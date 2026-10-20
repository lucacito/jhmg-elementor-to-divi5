<?php
/**
 * Writes a ConversionPlan to the database.
 *
 * The only class in the conversion pipeline that creates posts. Everything
 * upstream of it is read-only, which is what makes a preview trustworthy.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Exporters\DiviExporter;
use ElementorDivi5Converter\Jobs\ConversionFlag;

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
        $replace_original    = ! empty( $options['replace_original'] );
        $skip_converted      = ! empty( $options['skip_converted'] );

        $results = [];

        foreach ( $plan->items() as $item ) {
            if ( ( $item['error'] ?? '' ) !== '' ) {
                $results[] = $this->failResult( $item['title'], $item['error'] );
                continue;
            }

            // Callers that ask (the jobs runner) never convert a page twice. The
            // picker does not ask: re-converting from it is a deliberate act.
            if ( $skip_converted ) {
                $source_id = $this->sourcePostId( $item );
                if ( $source_id > 0 && ConversionFlag::is_converted( $source_id ) ) {
                    $results[] = $this->skippedResult( $item['title'], $source_id );
                    continue;
                }
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

            if ( $replace_original && $this->canReplace( $item ) ) {
                $results[] = $this->replacePage( $item );
                continue;
            }

            $results[] = $this->commitPage( $item, $default_post_type, $default_post_status );
        }

        $this->flagConvertedSources( $plan->items(), $results );

        return $results;
    }

    private function sourcePostId( array $item ): int {
        return ( $item['source_ref']['kind'] ?? '' ) === 'installed' ? (int) ( $item['source_ref']['post_id'] ?? 0 ) : 0;
    }

    /**
     * Records on each successfully converted installed page that it is done,
     * so it leaves the pending list. Items and results line up one to one.
     * (A skipped item has success=false, so an existing flag is never touched.)
     */
    private function flagConvertedSources( array $items, array $results ): void {
        foreach ( array_values( $items ) as $i => $item ) {
            $source_id = $this->sourcePostId( $item );
            if ( $source_id > 0 && ! empty( $results[ $i ]['success'] ) ) {
                ConversionFlag::mark( $source_id );
            }
        }
    }

    private function skippedResult( string $title, int $source_id ): array {
        $when = ConversionFlag::converted_at( $source_id );

        return [
            'title'       => $title,
            'post_id'     => $source_id,
            'success'     => false,
            'skipped'     => true,
            'error'       => sprintf(
                /* translators: %s: date the page was converted */
                __( 'Already converted on %s, so it was skipped.', 'jhmg-converter-for-elementor-to-divi' ),
                $when !== '' ? $when : __( 'an earlier run', 'jhmg-converter-for-elementor-to-divi' )
            ),
            'report'      => [],
            'unsupported' => [],
        ];
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

    /**
     * Only a page converted from this site has an original to replace — an
     * uploaded file has none — and only an ordinary content post: an Elementor
     * template (header, footer, popup) is something other pages may still
     * render through, and a Theme Builder item is a layout, not the page.
     */
    private function canReplace( array $item ): bool {
        if ( ( $item['source_ref']['kind'] ?? '' ) !== 'installed' || ( $item['template_type'] ?? '' ) !== '' ) {
            return false;
        }

        $source = get_post( (int) ( $item['source_ref']['post_id'] ?? 0 ) );

        return $source && ReplaceBackup::replaceable_type( (string) ( $source->post_type ?? '' ) );
    }

    /**
     * Convert the source page where it stands: same post, same URL, same
     * menu entries. The Elementor version is backed up first and is what Undo
     * restores. If anything fails after the backup the page is put back as it
     * was — a failed replace must never leave a half-converted original.
     */
    private function replacePage( array $item ): array {
        $title   = (string) ( $item['title'] ?? 'Imported Page' );
        $post_id = (int) $item['source_ref']['post_id'];
        $backup  = new ReplaceBackup();

        try {
            if ( ! $backup->save( $post_id ) ) {
                return $this->failResult( $title, 'The original page could not be backed up, so it was left unchanged.' );
            }

            try {
                $backup->strip_elementor( $post_id );
                $this->exporter->save( $post_id, $this->diviDataFor( $item ) );
            } catch ( \Throwable $e ) {
                $backup->restore( $post_id );
                throw $e;
            }

            update_post_meta( $post_id, '_edc_import_source', 'replaced' );

            return [
                'title'       => $title,
                'post_id'     => $post_id,
                'success'     => true,
                'replaced'    => true,
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
