# Build stages after install. Sourced by build.sh.

seed_content() {
    wp --user="$ADMIN_USER" eval-file /demo/lib/seed.php
}

# The kit ZIP uploaded on camera in the Pro "Global Kit" step.
export_kit() {
    rm -f "$DEMO_DIR/output/ferncourt-kit.zip"
    wp --user="$ADMIN_USER" elementor kit export /demo/output/ferncourt-kit.zip --include=site-settings
    unzip -l "$DEMO_DIR/output/ferncourt-kit.zip" | grep -q 'site-settings.json' \
        || fail "demo/output/ferncourt-kit.zip has no site-settings.json"
}

# Divi Theme Builder header/footer for the "after" shots. Divi is active only for this step.
build_theme_builder() {
    wp theme activate Divi
    wp --user="$ADMIN_USER" eval-file /demo/lib/theme-builder.php
    wp theme activate hello-elementor
}
