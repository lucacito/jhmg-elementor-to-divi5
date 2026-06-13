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

        // Real Elementor export widget type names (no prefix).
        $this->registerWidget( 'heading', '\\ElementorDivi5Converter\\Converter\\Handlers\\HeadingConverter' );
        $this->registerWidget( 'text-editor', '\\ElementorDivi5Converter\\Converter\\Handlers\\TextEditorConverter' );
        $this->registerWidget( 'image', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageConverter' );
        $this->registerWidget( 'button', '\\ElementorDivi5Converter\\Converter\\Handlers\\ButtonConverter' );
        $this->registerWidget( 'divider', '\\ElementorDivi5Converter\\Converter\\Handlers\\DividerConverter' );
        $this->registerWidget( 'video', '\\ElementorDivi5Converter\\Converter\\Handlers\\VideoConverter' );
        $this->registerWidget( 'spacer', '\\ElementorDivi5Converter\\Converter\\Handlers\\SpacerConverter' );
        $this->registerWidget( 'icon', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconConverter' );
        $this->registerWidget( 'image-box', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageBoxConverter' );
        $this->registerWidget( 'accordion', '\\ElementorDivi5Converter\\Converter\\Handlers\\AccordionConverter' );
        $this->registerWidget( 'toggle', '\\ElementorDivi5Converter\\Converter\\Handlers\\AccordionConverter' );
        $this->registerWidget( 'tabs', '\\ElementorDivi5Converter\\Converter\\Handlers\\TabsConverter' );
        $this->registerWidget( 'elementskit-testimonial', '\\ElementorDivi5Converter\\Converter\\Handlers\\ElementskitTestimonialConverter' );
        $this->registerWidget( 'icon-box', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconBoxConverter' );
        $this->registerWidget( 'menu-anchor', '\\ElementorDivi5Converter\\Converter\\Handlers\\MenuAnchorConverter' );

        // Legacy fixture widget type names (e- prefix).
        $this->registerWidget( 'e-heading', '\\ElementorDivi5Converter\\Converter\\Handlers\\HeadingConverter' );
        $this->registerWidget( 'e-paragraph', '\\ElementorDivi5Converter\\Converter\\Handlers\\TextEditorConverter' );
        $this->registerWidget( 'e-image', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageConverter' );
        $this->registerWidget( 'e-button', '\\ElementorDivi5Converter\\Converter\\Handlers\\ButtonConverter' );
        $this->registerWidget( 'e-divider', '\\ElementorDivi5Converter\\Converter\\Handlers\\DividerConverter' );
        $this->registerWidget( 'e-video', '\\ElementorDivi5Converter\\Converter\\Handlers\\VideoConverter' );
        $this->registerWidget( 'e-spacer', '\\ElementorDivi5Converter\\Converter\\Handlers\\SpacerConverter' );
        $this->registerWidget( 'e-icon', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconConverter' );
        $this->registerWidget( 'e-image-box', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageBoxConverter' );
        $this->registerWidget( 'e-icon-box', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconBoxConverter' );
        $this->registerWidget( 'e-accordion', '\\ElementorDivi5Converter\\Converter\\Handlers\\AccordionConverter' );
        $this->registerWidget( 'e-toggle', '\\ElementorDivi5Converter\\Converter\\Handlers\\AccordionConverter' );
        $this->registerWidget( 'e-tabs', '\\ElementorDivi5Converter\\Converter\\Handlers\\TabsConverter' );
    }
}
