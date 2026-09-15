#!/usr/bin/env bash
# Builds the Ferncourt demo site from nothing. Destroys any previous demo site and its snapshot's database.
set -euo pipefail
. "$(dirname "$0")/lib/common.sh"
. "$DEMO_DIR/lib/install.sh"
. "$DEMO_DIR/lib/stages.sh"

step "Removing any previous demo site"
dc --profile cli down --volumes --remove-orphans

step "Checking port $DEMO_PORT is free"
if lsof -nP -iTCP:"$DEMO_PORT" -sTCP:LISTEN >/dev/null 2>&1; then
    fail "Port $DEMO_PORT is in use by another process"
fi

step "Starting containers"
mkdir -p "$DEMO_DIR/output" "$DEMO_DIR/content"
dc up -d --wait db wordpress
until dc exec -T wordpress test -f /var/www/html/wp-config.php; do
    sleep 1
done

step "Installing WordPress"
install_core

step "Installing themes and plugins"
install_components

step "Seeding content"
seed_content

"$DEMO_DIR/verify.sh"
