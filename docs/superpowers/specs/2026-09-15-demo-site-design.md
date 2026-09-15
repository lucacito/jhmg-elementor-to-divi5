# Demo Site for the Converter Video — Design

**Date:** 2026-09-15
**Repo:** `jhmg-elementor-to-divi5` (branch `demo-site`, based on `21f94c4`, release Pro 1.2.0)
**Status:** approved in brainstorm, awaiting spec review

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
| Port | **8040** | 8080 and the sibling converters' ports (8010, 8020, 8030) are in use locally. |
| Hostname | `http://localhost:8040` | A `.test` hostname would still need the port. |

## Environment

Everything lives in `demo/`, as its own Docker Compose project named `jhmg-demo`, with its
own volumes. The e2e stack in the root `docker-compose.yml` (port 8000) is not touched.

### Containers

- `wordpress` — official image, WordPress 6.6 or newer (Elementor 4.1.3 requires 6.6), PHP 8.2
- `db` — MySQL 8.0
- `cli` — official `wordpress:cli` image, sharing the WordPress volume

### Themes and plugins

| Component | Version | Source | State after build |
|---|---|---|---|
| Hello Elementor | pinned at first build | wordpress.org | active |
| Divi | 5.7.4 | `references/Divi` (mounted) | installed, inactive |
| Elementor | 4.1.3 | `references/elementor.4.1.3.zip` | active |
| Essential Addons for Elementor Lite | 6.6.7 | `references/essential-addons-for-elementor-lite.6.6.7.zip` | active |
| Header Footer Elementor | 2.8.8 | `references/header-footer-elementor.2.8.8.zip` | active |
| ElementsKit Lite | 4.0.5 | wordpress.org | active |
| Premium Addons for Elementor | 4.11.104 | wordpress.org | active |
| Contact Form 7 | pinned at first build | wordpress.org | active |
| JHMG converter (free) | working tree | `plugin/jhmg-converter-for-elementor-to-divi` (mounted) | active |
| JHMG converter (Pro) | working tree | `plugin/jhmg-converter-for-elementor-to-divi-pro` (mounted) | active |

Every version, including the WordPress image tag and the "pinned at first build" rows, is
recorded in `demo/versions.env` and read by the scripts. Nothing installs "latest".

Free availability was verified on 2026-09-15: Essential Addons Lite 6.6.7 contains every
EAEL widget the registry maps; ElementsKit Lite 4.0.5 contains all five mapped ElementsKit
widgets (heading, testimonial, video, dual button, accordion); Premium Addons 4.11.104
registers its blog widget as `premium-addon-blog`, matching the registry.

### Pro license

Unlike the e2e stack, `demo/` does **not** set `EDCP_API_BASE`, so Pro talks to the real
license server. The user enters a real key once before the final snapshot, so the
"activate your license" admin notice never appears on camera.

### Scripts

| Script | Does |
|---|---|
| `demo/build.sh` | Checks port 8040 is free, starts the stack, installs WordPress and every component above, seeds all content, exports the kit ZIP, creates the Divi Theme Builder header/footer, takes the first snapshot, then runs `verify.sh`. Safe to re-run: it tears down the `jhmg-demo` project's volumes and starts from nothing. Re-running replaces the snapshot, so re-enter the license key and run `snapshot.sh` afterwards. |
| `demo/snapshot.sh` | Saves the database (`mysqldump`) and the uploads directory (tarball) to `demo/snapshots/`. Run after the license key is entered and after any polish in the editor. |
| `demo/reset.sh` | Restores the latest snapshot, then clears Elementor's generated CSS and Divi's `et-cache`. Takes seconds. Run before every take. |
| `demo/verify.sh` | The build checks below. Ends by running `reset.sh`, so it never leaves converted drafts behind. |

`demo/snapshots/` and `demo/output/` are gitignored.

## Site content

### Brand

- **Name:** Ferncourt Coworking — one coworking space for freelancers and small teams. No
  real street address; the map shows a generic city-centre location.
- **Colors:** forest green, warm cream, terracotta — set as Elementor global colors
- **Fonts:** Fraunces (headings) and Inter (body), Google Fonts — set as Elementor global typography
- **Logo:** a text wordmark, authored as SVG

Global colors and fonts live in the Elementor kit, so both the free plugin's installed-kit
fallback and Pro's kit upload have real values to carry over.

### Pages

Seven published pages, built with classic Elementor flexbox containers and widgets — not
Elementor 4's atomic `e-*` elements. Classic widgets are what existing Elementor sites are
made of, and what the converter registry is built around.

