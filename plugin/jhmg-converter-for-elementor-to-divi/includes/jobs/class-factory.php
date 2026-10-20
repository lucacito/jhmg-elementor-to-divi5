<?php
/** Builds the real (database-backed) collaborators once per request. */

namespace ElementorDivi5Converter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Factory {

    private static ?JobStore $store = null;
    private static ?PendingQuery $pending = null;
    private static ?JobService $service = null;

    public static function store(): JobStore {
        return self::$store ??= new WpdbJobStore();
    }

    public static function pending(): PendingQuery {
        return self::$pending ??= new PendingQuery();
    }

    public static function service(): JobService {
        return self::$service ??= new JobService( self::store(), self::pending() );
    }
}
