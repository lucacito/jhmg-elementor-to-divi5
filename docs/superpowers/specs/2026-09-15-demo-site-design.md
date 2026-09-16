# Demo Site for the Converter Video — Design

**Date:** 2026-09-15
**Repo:** `jhmg-elementor-to-divi5` (branch `demo-site`, based on `2e1d0a9`: free 3.0.1, Pro 1.2.0)
**Status:** approved in brainstorm; revised the same day after build research (see "Revisions")
**Plan:** `docs/superpowers/plans/2026-09-15-demo-site.md`

## Goal

A complete, realistic Elementor website running locally in Docker, built with the add-ons
the converter supports, that can be reset to a pristine state in seconds between takes of
a product demo video.

The site must feel like a real business, and every page on it must convert cleanly — the
video shows no placeholders and no unsupported-widget warnings.

## Decisions

| Question | Decision | Why |
|---|---|---|
| Content source | A made-up brand, authored by us | The existing kits are unfit: the EMI export is a real client's site, and the Agrow kit is licensed Envato content built mostly on widgets we don't convert (42 `elementskit-icon-box`, ElementsKit team/image accordion/blog posts/nav menu, MetForm). |
| Industry and brand | **Ferncourt Coworking** | A coworking space gives every showcase widget a natural job (plans, amenities, spaces gallery, events, team). A web search on 2026-09-15 found no coworking business by that name. |
| Elementor Pro | Not used; free plugins only | — |
| Header/footer conversion | **Not shown on camera** | HFE templates cannot currently reach the Divi Theme Builder. Recorded in `docs/known-issues.md` on `fix/correctness-pass-2026-08`. |
| Unsupported widgets on the site | **None** | The video shows clean conversions only. Coverage report and Undo are not demoed. |
| Converter bugs found while building | **Recorded, not fixed; the content avoids them** | User decision, 2026-09-15: the video comes first. Each bug goes into `docs/known-issues.md` for a later fix. |
| How the site is built | **Scripted, from files in the repo** | Rebuildable after Elementor or add-on updates; the user can still polish in the Elementor editor before the final snapshot. |
| Page source format | **PHP files returning Elementor element trees** | Pages reference IDs that only exist after seeding (attachments, the CF7 form, categories). PHP resolves them directly; JSON would need a placeholder-substitution layer. |
| Port | **8040** | 8080 and the sibling converters' ports (8010, 8020, 8030) are in use locally. |
| Hostname | `http://localhost:8040` | A `.test` hostname would still need the port. |

## Environment

Everything lives in `demo/`, as its own Docker Compose project named `jhmg-demo`, with its
own volumes. The e2e stack in the root `docker-compose.yml` (port 8000) is not touched.

### Containers

| Service | Image |
|---|---|
| `wordpress` | `wordpress:6.9-php8.2-apache` |
| `db` | `mysql:8.0` |
| `cli` | `wordpress:cli-2.12.0-php8.2`, sharing the WordPress volume and mounts |

### Themes and plugins

| Component | Version | Source | State after build |
|---|---|---|---|
| Hello Elementor | 3.5.1 | wordpress.org | active |
| Divi | 5.7.4 | `references/Divi` (mounted) | installed, inactive |
| Elementor | 4.1.3 | `references/elementor.4.1.3.zip` | active |
| Essential Addons for Elementor Lite | 6.6.7 | `references/essential-addons-for-elementor-lite.6.6.7.zip` | active |
| Header Footer Elementor | 2.8.8 | `references/header-footer-elementor.2.8.8.zip` | active |
| ElementsKit Lite | 4.0.5 | wordpress.org | active |
| Premium Addons for Elementor | 4.11.104 | wordpress.org | active |
| Contact Form 7 | 6.1.7 | wordpress.org | active |
| JHMG converter (free) | working tree (3.0.1) | `plugin/jhmg-converter-for-elementor-to-divi` (mounted) | active |
| JHMG converter (Pro) | working tree (1.2.0) | `plugin/jhmg-converter-for-elementor-to-divi-pro` (mounted) | active |

Every version, including image tags, is recorded in `demo/versions.env` and read by the
scripts. Nothing installs "latest".

Free availability was verified on 2026-09-15: Essential Addons Lite 6.6.7 contains every
EAEL widget the registry maps; ElementsKit Lite 4.0.5 contains all five mapped ElementsKit
widgets (heading, testimonial, video, dual button, accordion); Premium Addons 4.11.104
registers its blog widget as `premium-addon-blog`, matching the registry. EAEL, ElementsKit
and Premium Addons enable all their widgets by default, so no module settings are seeded.

