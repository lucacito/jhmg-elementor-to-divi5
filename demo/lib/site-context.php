<?php
/**
 * Reads the seeded site back into a Context, so checks load page files with the
 * same attachment IDs, form and categories the seed used. Requires WordPress.
 * Callers require elementor.php first.
 */

namespace Ferncourt\Demo;

/** The content and output directories as mounted in the containers. */
const CONTENT_DIR   = '/demo/content';
const OUTPUT_DIR    = '/demo/output';
const DOCUMENT_META = '_ferncourt_document';
const MEDIA_META    = '_ferncourt_file';

function site_context(): Context {
    $images = [];
    $attachments = get_posts( [
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => -1,
        'meta_key'    => MEDIA_META,
    ] );
    foreach ( $attachments as $attachment ) {
        $images[ get_post_meta( $attachment->ID, MEDIA_META, true ) ] = [
            'id'  => (int) $attachment->ID,
            'url' => (string) wp_get_attachment_url( $attachment->ID ),
            'alt' => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
        ];
    }

    $categories = [];
    foreach ( get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] ) as $term ) {
        $categories[ $term->slug ] = (int) $term->term_id;
    }

    return new Context( [
        'images'     => $images,
        'form_id'    => (int) get_option( 'ferncourt_form_id' ),
        'categories' => $categories,
        'menu'       => 'primary',
        'home'       => home_url( '/' ),
        'now'        => (int) get_option( 'ferncourt_seeded_at' ),
    ] );
}

/** The post seeded from a document, e.g. document_post_id( 'pages/home' ); 0 if there is none. */
function document_post_id( string $name ): int {
    $ids = get_posts( [
        'post_type'   => [ 'page', 'elementor-hf' ],
        'post_status' => 'any',
        'numberposts' => 1,
        'fields'      => 'ids',
        'meta_key'    => DOCUMENT_META,
        'meta_value'  => $name,
    ] );

    return (int) ( $ids[0] ?? 0 );
}

/** @return string[] Every document under content/, pages first: 'pages/about', …, 'templates/header'. */
function document_names(): array {
    $names = [];
    foreach ( [ 'pages', 'templates' ] as $dir ) {
        foreach ( glob( CONTENT_DIR . "/{$dir}/*.php" ) as $file ) {
            $names[] = $dir . '/' . basename( $file, '.php' );
        }
    }
    return $names;
}
