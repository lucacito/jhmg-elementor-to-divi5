<?php
/**
 * Writes a ConversionPlan to the database.
 *
 * The only class in the conversion pipeline that creates posts. Everything
 * upstream of it is read-only, which is what makes a preview trustworthy.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Exporters\DiviExporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionCommitter {

    private DiviExporter $exporter;
    /** Theme Builder exporter supplied by the Pro add-on via filter (null when absent). */
    private ?object $themeBuilderExporter;

    public function __construct( ?DiviExporter $exporter = null, ?object $themeBuilderExporter = null ) {
        $this->exporter = $exporter ?? new DiviExporter();
        $this->themeBuilderExporter = $themeBuilderExporter
            ?? ( function_exists( 'apply_filters' ) ? apply_filters( 'edc_theme_builder_exporter', null ) : null );
    }

    /**
     * @return array[] One result per plan item, shaped
     *   ['title','post_id','success','error','report','unsupported'].
     */
    public function commit( ConversionPlan $plan, array $options = [] ): array {
        $default_post_type   = $options['post_type']       ?? null;
        $default_post_status = $options['post_status']     ?? 'draft';
        $convert_headers     = $options['convert_headers'] ?? true;
        $convert_footers     = $options['convert_footers'] ?? true;

        $results = [];

        foreach ( $plan->items() as $item ) {
            if ( ( $item['error'] ?? '' ) !== '' ) {
                $results[] = $this->failResult( $item['title'], $item['error'] );
                continue;
            }

            $template_type = (string) ( $item['template_type'] ?? '' );

            $wants_theme_builder = ( $template_type === 'header' && $convert_headers )
                || ( $template_type === 'footer' && $convert_footers );

            if ( $wants_theme_builder && $this->themeBuilderExporter === null ) {
                $result = $this->commitPage( $item, $default_post_type, $default_post_status );
                $result['report']['warnings'][] = 'Theme Builder export for headers/footers requires the Pro add-on — imported as a regular draft instead. Get Pro: https://divi5lab.com/plugins/elementor-to-divi-5';
                $results[] = $result;
                continue;
            }

            if ( $wants_theme_builder ) {
                $results[] = $this->commitTemplate( $item, $template_type );
                continue;
            }

            $results[] = $this->commitPage( $item, $default_post_type, $default_post_status );
        }

        return $results;
    }

    /**
     * Post-type precedence: the caller's explicit option wins over the item's
     * own type; the item's own type is only the fallback when no option is
     * passed. Today's admin screen always passes an explicit option, so the
     * upload path keeps its exact current behaviour. Direct conversion passes
     * no post_type option, so a converted blog post correctly stays a post
     * instead of silently becoming a page.
     */
    private function commitPage( array $item, ?string $post_type_option, string $post_status ): array {
        $title     = (string) ( $item['title'] ?? 'Imported Page' );
        $post_name = (string) ( $item['post_name'] ?? '' );

        try {
            $post_type = $post_type_option ?? ( $item['post_type'] ?: 'page' );

            $post_args = [
                'post_type'    => $post_type,
                'post_title'   => $title ?: 'Imported Page',
                'post_status'  => $post_status,
                'post_content' => '',
            ];

            if ( $post_name !== '' ) {
                $post_args['post_name'] = $post_name;
            }

            $post_id = wp_insert_post( $post_args );

            if ( is_wp_error( $post_id ) || (int) $post_id === 0 ) {
                $error = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post returned 0';
                return $this->failResult( $title, $error );
            }

            $post_id = (int) $post_id;
            $this->exporter->save( $post_id, $this->diviDataFor( $item ) );
            $this->stampSource( $post_id, $item );

            return [
                'title'       => $title,
                'post_id'     => $post_id,
                'success'     => true,
                'error'       => '',
                'report'      => $item['report']      ?? [],
                'unsupported' => $item['unsupported'] ?? [],
            ];
        } catch ( \Throwable $e ) {
            return $this->failResult( $title, $e->getMessage() );
        }
    }

    private function commitTemplate( array $item, string $template_type ): array {
        $title = (string) ( $item['title'] ?? 'Imported Template' );

        try {
            $divi_data = $this->diviDataFor( $item );

            // The source travels with the content so the exporter can tell a
            // re-import of the same header from a genuinely new one. Without it
            // every import created another published layout and another default
            // template, and Divi's pick among the defaults looked arbitrary.
            $source_ref = $item['source_ref'] ?? [];

            $tb_result = $template_type === 'header'
                ? $this->themeBuilderExporter->saveHeader( $title, $divi_data, $source_ref )
                : $this->themeBuilderExporter->saveFooter( $title, $divi_data, $source_ref );

            $post_id = (int) ( $tb_result['post_id'] ?? 0 );
            if ( $post_id > 0 ) {
                $this->stampSource( $post_id, $item );
            }

            return [
                'title'            => $title,
                'post_id'          => $post_id,
                'template_id'      => $tb_result['template_id'] ?? 0,
                'theme_builder_id' => $tb_result['theme_builder_id'] ?? 0,
                'template_type'    => $template_type,
                'success'          => $tb_result['success'] ?? false,
                'error'            => $tb_result['error'] ?? '',
                'report'           => $item['report']      ?? [],
                'unsupported'      => $item['unsupported'] ?? [],
            ];
        } catch ( \Throwable $e ) {
            return $this->failResult( $title, $e->getMessage() );
        }
    }

    /**
     * DiviExporter and the Pro Theme Builder exporter both expect the engine's
     * output shape, so rebuild it from the plan item rather than storing a
     * second copy on the plan.
     */
    private function diviDataFor( array $item ): array {
        return [
            'divi'        => $item['blocks']      ?? [],
            'report'      => $item['report']      ?? [],
            'unsupported' => $item['unsupported'] ?? [],
        ];
    }

    private function stampSource( int $post_id, array $item ): void {
        $kind = $item['source_ref']['kind'] ?? 'upload';

        update_post_meta( $post_id, '_edc_import_source', $kind === 'installed' ? 'direct' : 'file_upload' );

        $source_post_id = $item['source_ref']['post_id'] ?? null;
        if ( $kind === 'installed' && $source_post_id ) {
            update_post_meta( $post_id, '_edc_source_post_id', (int) $source_post_id );
        }
    }

    private function failResult( string $title, string $error ): array {
        return [
            'title'       => $title,
            'post_id'     => 0,
            'success'     => false,
            'error'       => $error,
            'report'      => [],
            'unsupported' => [],
        ];
    }
}
