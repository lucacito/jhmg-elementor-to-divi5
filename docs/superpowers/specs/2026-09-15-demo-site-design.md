# Demo Site for the Converter Video — Design

**Date:** 2026-09-15
**Repo:** `jhmg-elementor-to-divi5` (branch `demo-site`, based on `2e1d0a9`: free 3.0.1, Pro 1.2.0)
**Status:** approved in brainstorm; revised the same day after build research (see "Revisions")

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

`demo/mu-plugins/ferncourt-demo.php` is mounted into `wp-content/mu-plugins/`. It returns
`false` from HFE's `enable_hfe_render_header` and `enable_hfe_render_footer` filters
whenever the active theme is not Hello Elementor, so switching to Divi on camera never
shows HFE's header on top of Divi's Theme Builder header.

### Scripts

| Script | Does |
|---|---|
| `demo/build.sh` | Checks port 8040 is free, tears down the `jhmg-demo` project's volumes, starts the stack, installs WordPress and every component above, seeds all content, exports the kit ZIP, creates the Divi Theme Builder header/footer, takes the first snapshot, then runs `verify.sh`. Re-running replaces the snapshot, so re-enter the license key and run `snapshot.sh` afterwards. |
| `demo/snapshot.sh` | Saves the database (`mysqldump`) and the uploads directory (tarball) to `demo/snapshots/`. Run after the license key is entered and after any polish in the editor. |
| `demo/reset.sh` | Restores the latest snapshot, then clears Elementor's generated CSS and Divi's `et-cache`. Takes seconds. Run before every take. |
| `demo/verify.sh` | The build checks below. Ends by running `reset.sh`, so it never leaves converted drafts behind. |

`demo/snapshots/` and `demo/output/` are gitignored.

## Site content

### Brand

- **Name:** Ferncourt Coworking — one coworking space for freelancers and small teams. No
  real street address; the map shows a generic city-centre location.
- **Colors:** forest green `#2F4F3A`, warm cream `#F6F1E7`, terracotta `#C8643B`, ink
  `#1F2421` — Elementor system colors primary, secondary, accent, text
- **Fonts:** Fraunces (headings) and Inter (body), Google Fonts — Elementor system typography
- **Logo:** a text wordmark, authored as SVG

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
| **About** | `elementskit-heading`, `text-editor`, `image` · Team: 4 × `eael-team-member` · Member mix: `eael-progress-bar` · `elementskit-testimonial` · Space tour: `elementskit-video` |
| **Events** | Next event: `eael-countdown` · `eael-adv-tabs` (this week / this month) · `eael-post-grid` of Events posts |
| **Blog** | `premium-addon-blog` listing |
| **Contact** | `eael-contact-form-7` · `google_maps` · Address and hours: `icon-list` · Visitor FAQ: `elementskit-accordion` · `social-icons` |

No Elementor Pro widgets are used (its `flip-box`, `price-table`, `countdown`, `form`,
`posts`, etc.). None of the registry's 12 placeholder-only slugs are used. Every slug above
was checked against `ConverterRegistry` on 2026-09-15.

Each page file also lists the strings that must survive conversion (headlines, prices, team
names, table cells, the progress-bar percentages) for build check 6.

### Posts

Six standard (non-Elementor) WordPress posts, each with a featured image, across three
categories: News, Events, Member Stories. They feed the blog listing, post grid, and
events tabs. Because they carry no Elementor data, the batch conversion list shows only
the seven pages.

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

- **Photos:** about 30 free Unsplash photos, found through Unsplash's search and filtered to
  non-premium results (`premium` and `plus` both false), so every one is under the Unsplash
  License (commercial use, no attribution required). Resized to 1600px wide and committed
  under `demo/content/images/` (target ≤ 8 MB). Pexels was ruled out: it rejects scripted
  downloads.
- `demo/content/images/manifest.json` records each file's Unsplash page URL, photographer
  and license; `CREDITS.md` is generated from it.
- **Tour video:** a 20-second 1280×720 WebM slideshow of our own photos, recorded once with
  Playwright's built-in video capture and committed as `demo/content/video/tour.webm` with a
  poster frame. No stock video, no YouTube embed, and no extra ffmpeg image.

