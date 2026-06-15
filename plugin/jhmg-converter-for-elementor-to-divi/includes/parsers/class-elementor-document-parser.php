<?php

namespace ElementorDivi5Converter\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ElementorDocumentParser {
    public function parse( array $post_meta ): array {
        $data = $this->extractRawElementorData( $post_meta );

        if ( is_array( $data ) ) {
            if ( isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
                return $data;
            }

            if ( isset( $data[0] ) && is_string( $data[0] ) ) {
                $decoded = json_decode( $data[0], true );

                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                if ( isset( $decoded['elements'] ) && is_array( $decoded['elements'] ) ) {
                    return $decoded;
                }

                return [ 'elements' => $decoded ];
            }
            }

            return [ 'elements' => $data ];
        }

        if ( is_string( $data ) ) {
            $decoded = json_decode( $data, true );

            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                if ( isset( $decoded['elements'] ) && is_array( $decoded['elements'] ) ) {
                    return $decoded;
                }

                return [ 'elements' => $decoded ];
            }
        }

        return [ 'elements' => [] ];
    }

    private function extractRawElementorData( array $post_meta ) {
        if ( isset( $post_meta['_elementor_data'] ) ) {
            return $post_meta['_elementor_data'];
        }

        if ( isset( $post_meta['_elementor_data'][0] ) ) {
            return $post_meta['_elementor_data'][0];
        }

        return null;
    }
}