### Pro license

Unlike the e2e stack, `demo/` does **not** set `EDCP_API_BASE`, so Pro talks to the real
license server. The user enters a real key once before the final snapshot, so the
"activate your license" admin notice never appears on camera. Nothing in the build needs
the key: Pro registers its Theme Builder exporter and batch limit without checking it.

### Must-use plugin

`demo/mu-plugins/ferncourt-demo.php` is mounted into `wp-content/mu-plugins/`. It leaves
Header Footer Elementor out of the active plugins list on any request made while the active
theme is not Hello Elementor (only the option read is filtered; HFE stays active in the
database). Found during the build: with HFE loaded under Divi, HFE's theme compatibility and
Divi's Theme Builder both take over `get_header`, and every page dies with a fatal error in
`wp_head` (recorded in `docs/known-issues.md`). It also means switching to Divi on camera never
shows HFE's header on top of Divi's Theme Builder header.

### Scripts

| Script | Does |
|---|---|
| `demo/build.sh` | Tears down the `jhmg-demo` project's volumes, checks port 8040 is free, starts the stack, installs WordPress and every component above, seeds all content, exports the kit ZIP, creates the Divi Theme Builder header/footer, takes the first snapshot, then runs `verify.sh`. Re-running replaces the snapshot, so re-enter the license key and run `snapshot.sh` afterwards. |
| `demo/snapshot.sh` | Saves the database (`mysqldump`) and the uploads directory (tarball) to `demo/snapshots/`. Run after the license key is entered and after any polish in the editor. |
| `demo/reset.sh` | Restores the latest snapshot, then clears Elementor's generated CSS and Divi's `et-cache`. Takes seconds. Run before every take. |
| `demo/verify.sh` | The build checks below. Ends by running `reset.sh`, so it never leaves converted drafts behind. |

`demo/snapshots/` and `demo/output/` are gitignored.

## Site content

### Brand

- **Name:** Ferncourt Coworking — one coworking space for freelancers and small teams. No
  real street address; the map shows a generic downtown location.
- **Colors:** forest green `#2F4F3A`, warm cream `#F6F1E7`, terracotta `#C8643B`, ink
  `#1F2421` — Elementor system colors primary, secondary, accent, text
- **Fonts:** Fraunces (headings) and Inter (body), Google Fonts — Elementor system typography
- **Logo:** a text wordmark with a fern mark, authored as SVG and rendered to PNG for the
  media library (WordPress does not accept SVG uploads, and the PNG keeps the real fonts)

Global colors and fonts live in the Elementor kit, so both the free plugin's installed-kit
fallback and Pro's kit upload have real values to carry over. Widgets reference them through
`__globals__` (`globals/colors?id=primary`), which is how real Elementor sites store them.

### Pages

Seven published pages, built with classic Elementor flexbox containers and widgets — not
Elementor 4's atomic `e-*` elements. Classic widgets are what existing Elementor sites are
made of, and what the converter registry is built around.

| Page | Sections and widgets |
|---|---|
| **Home** | Hero: `eael-fancy-text` headline, `elementskit-dual-button` · Stats: 4 × `counter` · Amenities: 3 × `eael-info-box` · Spaces: `image-carousel` · Members: 3 × `eael-testimonial` · Close: `eael-cta-box` |
| **Spaces** | `eael-filterable-gallery` (hot desks / private offices / meeting rooms / lounge) · Amenities: 6 × `eael-flip-box` · Space types: `tabs` beside an `image-box` |
| **Memberships** | 3 × `eael-pricing-table` (day pass, flex desk, private office) · Plan comparison: `eael-data-table` · FAQ: `eael-adv-accordion` |
| **About** | `elementskit-heading`, `text-editor`, `image` · Team: 4 × `eael-team-member` · Member mix: 3 × `counter` · `elementskit-testimonial` · Space tour: `elementskit-video` |
| **Events** | Next event: `eael-countdown` · `eael-adv-tabs` (this week / this month) · `eael-post-grid` of the latest posts |
| **Blog** | `premium-addon-blog` listing |
| **Contact** | `eael-contact-form-7` · `google_maps` · Address and hours: `icon-list` · Visitor FAQ: `elementskit-accordion` · `social-icons` |

