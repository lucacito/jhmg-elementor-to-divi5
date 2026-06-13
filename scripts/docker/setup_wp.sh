#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../../" && pwd)
COMPOSE_DIR=$ROOT
WP_URL=http://localhost:8000
ADMIN_USER=admin
ADMIN_PASS=admin
ADMIN_EMAIL=admin@example.test

echo "Starting Docker environment..."
docker-compose -f "$COMPOSE_DIR/docker-compose.yml" up -d --build

echo "Waiting for WordPress to be ready..."
# wait for 200 response
until curl -sSf "$WP_URL" >/dev/null; do
  printf '.'; sleep 1
done

echo "Installing WP-CLI inside container and configuring WordPress..."
# install wp-cli inside container
docker exec -i $(docker-compose -f "$COMPOSE_DIR/docker-compose.yml" ps -q wordpress) bash -lc "curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp"

WP=$(docker-compose -f "$COMPOSE_DIR/docker-compose.yml" ps -q wordpress)

# install core
docker exec -i $WP bash -lc "if ! wp core is-installed --allow-root; then wp core install --url=$WP_URL --title='Test' --admin_user=$ADMIN_USER --admin_password=$ADMIN_PASS --admin_email=$ADMIN_EMAIL --skip-email --allow-root; else echo 'WP core already installed'; fi"

# activate Divi theme (references/Divi must be a valid theme)
docker exec -i $WP bash -lc "if wp theme is-installed Divi --allow-root; then wp theme activate Divi --allow-root; else echo 'Divi theme not found in wp-content/themes/Divi'; fi"

# install and activate Elementor from bundled zip if present
if [ -f "$COMPOSE_DIR/references/elementor4.1.3.zip" ]; then
  docker exec -i $WP bash -lc "wp plugin install /tmp/elementor4.1.3.zip --activate --force --allow-root || (mkdir -p wp-content/plugins/elementor && unzip -o /tmp/elementor4.1.3.zip -d wp-content/plugins/ && wp plugin activate elementor --allow-root)"
fi

# activate our converter plugin
docker exec -i $WP bash -lc "wp plugin activate elementor-divi5-converter --allow-root || (echo 'Failed to activate plugin')"

# Create a test page and insert Elementor fixture data
TEST_PAGE_TITLE='Elementor Test Page'
PAGE_ID=$(docker exec -i $WP bash -lc "wp post create --post_type=page --post_status=publish --post_title=\"$TEST_PAGE_TITLE\" --porcelain --allow-root")

echo "Created page ID: $PAGE_ID"

# Insert fixture _elementor_data meta (using first fixture)
docker exec -i $WP bash -lc "wp post meta update $PAGE_ID _elementor_data \"\$(cat fixtures/elementor/simple-container.json | php -R 'echo json_encode(json_decode(file_get_contents("php://stdin")));')\" --allow-root"

# Run converter via a PHP file written into the container to avoid quoting issues.
# The here-doc uses a quoted delimiter so PHP variables are preserved inside the file.
docker exec -i $WP bash -lc "cat > /tmp/convert.php <<'PHP'
<?php
require_once ABSPATH . 'wp-content/plugins/elementor-divi5-converter/includes/helpers/class-autoloader.php';
\$json = get_post_meta($PAGE_ID, '_elementor_data', true);
\$payload = json_decode(\$json, true);
\$engine = new \\ElementorDivi5Converter\\Converter\\ConverterEngine();
\$converted = \\$engine->convert(\$payload);
\$exporter = new \\ElementorDivi5Converter\\Exporters\\DiviExporter();
\$exporter->save($PAGE_ID, \$converted);
echo "Converter run complete";
PHP
wp eval-file /tmp/convert.php --allow-root"


echo "Setup complete. WordPress running at $WP_URL"
echo "Admin: $WP_URL/wp-admin/ (user: $ADMIN_USER / pass: $ADMIN_PASS)"

echo "To run Playwright tests, set PLAYWRIGHT_BASE_URL=$WP_URL and run 'npm run test:browser'"
