<?php

namespace ElementorDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor stores gallery and carousel images as {id, url}. Divi's gallery
 * needs attachment IDs (gallery/module.json image.advanced.galleryIds). On the
 * site the page came from, the ID is right; on a site that imported a kit or
 * a JSON export, Elementor remaps IDs on import, and a URL that still points
 * at this site's uploads can be looked up. Anything else is not in this
 * media library and cannot be listed.
 */
final class AttachmentResolver {
    public static function idFor( int $id, string $url ): int {
        if ( ! function_exists( 'wp_attachment_is_image' ) ) {
            // Outside WordPress (unit tests) there is nothing to check against.
            return $id > 0 ? $id : 0;
        }
        if ( $id > 0 && wp_attachment_is_image( $id ) ) {
            return $id;
        }
        if ( $url !== '' && function_exists( 'attachment_url_to_postid' ) ) {
            $found = (int) attachment_url_to_postid( self::withoutSizeSuffix( $url ) );
            if ( $found > 0 ) {
                return $found;
            }
        }
        return 0;
    }

    /** @param array<int, array{id?: int|string, url?: string}> $items */
    public static function ids( array $items ): array {
        $ids = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $id = self::idFor( (int) ( $item['id'] ?? 0 ), is_string( $item['url'] ?? '' ) ? (string) ( $item['url'] ?? '' ) : '' );
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /** ".../photo-300x200.jpg" → ".../photo.jpg": attachment_url_to_postid() knows only the original. */
    private static function withoutSizeSuffix( string $url ): string {
        return (string) preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $url );
    }
}
