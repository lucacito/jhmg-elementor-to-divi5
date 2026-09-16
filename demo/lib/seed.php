<?php
/**
 * Seeds the Ferncourt site on a fresh install: media, categories, posts, the contact
 * form, kit colors and fonts, the logo, every page and HFE template, and the menu.
 *
 * Run once: demo/wp --user=admin eval-file /demo/lib/seed.php
 */

namespace Ferncourt\Demo;

use WP_CLI;

require_once __DIR__ . '/elementor.php';
require_once __DIR__ . '/site-context.php';
require_once __DIR__ . '/documents.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

@ini_set( 'memory_limit', '1024M' );

if ( get_option( 'ferncourt_seeded_at' ) ) {
    WP_CLI::error( 'This site is already seeded. Run demo/build.sh to start from nothing.' );
}
update_option( 'ferncourt_seeded_at', time() );

// WordPress's sample post, sample page and privacy policy draft.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $post_id ) {
    wp_delete_post( $post_id, true );
}

// Media: every photo, the logo, and the tour video with its poster.
$media = media_files( CONTENT_DIR );
foreach ( $media as $file => $entry ) {
    // media_handle_sideload() moves the file it is given, so hand it a copy.
    $tmp = wp_tempnam( $file );
    copy( $entry['path'], $tmp );
    $attachment_id = media_handle_sideload( [ 'name' => $file, 'tmp_name' => $tmp ], 0 );
    if ( is_wp_error( $attachment_id ) ) {
        WP_CLI::error( "{$file}: " . $attachment_id->get_error_message() );
    }
    update_post_meta( $attachment_id, MEDIA_META, $file );
    if ( $entry['alt'] !== '' ) {
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $entry['alt'] );
    }
}
WP_CLI::log( 'Imported ' . count( $media ) . ' media files.' );

// Categories. News becomes the default so Uncategorized can be deleted.
foreach ( [ 'news' => 'News', 'events' => 'Events', 'member-stories' => 'Member Stories' ] as $slug => $name ) {
    $term = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );
    if ( is_wp_error( $term ) ) {
        WP_CLI::error( "Category {$slug}: " . $term->get_error_message() );
    }
}
update_option( 'default_category', get_term_by( 'slug', 'news', 'category' )->term_id );
$uncategorized = get_term_by( 'slug', 'uncategorized', 'category' );
if ( $uncategorized ) {
    wp_delete_term( $uncategorized->term_id, 'category' );
}

// Contact Form 7's default form (name, email, subject, message), retitled.
$form = \WPCF7_ContactForm::get_template( [ 'title' => 'Book a tour' ] );
update_option( 'ferncourt_form_id', (int) $form->save() );

$ctx = site_context();

// Posts.
foreach ( require CONTENT_DIR . '/posts.php' as $post ) {
    $post_id = wp_insert_post( [
        'post_type'     => 'post',
        'post_status'   => 'publish',
        'post_title'    => $post['title'],
        'post_name'     => $post['slug'],
        'post_excerpt'  => $post['excerpt'],
        'post_content'  => $post['content'],
        'post_date'     => $ctx->date( $post['published'], 'Y-m-d H:i:s' ),
        'post_category' => [ $ctx->categoryId( $post['category'] ) ],
    ], true );
    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( "{$post['slug']}: " . $post_id->get_error_message() );
    }
    set_post_thumbnail( $post_id, $ctx->image( $post['image'] )['id'] );
}
WP_CLI::log( 'Created the posts.' );

// Kit colors and fonts. Elementor creates its default kit on activation; create it if not.
$kit_id = (int) get_option( 'elementor_active_kit' );
if ( $kit_id === 0 ) {
    $kit_id = (int) \Elementor\Core\Kits\Manager::create_default_kit();
}
$kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
update_post_meta(
    $kit_id,
    '_elementor_page_settings',
    array_merge( is_array( $kit_settings ) ? $kit_settings : [], require CONTENT_DIR . '/kit.php' )
);

// The logo, for Hello Elementor and for HFE's site-logo fallback.
$logo_id = $ctx->image( 'logo.png' )['id'];
set_theme_mod( 'custom_logo', $logo_id );
update_option( 'site_logo', $logo_id );

// Pages and HFE templates, stored the way Elementor stores a document saved in its editor.
$pages = [];
foreach ( document_names() as $name ) {
    $doc     = load_document( CONTENT_DIR . "/{$name}.php", $ctx );
    $post_id = insert_document( $doc, $name );
    if ( $doc['template'] !== '' ) {
        continue;
    }
    $pages[ $doc['slug'] ] = $post_id;

    if ( $doc['front_page'] ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $post_id );
    }
}

// The primary menu, in Hello Elementor's header and footer locations. HFE's navigation
// menus name it by its slug, "primary".
$menu_id = wp_create_nav_menu( 'Primary' );
if ( is_wp_error( $menu_id ) ) {
    WP_CLI::error( 'Menu: ' . $menu_id->get_error_message() );
}
foreach ( [ 'home', 'spaces', 'memberships', 'about', 'events', 'blog', 'contact' ] as $position => $slug ) {
    wp_update_nav_menu_item( $menu_id, 0, [
        'menu-item-object-id' => $pages[ $slug ],
        'menu-item-object'    => 'page',
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
        'menu-item-position'  => $position + 1,
    ] );
}
set_theme_mod( 'nav_menu_locations', [ 'menu-1' => $menu_id, 'menu-2' => $menu_id ] );

\Elementor\Plugin::$instance->files_manager->clear_cache();
flush_rewrite_rules();

WP_CLI::success( 'Seeded ' . count( $pages ) . ' pages, ' . ( count( document_names() ) - count( $pages ) ) . ' templates, the posts and the menu.' );
