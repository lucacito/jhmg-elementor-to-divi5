# Build stages after install. Sourced by build.sh.

seed_content() {
    wp --user="$ADMIN_USER" eval-file /demo/lib/seed.php
}

# The kit ZIP uploaded on camera in the Pro "Global Kit" step. Elementor 4.1.3's exporter
# accepts templates, content, settings and plugins; the documented "site-settings" is ignored.
export_kit() {
    rm -f "$DEMO_DIR/output/ferncourt-kit.zip"
    wp --user="$ADMIN_USER" elementor kit export /demo/output/ferncourt-kit.zip --include=settings
    # The ZIP is written inside the container; the host's view of the bind mount can lag a
    # moment behind, so give it a few seconds before calling the export broken.
    local attempt listing
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        listing=$(unzip -Z1 "$DEMO_DIR/output/ferncourt-kit.zip" 2>/dev/null || true)
        [[ $'\n'"$listing"$'\n' == *$'\n'site-settings.json$'\n'* ]] && return 0
        sleep 1
    done
    fail "demo/output/ferncourt-kit.zip has no site-settings.json"
}

# Divi Theme Builder header/footer for the "after" shots. Divi is active only for this step.
build_theme_builder() {
    wp theme activate Divi
    wp --user="$ADMIN_USER" eval-file /demo/lib/theme-builder.php
    wp theme activate hello-elementor
}
