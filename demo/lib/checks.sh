# Build checks run by verify.sh. Each check_* function calls fail() on a problem.

# expect_row <csv rows> <name> <version> <status>
expect_row() {
    local rows=$1 name=$2 version=$3 status=$4 line
    line=$(printf '%s\n' "$rows" | grep "^$name," || true)
    [ "$line" = "$name,$version,$status" ] \
        || fail "$name: expected '$name,$version,$status', got '${line:-not installed}'"
    echo "ok  $line"
}

# Check 1: every theme and plugin matches versions.env and is active or inactive as specified.
check_versions() {
    local themes plugins
    themes=$(wp theme list --fields=name,version,status --format=csv)
    plugins=$(wp plugin list --fields=name,version,status --format=csv)

    expect_row "$themes" hello-elementor "$HELLO_ELEMENTOR_VERSION" active
    expect_row "$themes" Divi "$DIVI_VERSION" inactive
    expect_row "$plugins" elementor "$ELEMENTOR_VERSION" active
    expect_row "$plugins" essential-addons-for-elementor-lite "$EAEL_VERSION" active
    expect_row "$plugins" header-footer-elementor "$HFE_VERSION" active
    expect_row "$plugins" elementskit-lite "$ELEMENTSKIT_VERSION" active
    expect_row "$plugins" premium-addons-for-elementor "$PREMIUM_ADDONS_VERSION" active
    expect_row "$plugins" contact-form-7 "$CF7_VERSION" active
    expect_row "$plugins" jhmg-converter-for-elementor-to-divi "$EDC_FREE_VERSION" active
    expect_row "$plugins" jhmg-converter-for-elementor-to-divi-pro "$EDC_PRO_VERSION" active
    expect_row "$plugins" ferncourt-demo "" must-use
}

# Check 2: every page returns 200 with no console errors and no PHP warning, notice or
# error logged. Runs every Playwright spec in demo/tests except the screenshots.
check_pages() {
    (cd "$DEMO_DIR/.." && npx playwright test -c demo/playwright.config.ts)
}
