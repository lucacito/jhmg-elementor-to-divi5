<?php
/**
 * Animation Addons for Elementor (references/animation-addons-for-elementor.*.zip)
 * ships every widget DISABLED until someone visits its dashboard and toggles them
 * on. Without this, ConverterRegistry::registerDefaults() entries for wcf--/aae--
 * widgets look right but the widget never registers with Elementor at all
 * (Elementor\Plugin::instance()->widgets_manager->get_widget_types() has none of
 * them), so any probe page using one silently falls back to Elementor's "invalid
 * widget" placeholder instead of testing the real thing.
 *
 * The toggle state lives in option `aaeaddon_save_widgets`, read by
 * Aaeaddon_Extension_Widgets_Trait::get_widgets() (only its array KEYS matter —
 * the PHP filename under widgets/, e.g. "counter", "image-box-slider", not the
 * "wcf--"/"aae--" Elementor widget slug). Run via:
 *
 *   docker compose run --rm cli wp eval-file /probe/enable-widgets.php
 *
 * or paste into `wp eval`.
 */

$slugs = [
    'advance-accordion', 'advanced-testimonial', 'animated-heading', 'animated-text', 'animated-title',
    'archive-title', 'author-box', 'banner-posts', 'brand-slider', 'breadcrumbs', 'button-pro', 'button',
    'category-showcase', 'category-slider', 'clickdrop', 'contact-form-7', 'content-slider', 'countdown',
    'counter', 'current-date', 'event-slider', 'feature-posts', 'filterable-slider', 'floating-elements',
    'grid-hover-posts', 'icon-box', 'image-accordion', 'image-box-slider', 'image-box', 'image-compare',
    'image-gallery', 'image-hotspot', 'image', 'loop-grid', 'nested-slider', 'notification', 'one-page-nav',
    'post-comment', 'post-content', 'post-excerpt', 'post-feature-image', 'post-meta-info', 'post-paginate',
    'post-rating-form', 'post-rating', 'post-reactions', 'post-social-share', 'post-timeline', 'post-title',
    'posts', 'progressbar', 'search-form', 'search-no-result', 'search-query', 'services-tab', 'site-logo',
    'social-icons', 'tabs', 'team-slider', 'team', 'testimonial', 'testimonial2', 'testimonial3',
    'text-hover-image', 'timeline', 'toggle-switcher', 'typewriter', 'video-posts-tab', 'weather',
    'advance-pricing-table', 'mailchimp', 'nav-menu',
];

update_option( 'aaeaddon_save_widgets', array_fill_keys( $slugs, true ) );
echo 'Enabled ' . count( $slugs ) . " Animation Addons widget slugs.\n";
