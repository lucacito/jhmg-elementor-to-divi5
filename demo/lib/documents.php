<?php
/**
 * Inserts a loaded document the way Elementor stores one saved in its editor.
 * Shared by seed.php (pages and HFE templates) and seed-probes.php.
 */

namespace Ferncourt\Demo;

/** @return int The new post ID. */
function insert_document( array $doc, string $name ): int {
    $is_template = $doc['template'] !== '';

    $post_id = wp_insert_post( [
        'post_type'   => $is_template ? 'elementor-hf' : 'page',
        'post_status' => 'publish',
        'post_title'  => $doc['title'],
        'post_name'   => $doc['slug'],
    ], true );
    if ( is_wp_error( $post_id ) ) {
        \WP_CLI::error( "{$name}: " . $post_id->get_error_message() );
    }

    update_post_meta( $post_id, DOCUMENT_META, $name );
    update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $post_id, '_elementor_template_type', $is_template ? 'wp-post' : 'wp-page' );
    update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
    // update_post_meta() unslashes, so slash the JSON first, as Elementor does.
    update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $doc['elements'] ) ) );

    if ( $is_template ) {
        // Header Footer Elementor display rules: the entire website, every user.
        update_post_meta( $post_id, 'ehf_template_type', 'type_' . $doc['template'] );
        update_post_meta( $post_id, 'ehf_target_include_locations', [ 'rule' => [ 'basic-global' ], 'specific' => [] ] );
        update_post_meta( $post_id, 'ehf_target_exclude_locations', [] );
        update_post_meta( $post_id, 'ehf_target_user_roles', [ 'all' ] );
        return (int) $post_id;
    }

    update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
    update_post_meta( $post_id, '_elementor_page_settings', [ 'hide_title' => 'yes' ] );

    return (int) $post_id;
}
