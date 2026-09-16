#!/usr/bin/env bash
# Saves the demo site's database and uploads. Run after entering the Pro license key
# and after any polish in the Elementor editor. Replaces the previous snapshot.
set -euo pipefail
. "$(dirname "$0")/lib/common.sh"

mkdir -p "$DEMO_DIR/snapshots"
cd "$DEMO_DIR/snapshots"

step "Saving the database"
dc exec -T db mysqldump -uroot -proot --single-transaction --no-tablespaces wordpress 2>/dev/null > db.sql.tmp
mv db.sql.tmp db.sql

step "Saving uploads"
dc exec -T wordpress tar -C /var/www/html/wp-content -czf - uploads > uploads.tgz.tmp
mv uploads.tgz.tmp uploads.tgz

date -u '+%Y-%m-%dT%H:%M:%SZ' > taken-at
step "Snapshot saved to demo/snapshots/ ($(du -sh . | cut -f1))"