| Page | Sections and widgets |
|---|---|
| **Home** | Hero: `eael-fancy-text` headline, `elementskit-dual-button` · Stats: 4 × `counter` · Amenities: 3 × `eael-info-box` · Spaces: `image-carousel` · Members: 3 × `eael-testimonial` · Close: `eael-cta-box` |
| **Spaces** | `eael-filterable-gallery` (hot desks / private offices / meeting rooms / lounge) · Amenities: 6 × `eael-flip-box` · Space types: `tabs` containing `image-box` |
| **Memberships** | 3 × `eael-pricing-table` (day pass, flex desk, private office) · Plan comparison: `eael-data-table` · FAQ: `eael-adv-accordion` |
| **About** | `elementskit-heading`, `text-editor`, `image` · Team: 4 × `eael-team-member` · Member mix: `eael-progress-bar` · `elementskit-testimonial` · Space tour: `elementskit-video` |
| **Events** | Next event: `eael-countdown` · `eael-adv-tabs` (this week / this month) · `eael-post-grid` of Events posts |
| **Blog** | `premium-addon-blog` listing |
| **Contact** | `eael-contact-form-7` · `google_maps` · Address and hours: `icon-list` · Visitor FAQ: `elementskit-accordion` · `social-icons` |

No Elementor Pro widgets are used (its `flip-box`, `price-table`, `countdown`, `form`,
`posts`, etc.). None of the registry's 12 placeholder-only slugs are used.

### Posts

Six standard (non-Elementor) WordPress posts, each with a featured image, across three
categories: News, Events, Member Stories. They feed the blog listing, post grid, and
events tabs. Because they carry no Elementor data, the batch conversion list shows only
the seven pages.

### Header and footer

- **On the Elementor site:** built in Header Footer Elementor. Header: `site-logo`,
  `navigation-menu`, "Book a tour" `button`. Footer: `navigation-menu`, `social-icons`, `copyright`.
- **For the "after" shots:** the build creates matching Divi Theme Builder global header and
  footer by passing the same Elementor data, typed `header` / `footer`, to Pro's
  `DiviThemeBuilderExporter`. These layouts only apply once Divi is active, so they are
  invisible during the Elementor tour.

A primary menu (Home, Spaces, Memberships, About, Events, Blog, Contact) is assigned for
both.

### Media

- About 30 photos from Unsplash and Pexels (both licenses allow commercial use without
  attribution), resized to 1600px wide, committed under `demo/content/images/` (target ≤ 8 MB).
- `demo/content/images/CREDITS.md` lists each file's source URL and license.
- The About page tour video is a self-hosted Pexels clip, not a YouTube embed.

### File layout

```
demo/
  docker-compose.yml
  versions.env
  build.sh  snapshot.sh  reset.sh  verify.sh
  README.md                  how to build, reset, and record
  lib/                       PHP run through WP-CLI: seeding, dry-run check, trial conversion, Theme Builder header/footer
  content/
    pages/*.json             one Elementor document per page
    templates/               HFE header and footer Elementor documents
    posts.json               the six posts
    kit.json                 global colors and typography
    logo.svg
    images/  CREDITS.md
  tests/                     Playwright specs for verify.sh
  playwright.config.ts       separate from the root config, so `npm run test:browser` is unaffected
  output/                    gitignored: screenshots, ferncourt-kit.zip
  snapshots/                 gitignored
```

## Recording flow

**Starting state after `reset.sh`:** Hello Elementor active; the seven Elementor pages
published; no Divi drafts; Divi Theme Builder header/footer present; Pro license active;
`demo/output/ferncourt-kit.zip` present (exported during the build with Elementor's own
WP-CLI kit export).

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
3. **Every page converts cleanly** — `ConversionPreflight` (dry run, writes nothing) over
   each page's `InstalledPostSource`. Fails if any plan item has a non-empty `error`,
   `unsupported`, or `report['warnings']`.
4. **Converted pages look right** — Divi activated, all seven pages converted with
   `ConversionCommitter`, each converted draft screenshotted next to its original into
   `demo/output/screenshots/`. Reviewed by eye before recording.
5. **Reset works** — after `reset.sh`: no Divi drafts exist, Hello Elementor is active, and Divi is inactive.

Converter bugs surfaced by checks 3 or 4 are raised with the user as their own task. They are not patched as part of this work.

## Risks to verify during the build

- **HFE under Divi.** HFE may still inject its header after the theme switch, producing two
  headers in step 2. If so, the mitigation is off camera: limit HFE's display rules so
  its templates only render with Hello Elementor.
- **ElementsKit video with a self-hosted file.** If the widget does not accept a
  self-hosted source, the About tour uses core `video` instead. ElementsKit is still shown
  through its other four widgets.
- **Elementor kit export over WP-CLI.** Elementor 4.1.3 ships `wp-cli.php` in both
  `app/modules/import-export` and `app/modules/import-export-customization`. The exact
  command name has not been confirmed. If CLI export fails, the build exports the kit
  through the admin UI with Playwright.

## Out of scope

- Fixing the HFE → Theme Builder bug (`docs/known-issues.md`)
- Showing unsupported widgets, the coverage report, or Undo on camera
- Elementor Pro and Elementor 4 atomic elements
- Hosting the demo publicly
- Scripting, recording, or editing the video itself
