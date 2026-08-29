<?php
/**
 * A place conversion input comes from.
 *
 * Implementations normalize their input to the item shape the converter
 * pipeline expects, so ConversionPreflight never needs to know whether the
 * work arrived as an upload or was read off a post already on this site.
 */

namespace ElementorDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ConversionSource {

    /**
     * @return array[] Items shaped
     *   ['title','post_type','post_name','template_type','elements','source_ref'].
     *   Returns [] when there is nothing to convert — never throws.
     */
    public function items(): array;
}
