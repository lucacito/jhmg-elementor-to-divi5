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

    public function register( string $elementorType, $converterClass ): void {
        $this->registry[ $elementorType ] = $converterClass;
    }

    public function registerWidget( string $widgetType, $converterClass ): void {
        $this->registry[ 'widget:' . $widgetType ] = $converterClass;
    }

    public function getConverter( array $element ): ?ConverterInterface {
        if ( isset( $element['elType'] ) && $element['elType'] === 'widget' && ! empty( $element['widgetType'] ) ) {
            $widgetKey = 'widget:' . $element['widgetType'];
            if ( isset( $this->registry[ $widgetKey ] ) ) {
                return $this->instantiate( $this->registry[ $widgetKey ] );
            }
        }

        $elementType = $element['elType'] ?? '';

        if ( isset( $this->registry[ $elementType ] ) ) {
            return $this->instantiate( $this->registry[ $elementType ] );
        }

        $fallbackClass = $this->detectByShape( $element );
        if ( $fallbackClass !== null ) {
            $widgetType = $element['widgetType'] ?? $elementType;
            $this->engine->logWarning( "Widget '{$widgetType}' not registered; matched by settings shape to " . basename( str_replace( '\\', '/', $fallbackClass ) ) );
            return new $fallbackClass( $this->engine );
        }

        return null;
    }

    /**
     * Instantiate a converter from a class-name string or a factory closure.
     *
     * Closures receive the ConverterEngine as their sole argument and must
     * return a ConverterInterface. This allows converters with extra constructor
     * parameters (e.g. EaelFormShortcodeConverter) to be registered cleanly.
     */
    private function instantiate( $entry ): ConverterInterface {
        if ( is_callable( $entry ) ) {
            return $entry( $this->engine );
        }
        return new $entry( $this->engine );
    }

    private function detectByShape( array $element ): ?string {
        if ( ( $element['elType'] ?? '' ) !== 'widget' ) {
            return null;
        }
        $settings    = $element['settings'] ?? [];
        $has_content = ! empty( $settings['title_text'] ) || ! empty( $settings['description_text'] );

        if ( ! $has_content ) {
            return null;
        }

        if ( $this->settingsHasIcon( $settings ) ) {
            return '\\ElementorDivi5Converter\\Converter\\Handlers\\IconBoxConverter';
        }

        if ( $this->settingsHasImage( $settings ) ) {
            return '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageBoxConverter';
        }

        return null;
    }

    private function settingsHasIcon( array $settings ): bool {
        $selected = $settings['selected_icon'] ?? null;
        if ( is_array( $selected ) ) {
            $val = $selected['value'] ?? '';
            return is_string( $val ) && $val !== '';
        }
        if ( is_string( $selected ) && $selected !== '' ) {
            return true;
        }
        $legacy = $settings['icon'] ?? '';
        return is_string( $legacy ) && $legacy !== '';
    }

    private function settingsHasImage( array $settings ): bool {
        $image = $settings['image'] ?? null;
        if ( ! is_array( $image ) ) {
            return false;
        }
        $url = $image['url'] ?? '';
        return is_string( $url ) && $url !== '';
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
        $this->registerWidget( 'elementskit-heading', '\\ElementorDivi5Converter\\Converter\\Handlers\\ElementskitHeadingConverter' );
        $this->registerWidget( 'elementskit-video', '\\ElementorDivi5Converter\\Converter\\Handlers\\VideoConverter' );
        $this->registerWidget( 'icon-box', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconBoxConverter' );
        $this->registerWidget( 'menu-anchor', '\\ElementorDivi5Converter\\Converter\\Handlers\\MenuAnchorConverter' );
        $this->registerWidget( 'icon-list', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconListConverter' );
        $this->registerWidget( 'image-carousel', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageCarouselConverter' );
        $this->registerWidget( 'elementskit-dual-button', '\\ElementorDivi5Converter\\Converter\\Handlers\\ElementsKitDualButtonConverter' );
        $this->registerWidget( 'elementskit-accordion', '\\ElementorDivi5Converter\\Converter\\Handlers\\AccordionConverter' );
        $this->registerWidget( 'html', '\\ElementorDivi5Converter\\Converter\\Handlers\\HtmlConverter' );
        $this->registerWidget( 'gallery', '\\ElementorDivi5Converter\\Converter\\Handlers\\GalleryConverter' );
        $this->registerWidget( 'counter', '\\ElementorDivi5Converter\\Converter\\Handlers\\CounterConverter' );
        $this->registerWidget( 'call-to-action', '\\ElementorDivi5Converter\\Converter\\Handlers\\CtaConverter' );
        $this->registerWidget( 'progress-bar', '\\ElementorDivi5Converter\\Converter\\Handlers\\ProgressBarConverter' );
        $this->registerWidget( 'social-icons', '\\ElementorDivi5Converter\\Converter\\Handlers\\SocialIconsConverter' );
        $this->registerWidget( 'slides', '\\ElementorDivi5Converter\\Converter\\Handlers\\SliderConverter' );
        $this->registerWidget( 'star-rating', '\\ElementorDivi5Converter\\Converter\\Handlers\\StarRatingConverter' );
        $this->registerWidget( 'alert', '\\ElementorDivi5Converter\\Converter\\Handlers\\AlertConverter' );
        $this->registerWidget( 'premium-addon-blog', '\\ElementorDivi5Converter\\Converter\\Handlers\\PremiumBlogConverter' );

        // Legacy fixture widget type names (e- prefix).
        $this->registerWidget( 'e-heading', '\\ElementorDivi5Converter\\Converter\\Handlers\\HeadingConverter' );
        $this->registerWidget( 'e-paragraph', '\\ElementorDivi5Converter\\Converter\\Handlers\\TextEditorConverter' );
        $this->registerWidget( 'e-image', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageConverter' );
        $this->registerWidget( 'e-button', '\\ElementorDivi5Converter\\Converter\\Handlers\\ButtonConverter' );
        $this->registerWidget( 'e-divider', '\\ElementorDivi5Converter\\Converter\\Handlers\\DividerConverter' );
        $this->registerWidget( 'e-spacer', '\\ElementorDivi5Converter\\Converter\\Handlers\\SpacerConverter' );
        $this->registerWidget( 'e-icon', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconConverter' );
        $this->registerWidget( 'e-image-box', '\\ElementorDivi5Converter\\Converter\\Handlers\\ImageBoxConverter' );
        $this->registerWidget( 'e-icon-box', '\\ElementorDivi5Converter\\Converter\\Handlers\\IconBoxConverter' );
        $this->registerWidget( 'e-accordion', '\\ElementorDivi5Converter\\Converter\\Handlers\\AccordionConverter' );
        $this->registerWidget( 'e-toggle', '\\ElementorDivi5Converter\\Converter\\Handlers\\AccordionConverter' );
        $this->registerWidget( 'e-tabs', '\\ElementorDivi5Converter\\Converter\\Handlers\\TabsConverter' );

        // ── Native Elementor free widgets ────────────────────────────────────
        $this->registerWidget( 'shortcode',      '\\ElementorDivi5Converter\\Converter\\Handlers\\ShortcodeConverter' );
        $this->registerWidget( 'testimonial',    '\\ElementorDivi5Converter\\Converter\\Handlers\\TestimonialConverter' );
        $this->registerWidget( 'countdown',      '\\ElementorDivi5Converter\\Converter\\Handlers\\CountdownConverter' );
        $this->registerWidget( 'sidebar',        '\\ElementorDivi5Converter\\Converter\\Handlers\\SidebarConverter' );
        $this->registerWidget( 'audio',          '\\ElementorDivi5Converter\\Converter\\Handlers\\AudioConverter' );
        $this->registerWidget( 'google-maps',    '\\ElementorDivi5Converter\\Converter\\Handlers\\GoogleMapsConverter' );
        $this->registerWidget( 'search',         '\\ElementorDivi5Converter\\Converter\\Handlers\\SearchConverter' );

        // ── Native Elementor Pro widgets ─────────────────────────────────────
        $this->registerWidget( 'flip-box',           '\\ElementorDivi5Converter\\Converter\\Handlers\\FlipBoxConverter' );
        $this->registerWidget( 'price-list',         '\\ElementorDivi5Converter\\Converter\\Handlers\\PriceListConverter' );
        $this->registerWidget( 'price-table',        '\\ElementorDivi5Converter\\Converter\\Handlers\\PriceTableConverter' );
        $this->registerWidget( 'animated-headline',  '\\ElementorDivi5Converter\\Converter\\Handlers\\AnimatedHeadlineConverter' );
        $this->registerWidget( 'lottie',             '\\ElementorDivi5Converter\\Converter\\Handlers\\LottieConverter' );
        $this->registerWidget( 'posts',              '\\ElementorDivi5Converter\\Converter\\Handlers\\PostsConverter' );
        $this->registerWidget( 'portfolio',          '\\ElementorDivi5Converter\\Converter\\Handlers\\PostsConverter' );
        $this->registerWidget( 'form',               '\\ElementorDivi5Converter\\Converter\\Handlers\\FormConverter' );
        $this->registerWidget( 'text-path',          '\\ElementorDivi5Converter\\Converter\\Handlers\\TextPathConverter' );
        $this->registerWidget( 'table-of-contents',  '\\ElementorDivi5Converter\\Converter\\Handlers\\TableOfContentsConverter' );
        $this->registerWidget( 'hotspot',            '\\ElementorDivi5Converter\\Converter\\Handlers\\HotspotConverter' );

        // ── Native Elementor header widgets ──────────────────────────────────
        // nav-menu is the native Elementor Nav Menu widget (stores menu ID in 'menu').
        $this->registerWidget( 'nav-menu', '\\ElementorDivi5Converter\\Converter\\Handlers\\NavMenuConverter' );

        // ── Header Footer Elementor (HFE) ────────────────────────────────────
        $this->registerWidget( 'hfe-site-title',         '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeSiteTitleConverter' );
        $this->registerWidget( 'hfe-site-tagline',       '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeSiteTaglineConverter' );
        $this->registerWidget( 'site-logo',              '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeSiteLogoConverter' );
        $this->registerWidget( 'retina',                 '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeSiteLogoConverter' );
        $this->registerWidget( 'navigation-menu',        '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeNavigationMenuConverter' );
        $this->registerWidget( 'copyright',              '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeCopyrightConverter' );
        $this->registerWidget( 'page-title',             '\\ElementorDivi5Converter\\Converter\\Handlers\\HfePageTitleConverter' );
        $this->registerWidget( 'hfe-search-button',      '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeSearchConverter' );
        $this->registerWidget( 'hfe-counter',            '\\ElementorDivi5Converter\\Converter\\Handlers\\CounterConverter' );
        $this->registerWidget( 'hfe-breadcrumbs-widget', '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeBreadcrumbsConverter' );
        $this->registerWidget( 'infocard',               '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeInfocardConverter' );
        $this->registerWidget( 'post-info-widget',       '\\ElementorDivi5Converter\\Converter\\Handlers\\HfePostInfoConverter' );
        $this->registerWidget( 'hfe-basic-posts',        '\\ElementorDivi5Converter\\Converter\\Handlers\\HfeBasicPostsConverter' );
        $this->registerWidget( 'hfe-cart',               $this->fallbackCode( '[woocommerce_cart]' ) );
        $this->registerWidget( 'woo-product-grid',       $this->fallbackCode( '[products limit="12" columns="4"]' ) );

        // ── Essential Addons for Elementor (EAEL) — Tier 1 ──────────────────
        $this->registerWidget( 'eael-adv-accordion',      '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelAccordionConverter' );
        $this->registerWidget( 'eael-adv-tabs',           '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelTabsConverter' );
        $this->registerWidget( 'eael-countdown',          '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelCountdownConverter' );
        $this->registerWidget( 'eael-team-member',        '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelTeamMemberConverter' );
        $this->registerWidget( 'eael-testimonial',        '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelTestimonialConverter' );
        $this->registerWidget( 'eael-info-box',           '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelInfoBoxConverter' );
        $this->registerWidget( 'eael-flip-box',           '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelFlipBoxConverter' );
        $this->registerWidget( 'eael-pricing-table',      '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelPricingTableConverter' );
        $this->registerWidget( 'eael-post-grid',          '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelPostGridConverter' );
        $this->registerWidget( 'eael-creative-button',    '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelCreativeButtonConverter' );
        $this->registerWidget( 'eael-cta-box',            '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelCtaBoxConverter' );
        $this->registerWidget( 'eael-dual-color-header',  '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelDualColorHeaderConverter' );
        $this->registerWidget( 'eael-breadcrumbs',        '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelBreadcrumbsConverter' );
        $this->registerWidget( 'eael-progress-bar',       '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelProgressBarConverter' );
        $this->registerWidget( 'eael-filterable-gallery', '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelFilterableGalleryConverter' );

        // ── EAEL — Tier 2 (partial / code-based) ────────────────────────────
        $this->registerWidget( 'eael-contact-form-7', '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelContactForm7Converter' );
        $this->registerWidget( 'eael-feature-list',   '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelFeatureListConverter' );
        $this->registerWidget( 'eael-sticky-video',   '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelStickyVideoConverter' );
        $this->registerWidget( 'eael-code-snippet',   '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelCodeSnippetConverter' );
        $this->registerWidget( 'eael-ninja',          $this->formShortcode( 'ninja_form', 'eael_ninjaform_id' ) );
        $this->registerWidget( 'eael-wpforms',        $this->formShortcode( 'wpforms', 'eael_wpforms_form_id' ) );
        $this->registerWidget( 'eael-gravity-form',   $this->formShortcode( 'gravityforms', 'eael_gravity_form_id' ) );
        $this->registerWidget( 'eael-fluentform',     $this->formShortcode( 'fluentform', 'eael_fluentform_form_id' ) );
        $this->registerWidget( 'eael-weform',         $this->formShortcode( 'weforms', 'eael_weforms_form_id' ) );
        $this->registerWidget( 'eael-caldera-form',   $this->formShortcode( 'caldera_form', 'eael_caldera_form_id' ) );

        // ── EAEL — Tier 3 (upgraded: meaningful Divi equivalent exists) ─────
        $this->registerWidget( 'eael-fancy-text',         '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelFancyTextConverter' );
        $this->registerWidget( 'eael-content-ticker',     '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelContentTickerConverter' );
        $this->registerWidget( 'eael-data-table',         '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelDataTableConverter' );
        $this->registerWidget( 'eael-advanced-data-table','\\ElementorDivi5Converter\\Converter\\Handlers\\EaelDataTableConverter' );
        $this->registerWidget( 'eael-tooltip',            '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelTooltipConverter' );
        $this->registerWidget( 'eael-image-accordion',    '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelImageAccordionConverter' );
        $this->registerWidget( 'eael-simple-menu',        '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelSimpleMenuConverter' );
        $this->registerWidget( 'eael-login-register',     '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelLoginRegisterConverter' );
        $this->registerWidget( 'eael-event-calendar',     '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelEventCalendarConverter' );
        $this->registerWidget( 'eael-embedpress',         '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelEmbedpressConverter' );
        $this->registerWidget( 'eael-interactive-circle', '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelInteractiveCircleConverter' );
        $this->registerWidget( 'eael-post-timeline',      '\\ElementorDivi5Converter\\Converter\\Handlers\\EaelPostTimelineConverter' );

        // ── EAEL — WooCommerce (shortcode-based) ─────────────────────────────
        $this->registerWidget( 'eael-woo-cart',             $this->fallbackCode( '[woocommerce_cart]' ) );
        $this->registerWidget( 'eael-woo-checkout',         $this->fallbackCode( '[woocommerce_checkout]' ) );
        $this->registerWidget( 'eael-woo-product-list',     $this->fallbackCode( '[products limit="12" columns="3"]' ) );
        $this->registerWidget( 'eael-woo-product-carousel', $this->fallbackCode( '[products limit="12" columns="4"]' ) );
        $this->registerWidget( 'eael-woo-product-compare',  $this->fallbackCode( '[yith_woocommerce_compare]' ) );
        $this->registerWidget( 'eael-woo-add-to-cart',      $this->wooProductShortcode( 'add_to_cart', 'eael_product_id' ) );
        $this->registerWidget( 'eael-woo-product-gallery',  $this->wooProductShortcode( 'product_page', 'eael_product_id' ) );
        $this->registerWidget( 'eael-woo-product-images',   $this->wooProductShortcode( 'product_page', 'eael_product_id' ) );
        $this->registerWidget( 'eael-woo-product-price',    $this->wooProductShortcode( 'product_page', 'eael_product_id' ) );
        $this->registerWidget( 'eael-woo-product-rating',   $this->wooProductShortcode( 'product_page', 'eael_product_id' ) );

        // ── EAEL — Tier 4 (no Divi equivalent, generic placeholder) ─────────
        foreach ( [
            'eael-nft-gallery', 'eael-career-page', 'eael-svg-draw',
            'eael-betterdocs-category-box', 'eael-betterdocs-category-grid',
            'eael-betterdocs-search-form', 'eael-better-payment',
            'eael-typeform', 'eael-formstack',
            'eael-business-reviews', 'eael-facebook-feed', 'eael-twitter-feed',
        ] as $slug ) {
            $this->registerWidget( $slug, $this->genericFallback( $slug ) );
        }
    }

    private function wooProductShortcode( string $tag, string $id_key ): \Closure {
        return function( $engine ) use ( $tag, $id_key ) {
            return new class( $engine, $tag, $id_key ) extends \ElementorDivi5Converter\Converter\BaseElementorConverter {
                private string $tag;
                private string $id_key;
                public function __construct( $engine, string $tag, string $id_key ) {
                    parent::__construct( $engine );
                    $this->tag    = $tag;
                    $this->id_key = $id_key;
                }
                public function convert( array $element ): array {
                    $id         = $element['id'] ?? uniqid( 'divi_code_' );
                    $product_id = (int) ( $element['settings'][ $this->id_key ] ?? 0 );
                    $shortcode  = $product_id > 0
                        ? '[' . $this->tag . ' id="' . $product_id . '"]'
                        : '[' . $this->tag . ']';
                    $this->engine->logConverted( 'code' );
                    return [
                        'id'       => $id,
                        'name'     => 'divi/code',
                        'settings' => [
                            'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $shortcode ] ] ],
                        ],
                        'elements' => [],
                    ];
                }
            };
        };
    }

    private function formShortcode( string $tag, string $id_key ): \Closure {
        return function( $engine ) use ( $tag, $id_key ) {
            return new \ElementorDivi5Converter\Converter\Handlers\EaelFormShortcodeConverter( $engine, $tag, $id_key );
        };
    }

    private function genericFallback( string $widget_type ): \Closure {
        return function( $engine ) use ( $widget_type ) {
            return new \ElementorDivi5Converter\Converter\Handlers\GenericFallbackConverter( $engine, $widget_type );
        };
    }

    private function fallbackCode( string $html ): \Closure {
        return function( $engine ) use ( $html ) {
            return new class( $engine, $html ) extends \ElementorDivi5Converter\Converter\BaseElementorConverter {
                private string $html;
                public function __construct( $engine, string $html ) {
                    parent::__construct( $engine );
                    $this->html = $html;
                }
                public function convert( array $element ): array {
                    $id = $element['id'] ?? uniqid( 'divi_code_' );
                    $this->engine->logConverted( 'code' );
                    return [
                        'id'       => $id,
                        'name'     => 'divi/code',
                        'settings' => [
                            'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $this->html ] ] ],
                        ],
                        'elements' => [],
                    ];
                }
            };
        };
    }
}
