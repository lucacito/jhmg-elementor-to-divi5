# Ferncourt Coworking — converter demo site

A complete Elementor website built only from widgets the converter supports, for
recording the converter's demo video. Design: `docs/superpowers/specs/2026-09-15-demo-site-design.md`.

Run everything from the repository root.

## Build

```bash
demo/build.sh
```

Starts from nothing every time (about 10 minutes): Docker project `jhmg-demo` on
http://localhost:8040, WordPress, Hello Elementor, Divi (inactive), Elementor and its
add-ons at the versions in `demo/versions.env`, the seven pages, posts, header and footer,
the kit ZIP in `demo/output/ferncourt-kit.zip`, the Divi Theme Builder header and footer,
a first snapshot, then every build check.

Log in at http://localhost:8040/wp-admin/ with `ADMIN_USER` / `ADMIN_PASSWORD` from
`demo/versions.env`.

## Before recording

1. Enter the Pro license key: Tools → Elementor → Divi 5 → License.
2. Dismiss any add-on notices in wp-admin, and polish pages in the Elementor editor if you want.
3. `demo/snapshot.sh` — the snapshot is what every reset returns to.
4. `demo/verify.sh` — must end with `All checks passed`. It ends by resetting the site.

Event countdowns count from the build time, so rebuild if the video is recorded more
than about three weeks after the build.

## Between takes

```bash
demo/reset.sh
```

## Recording flow

1. Tour the Elementor site: Home, Spaces, Memberships.
2. Appearance → Themes → activate Divi.
3. Tools → Elementor → Divi 5 → pick Home → conversion report → Convert. Show the draft beside the original.
4. Pro → Global Kit → upload `demo/output/ferncourt-kit.zip`.
5. Pro → Convert → select the other six pages → convert.
6. Click through the converted drafts on the front end.

## Checks

`demo/verify.sh [versions|pages|conversions|converted|render|reset]` — no argument runs all six.
`render` seeds the probe page in `demo/content/probes/`, converts it and the seven pages
under Divi, and runs `demo/tests/render.spec.ts`: sizes, colours and text a viewer sees on
each draft, one test per Elementor widget. Add a probe file to cover a widget the site does
not use; add a test for every rendering bug you fix.
`vendor/bin/phpunit -c demo/phpunit.xml` runs the page conversions offline, without Docker.

## Rebuilding media

Photos, the logo and the tour video are committed. To change them:

```bash
node demo/tools/find-photos.mjs [slot.jpg]   # contact sheets in demo/tools/.candidates/
# edit demo/content/images/picks.json
node demo/tools/download-photos.mjs
node demo/tools/render-media.mjs
node demo/tools/check-media.mjs
```

## Trying another kit

Two free kits from Elementor's Kit Library are in `references/kits/` (core widgets only,
built with Elementor 3.7.2). To convert one and compare it with the original:

```bash
demo/wp --user=admin elementor kit import /demo/references/kits/ceramic-studio.zip
# list its pages, screenshot them, convert, screenshot the drafts:
NODE_PATH=$PWD/node_modules node demo/tools/kit-shots.cjs elementor demo/output/kit-pages.json demo/output/kit-screenshots
demo/wp theme activate Divi   # then commit conversions, then:
NODE_PATH=$PWD/node_modules node demo/tools/kit-shots.cjs divi demo/output/kit-converted.json demo/output/kit-screenshots
demo/reset.sh                 # back to Ferncourt
```

`kit-pages.json` is `[{ "id", "slug", "url" }]` and `kit-converted.json` is
`[{ "slug", "source_id", "draft_id" }]`; the comment at the top of `kit-shots.cjs` shows
how they were produced. Importing a kit replaces the global colours and fonts and hides
nothing by itself; set the Ferncourt HFE templates to draft if you want the kit's header.

## Commands

`demo/wp <args>` runs WP-CLI against the site, e.g. `demo/wp plugin list`.
`docker compose -f demo/docker-compose.yml --env-file demo/versions.env down` stops it.
