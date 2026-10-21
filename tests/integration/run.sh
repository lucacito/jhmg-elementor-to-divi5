#!/usr/bin/env bash
# Runs an integration script inside the Docker WordPress (needs `docker-compose up`).
#   tests/integration/run.sh jobs-store.php
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
WP=$(cd "$ROOT" && docker-compose ps -q wordpress 2>/dev/null)
[ -n "$WP" ] || { echo "WordPress container is not running: scripts/docker/setup_wp.sh" >&2; exit 1; }
docker cp "$ROOT/tests/integration/$1" "$WP:/tmp/$1"
docker exec "$WP" bash -lc "wp eval-file /tmp/$1 --allow-root 2>&1 | grep -v -i 'deprecated'"
