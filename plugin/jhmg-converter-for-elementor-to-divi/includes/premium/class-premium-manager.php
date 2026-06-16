<?php

namespace ElementorDivi5Converter\Premium;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PremiumManager {

    private const OPTION_KEY = 'edc_premium_active';

    public static function is_active(): bool {
        return (bool) get_option( self::OPTION_KEY, false );
    }

    public static function activate(): void {
        update_option( self::OPTION_KEY, true, false );
    }

    public static function get_plan(): string {
        return 'free_preview';
    }
}