No Elementor Pro widgets are used (its `flip-box`, `price-table`, `countdown`, `form`,
`posts`, etc.). None of the registry's 12 placeholder-only slugs are used. Every slug above
was checked against `ConverterRegistry` on 2026-09-15.

Each page file also lists the strings that must survive conversion (headlines, prices, team
names, table cells, counter values, the event date) for build check 6.

### Converter bugs the content avoids

Found 2026-09-15 by reproducing or reading the converters while writing the pages. All are
recorded in `docs/known-issues.md` for a later fix; none is fixed in this work.

- **EAEL progress bar converts its percentage to `Array`** (or `0` when left at the default).
  Reproduced. About shows its member mix with core `counter` widgets instead.
- **EAEL Call to Action drops its body text.** Home's CTA puts its sentence in the subtitle,
  which both EAEL and the converter show.
- **EAEL filterable gallery loses its filter buttons, item names and captions.** The images
  carry over, so Spaces keeps the gallery: the converted page still shows every photo.
- **EAEL post grid ignores its category filter.** The Events grid lists the latest posts
  instead of only Events posts, so both versions match.
- **EAEL pricing table drops its subtitle.** Found by the offline harness. The Memberships
  pricing tables have no subtitle.
- **ElementsKit heading drops its subtitle.** Found by the offline harness. About's heading
  has no subtitle.

By design, not bugs: fancy text converts to a static heading with its first rotating phrase,
and the Google Maps widget converts to an embed with the address URL-encoded.

### Posts

Six standard (non-Elementor) WordPress posts, each with a featured image, across three
categories: News, Events, Member Stories. They feed the blog listing and the events post
grid. Because they carry no Elementor data, the batch conversion list shows only the seven
pages.

### Header and footer

- **On the Elementor site:** built in Header Footer Elementor. Header: `site-logo`,
  `navigation-menu`, "Book a tour" `button`. Footer: `navigation-menu`, `social-icons`,
  `copyright`. Display rule: entire website, all users.
- **For the "after" shots:** the build creates matching Divi Theme Builder global header and
  footer by converting the same Elementor data and passing it to Pro's
  `DiviThemeBuilderExporter::saveHeader()` / `saveFooter()`. Divi is activated for that step,
  because the exporter calls Divi's own Theme Builder functions, then Hello Elementor is
  reactivated. The layouts only apply once Divi is active, so they are invisible during the
  Elementor tour.

A primary menu (Home, Spaces, Memberships, About, Events, Blog, Contact) is assigned for
both.

### Media

- **Photos:** 30 free Unsplash photos, found through Unsplash's search and filtered to
  non-premium results (`premium` and `plus` both false), so every one is under the Unsplash
  License (commercial use, no attribution required). Cropped to 1600×1067 (spaces) or
  800×800 (portraits) and committed under `demo/content/images/` (target ≤ 8 MB). Pexels was
  ruled out: it rejects scripted downloads.
- `demo/content/images/manifest.json` records each file's Unsplash page URL, photographer
  and license; `CREDITS.md` is generated from it.
- **Tour video:** a 20-second 1280×720 WebM slideshow of our own photos, recorded once with
  Playwright's built-in video capture and committed as `demo/content/video/tour.webm` with a
  poster frame. No stock video, no YouTube embed, and no extra ffmpeg image.

### File layout

```
demo/
  docker-compose.yml  versions.env  wp
  build.sh  snapshot.sh  reset.sh  verify.sh
  README.md                  how to build, reset, and record
  mu-plugins/ferncourt-demo.php
  lib/
    common.sh  install.sh  stages.sh  checks.sh
    elementor.php            Context, element builders, document loader (pure PHP)
    conversion-checks.php    checks 3 and 6 for one document (pure PHP)
    site-context.php         Context from the seeded site
    seed.php  theme-builder.php  check-conversions.php  commit-conversions.php  starting-state.php
  content/
    pages/*.php  templates/header.php  templates/footer.php
    posts.php  kit.php  logo.svg  logo.png
    images/  shots.json  picks.json  manifest.json  CREDITS.md
    video/tour.webm  video/tour-poster.jpg
  tools/                     one-off media authoring, not run by build.sh
    find-photos.mjs  download-photos.mjs  render-media.mjs  tour.html  check-media.mjs
  phpunit.xml  tests/php/    offline harness: every document through the real converter
  playwright.config.ts  tests/*.spec.ts
  output/                    gitignored: screenshots, converted.json, ferncourt-kit.zip
  snapshots/                 gitignored
```

