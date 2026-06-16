<?php

namespace ElementorDivi5Converter\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parses Elementor export files into a normalized list of import items.
 *
 * Supported formats:
 *  - Raw JSON array: the `_elementor_data` blob (array of top-level elements).
 *  - Elementor template JSON: `{version, title, type, content: [...]}`.
 *  - JSON object with `elements` key and optional post metadata.
 *  - Elementor Kit ZIP: root `manifest.json` + `content/*.json` files.
 *  - Generic ZIP: fallback — treat each `.json` file in the archive as one page.
 *
 * Each returned item has shape:
 *   ['title' => string, 'post_type' => string, 'post_name' => string, 'elements' => array]
 */
class ElementorImportParser {
    private const MAX_PAGES = 500;

    /**
     * @param  string $file_path Absolute path to the uploaded file.
     * @param  string $file_name Original filename (used for type detection and fallback title).
     * @return array[] Normalized import items.
     * @throws \RuntimeException On unrecognizable or unreadable input.
     */
    public function parse( string $file_path, string $file_name = '' ): array {
        if ( ! is_readable( $file_path ) ) {
            throw new \RuntimeException( 'Import file is not readable.' );
        }

        if ( $this->isZipFile( $file_path, $file_name ) ) {
            return $this->parseZip( $file_path );
        }

        return $this->parseJson( $file_path, $file_name );
    }

    // -------------------------------------------------------------------------
    // Format detection
    // -------------------------------------------------------------------------

