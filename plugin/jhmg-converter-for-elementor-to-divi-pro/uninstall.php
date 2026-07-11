<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
delete_option( 'edcp_license_key' );
delete_option( 'edcp_license_state' );
delete_option( 'edcp_update_blocked' );
