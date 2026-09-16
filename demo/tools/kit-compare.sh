#!/usr/bin/env bash
# Imports a Kit Library kit, screenshots its pages as Elementor renders them, converts
# them to Divi drafts and screenshots those, then resets the site. Run from the repo root.
#
#   demo/tools/kit-compare.sh references/kits/ceramic-studio.zip [outdir]
#
# Output: <outdir>/kit-<slug>-elementor.png beside kit-<slug>-divi.png
# (default demo/output/kit-screenshots), plus demo/output/kit-pages.json and
# kit-converted.json. The kit's pages are found by their _elementor_data, minus the
# seven Ferncourt pages.
set -euo pipefail
. "$(dirname "$0")/../lib/common.sh"

KIT=${1:?kit zip path, e.g. references/kits/ceramic-studio.zip}
OUT=${2:-$DEMO_DIR/output/kit-screenshots}
NAME=$(basename "$KIT" .zip)

step "Importing $NAME"
wp --user="$ADMIN_USER" elementor kit import "/demo/references/kits/$NAME.zip" >/dev/null

step "Listing the kit's pages"
wp --user="$ADMIN_USER" eval '
$before = ["home","spaces","memberships","about","events","blog","contact","render-probe-core-widgets"];
$rows = [];
foreach ( get_posts( [ "post_type" => "page", "post_status" => "publish", "numberposts" => -1, "meta_key" => "_elementor_data" ] ) as $p ) {
    if ( in_array( $p->post_name, $before, true ) ) { continue; }
    $rows[] = [ "id" => $p->ID, "slug" => $p->post_name, "url" => get_permalink( $p ) ];
}
file_put_contents( "/demo/output/kit-pages.json", wp_json_encode( $rows, JSON_PRETTY_PRINT ) . "\n" );
echo count( $rows ), " pages\n";'

step "Screenshots of the originals"
(cd "$DEMO_DIR/.." && NODE_PATH=$PWD/node_modules node demo/tools/kit-shots.cjs elementor demo/output/kit-pages.json "$OUT")

step "Converting under Divi"
wp theme activate Divi >/dev/null
IDS=$(python3 -c "import json; print(' '.join(str(p['id']) for p in json.load(open('$DEMO_DIR/output/kit-pages.json'))))")
# shellcheck disable=SC2086
wp --user="$ADMIN_USER" eval-file /demo/lib/commit-kit.php $IDS

step "Screenshots of the drafts"
(cd "$DEMO_DIR/.." && NODE_PATH=$PWD/node_modules node demo/tools/kit-shots.cjs divi demo/output/kit-converted.json "$OUT")

step "Resetting the site"
"$DEMO_DIR/reset.sh" >/dev/null
step "Done: compare $OUT/kit-<slug>-elementor.png with kit-<slug>-divi.png"
