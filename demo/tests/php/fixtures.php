<?php

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\media_files;

const DEMO_TEST_NAV_MENU_ID = 5;

/** The seeded site, faked: every media file, one contact form, three categories, a fixed build time. */
function demo_test_context(): Context {
    $images = [];
    $id     = 100;
    foreach ( media_files( __DIR__ . '/../../content' ) as $file => $media ) {
        $images[ $file ] = [
            'id'  => $id++,
            'url' => 'https://ferncourt.test/wp-content/uploads/2026/09/' . $file,
            'alt' => $media['alt'],
        ];
    }

    return new Context( [
        'images'     => $images,
        'form_id'    => 7,
        'categories' => [ 'news' => 2, 'events' => 3, 'member-stories' => 4 ],
        'menu'       => 'primary',
        'home'       => 'https://ferncourt.test/',
        'now'        => gmmktime( 9, 0, 0, 9, 15, 2026 ),
    ] );
}

/** Puts the kit and the primary menu where the converter looks for them. Call after edc_test_reset_hooks(). */
function demo_test_seed_site(): void {
    update_option( 'elementor_active_kit', 900 );
    update_post_meta( 900, '_elementor_page_settings', require __DIR__ . '/../../content/kit.php' );
    $GLOBALS['__test_nav_menus']['primary'] = DEMO_TEST_NAV_MENU_ID;
}
