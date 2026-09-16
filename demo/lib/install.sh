# WordPress, themes and plugins from versions.env. Sourced by build.sh.

install_core() {
    wp core install --url="$DEMO_URL" --title="Ferncourt Coworking" \
        --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" \
        --admin_email="$ADMIN_EMAIL" --skip-email
    wp option update blogdescription "Coworking for freelancers and small teams"
    wp rewrite structure '/%postname%/'

    local plugin
    for plugin in akismet hello; do
        if wp plugin is-installed "$plugin"; then
            wp plugin delete "$plugin"
        fi
    done
}

install_components() {
    wp theme install hello-elementor --version="$HELLO_ELEMENTOR_VERSION" --activate

    # Only Hello Elementor and Divi on the Themes screen shown on camera.
    # Divi is a bind mount of references/Divi: never delete it.
    local theme
    for theme in $(wp theme list --field=name); do
        case "$theme" in
            hello-elementor|Divi) ;;
            *) wp theme delete "$theme" ;;
        esac
    done

    wp plugin install "/demo/references/elementor.$ELEMENTOR_VERSION.zip" --activate
    wp plugin install "/demo/references/essential-addons-for-elementor-lite.$EAEL_VERSION.zip" --activate
    wp plugin install "/demo/references/header-footer-elementor.$HFE_VERSION.zip" --activate
    wp plugin install elementskit-lite --version="$ELEMENTSKIT_VERSION" --activate
    wp plugin install premium-addons-for-elementor --version="$PREMIUM_ADDONS_VERSION" --activate
    wp plugin install contact-form-7 --version="$CF7_VERSION" --activate
    wp plugin activate jhmg-converter-for-elementor-to-divi jhmg-converter-for-elementor-to-divi-pro

    # Activation queues onboarding redirects for the first admin visit.
    wp transient delete --all
}
