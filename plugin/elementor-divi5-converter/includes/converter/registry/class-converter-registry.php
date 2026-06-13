<?php

namespace ElementorDivi5Converter\Converter\Registry;

use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Converter\ConverterInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConverterRegistry {
    private ConverterEngine $engine;
    private array $registry = [];

    public function __construct( ConverterEngine $engine ) {
        $this->engine = $engine;
        $this->registerDefaults();
    }

    public function register( string $elementorType, string $converterClass ): void {
        $this->registry[ $elementorType ] = $converterClass;
    }

    public function registerWidget( string $widgetType, string $converterClass ): void {
        $this->registry[ 'widget:' . $widgetType ] = $converterClass;
    }

    public function getConverter( array $element ): ?ConverterInterface {
        if ( isset( $element['elType'] ) && $element['elType'] === 'widget' && ! empty( $element['widgetType'] ) ) {
            $widgetKey = 'widget:' . $element['widgetType'];
            if ( isset( $this->registry[ $widgetKey ] ) ) {
                return new $this->registry[ $widgetKey ]( $this->engine );
            }
        }

        $elementType = $element['elType'] ?? '';

        if ( isset( $this->registry[ $elementType ] ) ) {
            return new $this->registry[ $elementType ]( $this->engine );
        }

        return null;
    }

    private function registerDefaults(): void {
        $this->register( 'section', '\\ElementorDivi5Converter\\Converter\\Handlers\\SectionConverter' );
        $this->register( 'container', '\\ElementorDivi5Converter\\Converter\\Handlers\\ContainerConverter' );
        $this->register( 'column', '\\ElementorDivi5Converter\\Converter\\Handlers\\ColumnConverter' );

        $this->registerWidget( 'e-heading', '\\ElementorDivi5Converter\\Converter\\Handlers\\HeadingConverter' );
        $this->registerWidget( 'e-paragraph', '\\ElementorDivi5Converter\\Converter\\Handlers\\TextEditorConverter' );
        $this->registerWidget( 'e-image', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageConverter' );
        $this->registerWidget( 'e-button', '\\ElementorDivi5Converter\\Converter\\Handlers\\ButtonConverter' );
    }
}