    private function isZipFile( string $file_path, string $file_name ): bool {
        // Check magic bytes (PK signature) first — reliable regardless of extension.
        $magic = file_get_contents( $file_path, false, null, 0, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( $magic === 'PK' ) {
            return true;
        }

        // Fall back to extension.
        return strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) ) === 'zip';
    }

    // -------------------------------------------------------------------------
    // JSON path
    // -------------------------------------------------------------------------

    private function parseJson( string $file_path, string $file_name = '' ): array {
        $raw = file_get_contents( $file_path );
        if ( $raw === false ) {
            throw new \RuntimeException( 'Could not read import file.' );
        }

        $decoded = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new \RuntimeException( 'Import file is not valid JSON: ' . esc_html( json_last_error_msg() ) );
        }

        $title    = $this->titleFromFileName( $file_name );
        $elements = $this->extractElements( $decoded, $meta );

        if ( $elements === null ) {
            throw new \RuntimeException( 'Could not find Elementor elements in JSON file.' );
        }

        return [
            $this->makeItem(
                $meta['title']         ?? $title,
                $elements,
                $meta['post_type']     ?? 'page',
                $meta['post_name']     ?? '',
                $meta['template_type'] ?? ''
            ),
        ];
    }

    // -------------------------------------------------------------------------
    // ZIP path
    // -------------------------------------------------------------------------

    private function parseZip( string $file_path ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new \RuntimeException(
                'ZIP support requires the PHP ZipArchive extension. Upload a JSON file instead.'
            );
        }

        $zip = new \ZipArchive();
        $opened = $zip->open( $file_path );

        if ( $opened !== true ) {
            throw new \RuntimeException( 'Could not open ZIP file (error code: ' . (int) $opened . ').' );
        }

        $items = [];

        try {
            // Try Elementor Kit format first: root manifest.json.
            $manifest_raw = $zip->getFromName( 'manifest.json' );
            if ( $manifest_raw !== false ) {
                $items = $this->parseZipManifest( $zip, $manifest_raw );
            }

            // Fallback: treat every .json in the archive as its own page.
            if ( empty( $items ) ) {
                $items = $this->parseZipFallback( $zip );
            }
        } finally {
            $zip->close();
        }

        return array_slice( $items, 0, self::MAX_PAGES );
    }

    private function parseZipManifest( \ZipArchive $zip, string $manifest_raw ): array {
        $manifest = json_decode( $manifest_raw, true );
        if ( ! is_array( $manifest ) ) {
            return [];
        }

        $items = [];

        // Elementor Kit manifests have a `pages` section; some also have `templates`.
        foreach ( [ 'pages', 'templates' ] as $section ) {
            $entries = $manifest[ $section ] ?? [];
            if ( ! is_array( $entries ) ) {
                continue;
            }

            foreach ( $entries as $key => $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }

                // Determine content file path.
                $content_file = $entry['content_file'] ?? ( 'content/' . ( $entry['post_name'] ?? $key ) . '.json' );
                $content_raw  = $zip->getFromName( $content_file );

                if ( $content_raw === false ) {
                    // Try common alternative paths.
                    $content_raw = $zip->getFromName( $key . '.json' )
                        ?: $zip->getFromName( 'content/' . $key . '.json' );
                }

                if ( $content_raw === false ) {
                    continue;
                }

                $content  = json_decode( $content_raw, true );
                $meta     = [];
                $elements = $this->extractElements( $content, $meta );

                if ( $elements === null ) {
                    continue;
                }

                $raw_post_type = $entry['post_type'] ?? $meta['post_type'] ?? 'page';
                $template_type = $meta['template_type'] ?? '';
                if ( $template_type === '' && $this->isHeaderTemplateType( $raw_post_type ) ) {
                    $template_type = 'header';
                } elseif ( $template_type === '' && $this->isFooterTemplateType( $raw_post_type ) ) {
                    $template_type = 'footer';
                }
                $items[] = $this->makeItem(
                    $entry['post_title'] ?? $meta['title'] ?? (string) $key,
                    $elements,
                    $raw_post_type,
                    $entry['post_name'] ?? $meta['post_name'] ?? '',
                    $template_type
                );

                if ( count( $items ) >= self::MAX_PAGES ) {
                    break 2;
                }
            }
        }

        return $items;
    }

    private function parseZipFallback( \ZipArchive $zip ): array {
        $items = [];

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $stat = $zip->statIndex( $i );
            if ( ! $stat || strtolower( pathinfo( $stat['name'], PATHINFO_EXTENSION ) ) !== 'json' ) {
                continue;
            }
            if ( basename( $stat['name'] ) === 'manifest.json' ) {
                continue;
            }

            $raw     = $zip->getFromIndex( $i );
            $decoded = $raw !== false ? json_decode( $raw, true ) : null;
            if ( ! is_array( $decoded ) ) {
                continue;
            }

            $meta     = [];
            $elements = $this->extractElements( $decoded, $meta );
            if ( $elements === null ) {
                continue;
            }

            $items[] = $this->makeItem(
                $meta['title']         ?? $this->titleFromFileName( basename( $stat['name'] ) ),
                $elements,
                $meta['post_type']     ?? 'page',
                $meta['post_name']     ?? '',
                $meta['template_type'] ?? ''
            );

            if ( count( $items ) >= self::MAX_PAGES ) {
                break;
            }
        }

        return $items;
    }

    // -------------------------------------------------------------------------
    // Element extraction
    // -------------------------------------------------------------------------

    /**
     * Attempt to extract the Elementor elements array from decoded JSON.
     * Populates $meta by reference with any page metadata found in the JSON.
     *
     * @param  mixed      $decoded Decoded JSON value.
     * @param  array|null $meta    Receives extracted metadata (title, post_type, post_name).
     * @return array|null The elements array, or null if unrecognizable.
     */
    private function extractElements( mixed $decoded, ?array &$meta = null ): ?array {
        $meta = [];

        // Format 1: raw array of Elementor elements.
        if ( is_array( $decoded ) && isset( $decoded[0] ) && is_array( $decoded[0] ) ) {
            return $decoded;
        }

        if ( ! is_array( $decoded ) ) {
            return null;
        }

        // Collect any metadata present in the object.
        foreach ( [ 'title', 'post_title' ] as $k ) {
            if ( isset( $decoded[ $k ] ) && is_string( $decoded[ $k ] ) ) {
                $meta['title'] = $decoded[ $k ];
                break;
            }
        }
        foreach ( [ 'type', 'post_type' ] as $k ) {
            if ( isset( $decoded[ $k ] ) && is_string( $decoded[ $k ] ) ) {
                $meta['post_type'] = $decoded[ $k ];
                break;
            }
        }
        if ( isset( $decoded['post_name'] ) && is_string( $decoded['post_name'] ) ) {
            $meta['post_name'] = $decoded['post_name'];
        }

        // Detect Elementor Theme Builder template types (header, footer, etc.).
        $raw_type = $meta['post_type'] ?? '';
        if ( $this->isHeaderTemplateType( $raw_type ) ) {
            $meta['template_type'] = 'header';
        } elseif ( $this->isFooterTemplateType( $raw_type ) ) {
            $meta['template_type'] = 'footer';
        }

        // Format 2: Elementor template export — {version, title, type, content: [...]}.
        if ( isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
            return $decoded['content'];
        }

        // Format 3: object with 'elements' key.
        if ( isset( $decoded['elements'] ) && is_array( $decoded['elements'] ) ) {
            return $decoded['elements'];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeItem( string $title, array $elements, string $post_type = 'page', string $post_name = '', string $template_type = '' ): array {
        $item = [
            'title'     => $title ?: 'Imported Page',
            'post_type' => in_array( $post_type, [ 'page', 'post' ], true ) ? $post_type : 'page',
            'post_name' => $post_name,
            'elements'  => $elements,
        ];
        if ( $template_type !== '' ) {
            $item['template_type'] = $template_type;
        }
        return $item;
    }

    /**
     * Elementor Theme Builder headers export with type='header' (or legacy variants).
     * Also covers HFE (Header Footer Elementor) templates.
     */
    private function isHeaderTemplateType( string $type ): bool {
        return in_array( strtolower( $type ), [
            'header',
            'et_header_layout',
            'hfe-template',   // HFE plugin template post type
        ], true );
    }

    /**
     * Elementor Theme Builder footers export with type='footer' (or legacy variants).
     */
    private function isFooterTemplateType( string $type ): bool {
        return in_array( strtolower( $type ), [
            'footer',
            'et_footer_layout',
        ], true );
    }

    private function titleFromFileName( string $file_name ): string {
        $base = pathinfo( $file_name, PATHINFO_FILENAME );
        if ( $base === '' ) {
            return '';
        }
        return ucwords( str_replace( [ '-', '_' ], ' ', $base ) );
    }
}