## Recording flow

**Starting state after `reset.sh`:** Hello Elementor active; the seven Elementor pages
published; no Divi drafts; Divi Theme Builder header/footer present; Pro license active;
`demo/output/ferncourt-kit.zip` present (exported during the build with
`wp elementor kit export`).

1. **Tour the Elementor site** — Home, Spaces, Memberships. Optionally open Home in the
   Elementor editor to show real add-on widgets.
2. **Switch to Divi** — Appearance → Themes. The converter screens require Divi.
3. **Free plugin** — Tools → Elementor → Divi 5 → pick Home → conversion report → Convert.
   This creates a new Divi draft; the Elementor original is untouched. Show both side by side.
4. **Pro global styles** — Global Kit tab → upload `ferncourt-kit.zip` → colors and fonts
   listed. On this site the free plugin already reads the installed kit, so Home does not
   change; this step shows the case of bringing a kit from another site.
5. **Pro batch** — Convert tab → select the other six pages → convert in one run → results.
6. **Wrap-up** — click through the converted drafts on the front end.

## Build checks (`verify.sh`)

The site is ready to record only when all of these pass:

1. **Versions** — every theme and plugin matches `versions.env` and is active or inactive as specified.
2. **Pages load** — all seven pages return 200 with no browser console errors, and no PHP
   warning, notice or error is logged while they load (Playwright). Also confirms only one
   header renders under each theme.
3. **Every page converts cleanly** — `ConversionPreflight::runUnlimited()` (dry run, writes
   nothing) over an `InstalledPostSource` of each page and template. Fails if any plan item
   has a non-empty `error`, `unsupported`, or `report['warnings']`.
4. **Converted pages look right** — Divi activated, all seven pages converted with
   `ConversionCommitter`, each converted draft screenshotted next to its original into
   `demo/output/screenshots/`. Reviewed by eye before recording.
5. **Reset works** — a dirtied site (a converted draft, Divi active) returns to the starting
   state after `reset.sh`.
6. **Content survived** — every string a page file lists as must-survive appears in that
   page's converted blocks from check 3. This catches a widget that converts "cleanly"
   while silently dropping its text, the class of bug fixed in 3.0.1.

Checks 3 and 6 also run offline, without Docker: `vendor/bin/phpunit -c demo/phpunit.xml`
feeds every page and template through the real converter.

A converter bug surfaced by checks 3, 4 or 6 is recorded in `docs/known-issues.md` and the
page content is changed to avoid it. It is not fixed as part of this work.

## Risks to verify during the build

- **Pro Theme Builder templates.** Pro 1.2.0 saves the header and footer as two default
  templates and leaves the body area hidden (recorded in `docs/known-issues.md`). The build
  merges them into one template with the body enabled; the theme-switch check proves the page
  body renders under Divi.
- **Kit export.** If `wp elementor kit export` fails on this site, the build stops with the
  command's output rather than continuing without a kit ZIP.
- **Divi activation side effects.** Activating Divi for the Theme Builder step may create
  Divi's default options or onboarding redirects. The snapshot is taken after Hello Elementor
  is reactivated, and check 2 catches anything visible.
- **Add-on PHP warnings.** An add-on may log warnings under PHP 8.2. Check 2 fails on them;
  whether to accept one is the user's call.

## Revisions

Changes since the brainstorm-approved version, all on 2026-09-15:

- Base moved to `2e1d0a9` (free 3.0.1), which fixed the ElementsKit video converter — that risk is gone.
- Exact pins for every "pinned at first build" row and for the container images.
- Pages are PHP files, not JSON, with an offline PHPUnit harness.
- Photos come from Unsplash's search; the tour video is recorded from our photos with Playwright; the logo is rendered to PNG.
- HFE double-header risk resolved with a must-use plugin, confirmed by a theme-switch check.
- Kit export command confirmed; the Playwright admin-UI fallback was dropped.
- Build check 6 (content survived) added.
- Four converter bugs found and recorded in `docs/known-issues.md`. By the user's decision they are not fixed now: About uses counters instead of progress bars, Home's CTA text sits in its subtitle, and the Events grid shows the latest posts.

## Out of scope

- Fixing any converter bug, including those found while building (`docs/known-issues.md`)
- Showing unsupported widgets, the coverage report, or Undo on camera
- Elementor Pro and Elementor 4 atomic elements
- Hosting the demo publicly
- Scripting, recording, or editing the video itself
