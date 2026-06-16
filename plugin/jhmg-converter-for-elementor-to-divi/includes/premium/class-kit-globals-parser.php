<?php

namespace ElementorDivi5Converter\Premium;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KitGlobalsParser {

    public function parse( string $zip_path ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new \RuntimeException( 'PHP ZipArchive extension is required.' );
        }

        $zip = new \ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            throw new \RuntimeException( 'Could not open ZIP file.' );
        }

        $settings_json = $zip->getFromName( 'site-settings.json' );
        if ( $settings_json === false ) {
            $zip->close();
            throw new \RuntimeException( 'This does not look like an Elementor Export Kit — site-settings.json was not found in the ZIP.' );
        }

        $kit_name      = '';
        $manifest_json = $zip->getFromName( 'manifest.json' );
        if ( $manifest_json !== false ) {
            $manifest = json_decode( $manifest_json, true );
            $kit_name = $manifest['name'] ?? $manifest['kit_name'] ?? '';
        }

        $zip->close();

        $data = json_decode( $settings_json, true );
        if ( ! is_array( $data ) || ! isset( $data['settings'] ) ) {
            throw new \RuntimeException( 'site-settings.json has an unexpected format.' );
        }

        $settings = $data['settings'];

        $colors = [];
        foreach ( array_merge( $settings['system_colors'] ?? [], $settings['custom_colors'] ?? [] ) as $entry ) {
            $id    = $entry['_id']   ?? null;
            $color = $entry['color'] ?? null;
            if ( $id !== null && $color !== null ) {
                $colors[ $id ] = $color;
            }
        }

        $typography = [];
        foreach ( array_merge( $settings['system_typography'] ?? [], $settings['custom_typography'] ?? [] ) as $entry ) {
            $id = $entry['_id'] ?? null;
            if ( $id === null ) {
                continue;
            }

            $typo = [];

            if ( ! empty( $entry['typography_font_family'] ) ) {
                $typo['family'] = $entry['typography_font_family'];
            }
            if ( ! empty( $entry['typography_font_weight'] ) ) {
                $typo['weight'] = $entry['typography_font_weight'];
            }
            if ( ! empty( $entry['typography_font_size'] ) && is_array( $entry['typography_font_size'] ) ) {
                $size_val  = $entry['typography_font_size']['size'] ?? null;
                $size_unit = $entry['typography_font_size']['unit'] ?? 'px';
                if ( $size_val !== null && $size_val !== '' ) {
                    $typo['size'] = $size_val . $size_unit;
                }
            }
            if ( ! empty( $entry['typography_line_height'] ) && is_array( $entry['typography_line_height'] ) ) {
                $lh_val  = $entry['typography_line_height']['size'] ?? null;
                $lh_unit = $entry['typography_line_height']['unit'] ?? 'em';
                if ( $lh_val !== null && $lh_val !== '' ) {
                    $typo['lineHeight'] = $lh_val . $lh_unit;
                }
            }
            if ( isset( $entry['typography_letter_spacing'] ) && $entry['typography_letter_spacing'] !== '' ) {
                $typo['letterSpacing'] = $entry['typography_letter_spacing'] . 'px';
            }

            if ( ! empty( $typo ) ) {
                $typography[ $id ] = $typo;
            }
        }

        return [
            'colors'     => $colors,
            'typography' => $typography,
            'name'       => (string) $kit_name,
        ];
    }

    public function extract_pages( string $zip_path ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new \RuntimeException( 'PHP ZipArchive extension is required.' );
        }

        $zip = new \ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            throw new \RuntimeException( 'Could not open ZIP file.' );
        }

        $pages = [];

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $stat = $zip->statIndex( $i );
            if ( ! $stat ) {
                continue;
            }
            $name = $stat['name'];
            if ( preg_match( '#^content/(page|post)/[^/]+\.json$#i', $name, $m ) ) {
                $type = strtolower( $m[1] );
            } elseif ( preg_match( '#^templates/[^/]+\.json$#i', $name ) ) {
                $type = 'page';
            } else {
                continue;
            }
            $raw     = $zip->getFromIndex( $i );
            $decoded = $raw !== false ? json_decode( $raw, true ) : null;
            $title   = '';
            if ( is_array( $decoded ) ) {
                $title = (string) ( $decoded['title'] ?? $decoded['post_title'] ?? '' );
            }
            if ( $title === '' ) {
                $title = pathinfo( basename( $name ), PATHINFO_FILENAME );
            }
            $pages[] = [
                'zip_entry' => $name,
                'title'     => $title,
                'type'      => $type,
            ];
        }

        $zip->close();

        return $pages;
    }
}
