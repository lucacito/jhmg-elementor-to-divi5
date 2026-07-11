<?php
/**
 * Fires on plugin deletion (not deactivation).
 * Removes all plugin options.
 *
 * Kit data (`edc_kit_globals` option + the uploaded kit ZIP) is owned by the
 * Pro add-on — Pro's GlobalsStore is what writes it — so its cleanup lives in
 * the Pro plugin's own uninstall.php, not here. See that file for the
 * path-confined delete of the kit ZIP.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove premium activation flag. Defensive: harmless if Pro was never
// installed, and Pro's own uninstall.php also clears its edcp_* options.
delete_option( 'edc_premium_active' );
