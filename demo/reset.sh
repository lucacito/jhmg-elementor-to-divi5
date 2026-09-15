#!/usr/bin/env bash
# Restores the latest snapshot and clears generated CSS. Run before every take.
set -euo pipefail
. "$(dirname "$0")/lib/common.sh"

[ -f "$DEMO_DIR/snapshots/db.sql" ] && [ -f "$DEMO_DIR/snapshots/uploads.tgz" ] \
    || fail "No snapshot in demo/snapshots/. Run demo/build.sh first."

step "Restoring the database from $(cat "$DEMO_DIR/snapshots/taken-at")"
dc exec -T db mysql -uroot -proot wordpress 2>/dev/null < "$DEMO_DIR/snapshots/db.sql"

step "Restoring uploads"
dc exec -T wordpress bash -c 'rm -rf /var/www/html/wp-content/uploads && tar -C /var/www/html/wp-content -xzf - && chown -R www-data:www-data /var/www/html/wp-content/uploads' \
    < "$DEMO_DIR/snapshots/uploads.tgz"

step "Clearing Elementor and Divi caches"
dc exec -T wordpress bash -c 'rm -rf /var/www/html/wp-content/et-cache/*'
wp elementor flush-css

step "Reset complete"
