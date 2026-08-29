<?php
/**
 * Conversion input read directly from posts on this site.
 *
 * This is the path that removes the export/upload round trip: the Elementor
 * data is already here, in `_elementor_data`, and ElementorDocumentParser
 * already knows every shape WordPress stores it in.
 *
 * Strictly read-only. The source post is never modified — a conversion always
 * creates a new post, so a failed or unwanted conversion can never cost the
 * user their Elementor original.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Parsers\ElementorDocumentParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class InstalledPostSource implements ConversionSource {

    /** @var int[] */
    private array $postIds;
    private ElementorDocumentParser $parser;

    public function __construct( array $post_ids, ?ElementorDocumentParser $parser = null ) {
        $this->postIds = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
        $this->parser  = $parser ?? new ElementorDocumentParser();
    }

    public function items(): array {
        $items = [];

        foreach ( $this->postIds as $post_id ) {
            $items[] = $this->itemFor( $post_id );
        }

        return $items;
    }

    private function itemFor( int $post_id ): array {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return $this->failed( $post_id, __( 'That page no longer exists.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $meta     = get_post_meta( $post_id );
        $document = $this->parser->parse( is_array( $meta ) ? $meta : [] );
        $elements = $document['elements'] ?? [];

        if ( empty( $elements ) ) {
            return $this->failed(
                $post_id,
                __( 'No Elementor content found on that page.', 'jhmg-converter-for-elementor-to-divi' ),
                (string) ( $post->post_title ?? '' )
            );
        }

        $post_type     = (string) ( $post->post_type ?? 'page' );
        $template_type = '';

        if ( $post_type === 'elementor_library' ) {
            $template_type = $this->templateType( $post_id );
            $post_type     = 'page';
        } elseif ( $post_type !== 'page' ) {
            $post_type = 'post';
        }

        return [
            'title'         => (string) ( $post->post_title ?? '' ) ?: __( 'Imported Page', 'jhmg-converter-for-elementor-to-divi' ),
            'post_type'     => $post_type,
            'post_name'     => (string) ( $post->post_name ?? '' ),
            'template_type' => $template_type,
            'elements'      => $elements,
            'error'         => '',
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => $post_id, 'file' => null ],
        ];
    }

    /**
     * Only header and footer templates have a Divi Theme Builder equivalent.
     * Every other Elementor library type (popup, single, archive) converts as
     * an ordinary page, which is what a blank template_type means downstream.
     */
    private function templateType( int $post_id ): string {
        $type = (string) get_post_meta( $post_id, '_elementor_template_type', true );

        return in_array( $type, [ 'header', 'footer' ], true ) ? $type : '';
    }

    private function failed( int $post_id, string $error, string $title = '' ): array {
        return [
            'title'         => $title !== '' ? $title : __( 'Unknown page', 'jhmg-converter-for-elementor-to-divi' ),
            'post_type'     => 'page',
            'post_name'     => '',
            'template_type' => '',
            'elements'      => [],
            'error'         => $error,
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => $post_id, 'file' => null ],
        ];
    }
}
