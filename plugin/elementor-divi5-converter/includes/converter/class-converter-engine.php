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

    public function __construct() {
        $this->registry = new ConverterRegistry( $this );
    }

    public function convert( array $elementor_data ): array {
        $elements = $this->extractRootElements( $elementor_data );

        return [
            'divi' => [
                'elements' => $this->convertChildren( $elements ),
            ],
            'unsupported' => $this->unsupportedWidgets,
        ];
    }

    public function convertChildren( array $elements ): array {
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

        return $converted;
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
