<?php

namespace ElementorDivi5Converter\Converter;

use ElementorDivi5Converter\Converter\ConverterInterface;
use ElementorDivi5Converter\Converter\Registry\ConverterRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConverterEngine {
    private ConverterRegistry $registry;
    private array $unsupportedWidgets = [];
    private array $conversionCounts   = [];
    private array $conversionWarnings = [];
    private array $skippedSettings    = [];
    private int   $nestingDepth       = 0;

    public function __construct() {
        $this->registry = new ConverterRegistry( $this );
    }

    public function logConverted( string $type ): void {
        $this->conversionCounts[ $type ] = ( $this->conversionCounts[ $type ] ?? 0 ) + 1;
    }

    public function logWarning( string $message ): void {
        $this->conversionWarnings[] = $message;
    }

    public function logSkippedSetting( string $message ): void {
        $this->skippedSettings[] = $message;
    }

    public function getReport(): array {
        return [
            'converted'        => $this->conversionCounts,
            'warnings'         => $this->conversionWarnings,
            'skipped_settings' => $this->skippedSettings,
        ];
    }

    public function convert( array $elementor_data ): array {
        $elements = $this->extractRootElements( $elementor_data );

        return [
            'divi'        => [
                'elements' => $this->convertChildren( $elements ),
            ],
            'unsupported' => $this->unsupportedWidgets,
            'report'      => $this->getReport(),
        ];
    }

    public function convertChildren( array $elements ): array {
        $this->nestingDepth++;
        $converted = [];

        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $result = $this->convertElement( $element );

            if ( ! empty( $result ) ) {
                $converted[] = $result;
            }
        }

        $this->nestingDepth--;
        return $converted;
    }

    public function getNestingDepth(): int {
        return $this->nestingDepth;
    }

    public function convertElement( array $element ): array {
        $converter = $this->registry->getConverter( $element );

        if ( $converter instanceof ConverterInterface ) {
            return $converter->convert( $element );
        }

        $this->logUnsupportedElement( $element );

        return [];
    }

    private function extractRootElements( array $elementor_data ): array {
        if ( isset( $elementor_data['elements'] ) && is_array( $elementor_data['elements'] ) ) {
            return $elementor_data['elements'];
        }

        return $elementor_data;
    }

    private function logUnsupportedElement( array $element ): void {
        $this->unsupportedWidgets[] = [
            'id' => $element['id'] ?? null,
            'elType' => $element['elType'] ?? null,
            'widgetType' => $element['widgetType'] ?? null,
        ];
    }

    public function getUnsupportedElements(): array {
        return $this->unsupportedWidgets;
    }
}
