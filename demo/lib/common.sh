# Shared by every demo script. Source it; do not run it.

DEMO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

set -a
# shellcheck source=../versions.env
. "$DEMO_DIR/versions.env"
set +a

dc() {
    docker compose --project-directory "$DEMO_DIR" -f "$DEMO_DIR/docker-compose.yml" \
        --env-file "$DEMO_DIR/versions.env" "$@"
}

wp() {
    "$DEMO_DIR/wp" "$@"
}

step() {
    printf '\n==> %s\n' "$*"
}

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}
