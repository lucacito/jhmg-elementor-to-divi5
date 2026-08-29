<?php
/**
 * Conversion input from an uploaded Elementor JSON (or kit ZIP) file.
 *
 * A thin adapter over the existing ElementorImportParser: the upload path's
 * behaviour is unchanged, it simply now arrives through the same interface as
 * every other source.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Parsers\ElementorImportParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UploadedJsonSource implements ConversionSource {

    private string $filePath;
    private string $fileName;
    private ElementorImportParser $parser;

    public function __construct( string $file_path, string $file_name = '', ?ElementorImportParser $parser = null ) {
        $this->filePath = $file_path;
        $this->fileName = $file_name;
        $this->parser   = $parser ?? new ElementorImportParser();
    }

    public function items(): array {
        try {
            $items = $this->parser->parse( $this->filePath, $this->fileName );
        } catch ( \Throwable $e ) {
            return [];
        }

        $out = [];
        foreach ( $items as $item ) {
            $item['source_ref'] = [
                'kind'    => 'upload',
                'post_id' => null,
                'file'    => $this->fileName,
            ];
            $out[] = $item;
        }

        return $out;
    }
}
