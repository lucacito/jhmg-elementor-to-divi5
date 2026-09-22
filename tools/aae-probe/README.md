# Animation Addons for Elementor probe

Ephemeral Docker stack for checking a widget's real settings shape and render
output against the actual "Animation Addons for Elementor" plugin
(wordpress.org: `animation-addons-for-elementor`, `references/animation-addons-for-elementor.*.zip`
in this repo) — ground truth for the `wcf--`/`aae--` converters under
`plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/`, instead
of guessing settings names from the plugin's PHP source alone.

Not the Ferncourt demo site (`../../demo/`) — this is throwaway, for research only.

## Use

```bash
cd tools/aae-probe
docker compose up -d db wordpress
docker compose run --rm cli wp core install \
  --url=http://localhost:8099 --title="AAE Probe" \
  --admin_user=admin --admin_password=probe --admin_email=probe@example.test --skip-email
docker compose run --rm cli wp plugin install /refs/elementor.4.1.3.zip --activate
docker compose run --rm cli wp plugin install /refs/animation-addons-for-elementor.4.2.2.zip --activate
docker compose run --rm cli wp eval-file /refs/../tools/aae-probe/enable-widgets.php
```

Every widget is disabled by default — `enable-widgets.php` flips the plugin's own
toggle option (`aaeaddon_save_widgets`); see its docblock for why.

Then either build a page through the real editor at
`http://localhost:8099/wp-admin/`, or seed one directly:

```bash
docker compose run --rm cli wp eval '
$data = [[ "id" => "probe1", "elType" => "widget", "widgetType" => "wcf--counter",
    "settings" => [ "ending_number" => 250, "title" => "Happy Clients" ], "elements" => [] ]];
$id = wp_insert_post([ "post_title" => "Probe", "post_type" => "page", "post_status" => "publish",
    "meta_input" => [ "_elementor_edit_mode" => "builder", "_elementor_version" => "3.32.0",
        "_elementor_data" => wp_slash( wp_json_encode( $data ) ) ] ]);
echo $id . "\n";
'
curl -s "http://localhost:8099/?page_id=<id>" | grep -i "fatal\|warning:\|notice:"
```

An empty grep result means it rendered clean. To pull a real Elementor export
matching what a client would send us, edit the page in the Elementor UI, then
Templates → Saved Templates → Export, or the page's own Export as Template.

## Teardown

```bash
docker compose down -v
```

Nothing here persists — every run starts from a fresh WordPress install.
