<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) {
        return dirname( $file ) . '/';
    }
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) {
        return 'file://' . dirname( $file ) . '/';
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback ) {
        return true;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
        // No-op stub for tests.
        return true;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) {
        return $value;
    }
}

// Simple in-memory post meta stubs for tests.
if ( ! function_exists( 'update_post_meta' ) ) {
    $GLOBALS['__test_postmeta'] = [];

    function update_post_meta( $post_id, $meta_key, $meta_value ) {
        $id = (int) $post_id;
        if ( ! isset( $GLOBALS['__test_postmeta'][ $id ] ) ) {
            $GLOBALS['__test_postmeta'][ $id ] = [];
        }
        $GLOBALS['__test_postmeta'][ $id ][ $meta_key ] = $meta_value;
        return true;
    }

    function add_post_meta( $post_id, $meta_key, $meta_value ) {
        return update_post_meta( $post_id, $meta_key, $meta_value );
    }

    function get_post_meta( $post_id, $meta_key = '', $single = false ) {
        $id = (int) $post_id;
        if ( $meta_key === '' ) {
            return $GLOBALS['__test_postmeta'][ $id ] ?? [];
        }
        $exists = isset( $GLOBALS['__test_postmeta'][ $id ] ) && array_key_exists( $meta_key, $GLOBALS['__test_postmeta'][ $id ] );
        if ( ! $exists ) {
            return $single ? '' : [];
        }
        $value = $GLOBALS['__test_postmeta'][ $id ][ $meta_key ];
        if ( $single ) {
            return $value;
        }
        return [ $value ];
    }

    function delete_post_meta( $post_id, $meta_key = '', $meta_value = '' ) {
        $id = (int) $post_id;
        if ( isset( $GLOBALS['__test_postmeta'][ $id ][ $meta_key ] ) ) {
            unset( $GLOBALS['__test_postmeta'][ $id ][ $meta_key ] );
            return true;
        }
        return false;
    }
}

// Minimal in-memory post storage for integration tests.
if ( ! function_exists( 'wp_insert_post' ) ) {
    $GLOBALS['__test_posts'] = [];
    $GLOBALS['__test_next_post_id'] = 1000;

    function wp_insert_post( $postarr ) {
        $id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : $GLOBALS['__test_next_post_id']++;
        $post = (object) array_merge( [ 'ID' => $id, 'post_type' => $postarr['post_type'] ?? 'post', 'post_content' => $postarr['post_content'] ?? '' ], $postarr );
        $GLOBALS['__test_posts'][ $id ] = $post;
        return $id;
    }

    function wp_update_post( $postarr ) {
        $id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
        if ( $id <= 0 || ! isset( $GLOBALS['__test_posts'][ $id ] ) ) {
            return 0;
        }

        $post = $GLOBALS['__test_posts'][ $id ];
        foreach ( $postarr as $key => $value ) {
            if ( $key === 'ID' ) {
                continue;
            }
            $post->{$key} = $value;
        }

        $GLOBALS['__test_posts'][ $id ] = $post;

        return $id;
    }

    function get_post( $post_id ) {
        $id = (int) $post_id;
        return $GLOBALS['__test_posts'][ $id ] ?? null;
    }

    function get_post_type( $post = null ) {
        if ( is_object( $post ) && isset( $post->post_type ) ) {
            return $post->post_type;
        }
        $id = (int) $post;
        if ( $id > 0 ) {
            $p = get_post( $id );
            return $p ? $p->post_type : null;
        }
        return null;
    }

    function wp_set_post_terms( $post_id, $terms, $taxonomy ) {
        // No-op for tests.
        return true;
    }
}

if ( ! function_exists( 'et_core_page_resource_get_the_ID' ) ) {
    function et_core_page_resource_get_the_ID() {
        return 0;
    }
}

if ( ! function_exists( 'et_is_test_env' ) ) {
    function et_is_test_env() {
        return true;
    }
}

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() {
        return false;
    }
}

if ( ! function_exists( 'is_customize_preview' ) ) {
    function is_customize_preview() {
        return false;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $cap = null ) {
        return true;
    }
}

if ( ! function_exists( 'is_singular' ) ) {
    function is_singular() {
        return false;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

if ( ! function_exists( 'wp_slash' ) ) {
    function wp_slash( $value ) {
        return addslashes( is_string( $value ) ? $value : '' );
    }
}

if ( ! function_exists( 'has_block' ) ) {
    function has_block( $block, $post_id = 0 ) {
        return false;
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $message;
        public function __construct( string $code = '', string $message = '' ) {
            $this->message = $message;
        }
        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ): string {
        return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    $GLOBALS['__test_transients'] = [];

    function set_transient( string $key, $value, int $expiration = 0 ): bool {
        $GLOBALS['__test_transients'][ $key ] = $value;
        return true;
    }

    function get_transient( string $key ) {
        return $GLOBALS['__test_transients'][ $key ] ?? false;
    }

    function delete_transient( string $key ): bool {
        unset( $GLOBALS['__test_transients'][ $key ] );
        return true;
    }
}

if ( ! function_exists( 'wp_get_theme' ) ) {
    function wp_get_theme( $template = null ) {
        return new class {
            public function get( $key ) {
                return 'Divi';
            }
        };
    }
}

if ( ! function_exists( 'get_template' ) ) {
    function get_template() {
        return 'Divi';
    }
}

// Divi path constants for including builder files from references during tests.
if ( ! defined( 'ET_BUILDER_DIR' ) ) {
    define( 'ET_BUILDER_DIR', __DIR__ . '/../references/Divi/includes/builder/' );
}

if ( ! defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
    define( 'ET_BUILDER_PLUGIN_DIR', __DIR__ . '/../references/Divi/' );
}

// Minimal Divi class stubs used by builder core during tests.
if ( ! class_exists( 'ET_Core_Portability' ) ) {
    class ET_Core_Portability {
        public static function doing_import() {
            return false;
        }
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

if ( file_exists( __DIR__ . '/../plugin/elementor-divi5-converter/elementor-divi5-converter.php' ) ) {
    require_once __DIR__ . '/../plugin/elementor-divi5-converter/elementor-divi5-converter.php';
}