### File layout

```
demo/
  docker-compose.yml
  versions.env
  build.sh  snapshot.sh  reset.sh  verify.sh
  README.md                  how to build, reset, and record
  mu-plugins/ferncourt-demo.php
  lib/                       PHP run through `wp eval-file`
    elementor.php            element-tree builders (container, widget, image, link, global color)
    seed.php                 media, categories, posts, CF7 form, menu, kit, pages, HFE templates
    theme-builder.php        Divi Theme Builder header/footer
    check-conversions.php    build checks 3 and 6
    commit-conversions.php   build check 4
  content/
    pages/*.php              one Elementor page per file
    templates/header.php  templates/footer.php
    posts.php  kit.php  logo.svg
    images/  manifest.json  CREDITS.md
    video/tour.webm  video/tour-poster.jpg
  tools/                     one-off media authoring, not run by build.sh
    fetch-images.mjs  record-tour.mjs  tour.html
  tests/                     Playwright specs for verify.sh
  playwright.config.ts       separate from the root config, so `npm run test:browser` is unaffected
  output/                    gitignored: screenshots, ferncourt-kit.zip
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
2. **Pages load** — all seven pages return 200 with no PHP notices, warnings, or browser console errors (Playwright).
3. **Every page converts cleanly** — `ConversionPreflight::runUnlimited()` (dry run, writes
   nothing) over an `InstalledPostSource` of the seven pages. Fails if any plan item has a
   non-empty `error`, `unsupported`, or `report['warnings']`.
4. **Converted pages look right** — Divi activated, all seven pages converted with
   `ConversionCommitter`, each converted draft screenshotted next to its original into
   `demo/output/screenshots/`. Reviewed by eye before recording.
5. **Reset works** — after `reset.sh`: no Divi drafts exist, Hello Elementor is active, and Divi is inactive.
6. **Content survived** — every string a page file lists as must-survive appears in that
   page's converted `content` from check 3. This catches a widget that converts "cleanly"
   while silently dropping its text, the class of bug fixed in 3.0.1.

Converter bugs surfaced by checks 3, 4 or 6 are raised with the user as their own task. They are not patched as part of this work.

## Known blocker

**EAEL progress bar converts to "Array" or "0".** Found 2026-09-15 and reproduced with the
converter: EAEL stores `progress_bar_value` as a slider (`{unit, size}`), and
`EaelProgressBarConverter` casts it straight to a string. A saved value becomes `"Array"`
(with a PHP warning); an unsaved default becomes `"0"` although EAEL renders 50%. Recorded
in `docs/known-issues.md`. The About page keeps its progress bar, so check 6 fails until
the converter is fixed; recording waits for that fix.

## Risks to verify during the build

- **HFE filters.** If HFE 2.8.8 ignores `enable_hfe_render_header` / `enable_hfe_render_footer`
  under Divi, the must-use plugin instead removes HFE's display rules while Divi is active.
- **Kit export.** If `wp elementor kit export` fails on this site, the build stops with the
  command's output rather than continuing without a kit ZIP.
- **Divi activation side effects.** Activating Divi for the Theme Builder step may create
  Divi's default options or onboarding redirects. The snapshot is taken after Hello Elementor
  is reactivated, and check 2 catches anything visible.

## Revisions

Changes since the brainstorm-approved version, all from research on 2026-09-15:

- Base moved to `2e1d0a9` (free 3.0.1), which fixed the ElementsKit video converter — that risk is gone.
- Exact pins for every "pinned at first build" row and for the container images.
- Pages are PHP files, not JSON.
- Photos come from Unsplash's search; the tour video is recorded from our photos with Playwright.
- HFE double-header risk resolved with a must-use plugin.
- Kit export command confirmed; the Playwright admin-UI fallback was dropped.
- Build check 6 (content survived) added; the progress bar blocker recorded.

## Out of scope

- Fixing the HFE → Theme Builder bug or the progress bar bug (`docs/known-issues.md`)
- Showing unsupported widgets, the coverage report, or Undo on camera
- Elementor Pro and Elementor 4 atomic elements
- Hosting the demo publicly
- Scripting, recording, or editing the video itself
