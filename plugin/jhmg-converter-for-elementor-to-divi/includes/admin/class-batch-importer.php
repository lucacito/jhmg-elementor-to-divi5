<?php

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\ConversionSource;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Runs the converter + exporter for a list of import items and returns results.
 *
 * Since 3.0.0 this is a thin orchestrator over ConversionPreflight (convert,
 * writing nothing) and ConversionCommitter (write). Its signature and result
 * shape are unchanged, because AdminPage and the Pro add-on both depend on them.
 *
 * Each input item must have shape:
 *   ['title' => string, 'post_type' => string, 'post_name' => string, 'elements' => array]
 *
 * Each returned result has shape:
 *   ['title' => string, 'post_id' => int, 'success' => bool, 'error' => string, 'report' => array]
 */
class BatchImporter {

    private ConversionPreflight $preflight;
    private ConversionCommitter $committer;

    public function __construct(
        ?ConverterEngine $engine = null,
        ?DiviExporter $exporter = null,
        ?object $themeBuilderExporter = null
    ) {
        $this->preflight = new ConversionPreflight( $engine );
        $this->committer = new ConversionCommitter( $exporter, $themeBuilderExporter );
    }

    /**
     * @param  array[] $items   Import items from ElementorImportParser::parse().
     * @param  array   $options Accepts: post_status ('draft'|'publish'), post_type override.
     * @return array[] Per-item results.
     */
    public function import( array $items, array $options = [] ): array {
        return $this->committer->commit( $this->preflight->runUnlimited( $this->sourceFor( $items ) ), $options );
    }

    /** Commit a plan a caller already built — the preview screen's convert step. */
    public function importPlan( ConversionPlan $plan, array $options = [] ): array {
        return $this->committer->commit( $plan, $options );
    }

    /**
     * The direct-conversion limit caps what the picker may select; it must not
     * cap an upload. A kit ZIP's page count is the user's file, not a tier
     * boundary, so uploads are planned at an unlimited cap.
     */
    private function sourceFor( array $items ): ConversionSource {
        return new class( $items ) implements ConversionSource {
            private array $items;
            public function __construct( array $items ) { $this->items = $items; }
            public function items(): array { return $this->items; }
        };
    }
}
