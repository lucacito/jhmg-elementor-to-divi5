<?php

use ElementorDivi5Converter\Conversion\ConversionSource;

/**
 * Feeds loaded demo documents to ConversionPreflight the way InstalledPostSource
 * feeds installed pages, without a database.
 */
final class DocumentSource implements ConversionSource {

    public function __construct( private array $documents ) {}

    public function items(): array {
        return array_map(
            static fn ( array $doc ): array => [
                'title'         => $doc['title'],
                'post_type'     => 'page',
                'post_name'     => $doc['slug'],
                'template_type' => '',
                'elements'      => $doc['elements'],
                'error'         => '',
                'source_ref'    => [ 'kind' => 'installed', 'post_id' => 0, 'file' => null ],
            ],
            $this->documents
        );
    }
}
