# Converted pages render correctly in Divi 5 — design (free 3.0.2, Pro 1.2.1)

**Date:** 2026-09-15
**Branch:** `fix/divi-rendering-2026-09` (from `demo-site`; merges into `fix/correctness-pass-2026-08`, then `main`)
**Status:** approved in brainstorm (branching, heading mapping, counter affixes, gallery captions), awaiting spec review
**Source of findings:** `docs/known-issues.md`; `demo/output/screenshots/` and `demo/output/kit-screenshots/` on the `demo-site` branch

## Goal

A page that the converter reports as clean must look right when the Divi 5.7.4 draft is opened. Today every existing check stops at the converter's own output, so a handler can write a value to an attribute path Divi never reads and still pass. This work has two halves that ship together:

1. A verification layer that fails when the converter writes something Divi 5.7.4 will not render: a schema test against Divi's generated module definitions, and a render check in the demo stack that looks at the page the way a viewer does.
2. The rendering fixes that layer exposes, in the priority order of the brief: structure and core widgets first, then the add-on widgets that render empty, then converters that drop content, then the Pro Theme Builder and Header Footer Elementor (HFE) issues.

## Non-goals

- No new widget support. Every change is to a converter that already exists.
- Elementor Pro widgets (price table, flip box, countdown, nav menu, form) are not re-verified against Elementor Pro's sources; their converters are only touched where they share code with the fixes below.
- No release. `RELEASE.md` needs the user's SVN password; the branch stops at "ready to release".
- The sibling project `Divi 5 Deterministic Validator` and the `mcp__ai-editor-divi5__validate_layout` tool were examined and do not fit: they validate block nesting and the presence of `innerContent`, not attribute paths against module definitions, and they target Divi 5.8.0 exports. Nothing is reused from them.

## Root causes

All grounded in Divi 5.7.4 under `references/Divi/includes/builder-5/`: `visual-builder/packages/module-library/src/components/<module>/module.json` (VB) and `server/Packages/ModuleLibrary/<Module>/<Module>Module.php` (front-end renderer).

| Symptom | Cause |
|---|---|
| Column background image renders as a 12 px strip | `ColumnConverter` moves the background onto an empty `divi/group` inside the column. Divi 5 rows are `display:flex` (`.et_flex_row`, default `align-items: stretch`), so the column itself already fills the row's min-height; the group does not. `divi/column` accepts `module.decoration.background`. |
| Heading with `header_size: span` (also `p`, `div`) loses its typography | `divi/heading` renders `headingLevel` as the tag (`ModuleElements.php:1009`) but its style selector lists only `.et_pb_heading_container h1 … h6`. |
| Button renders as Divi's outline button, text in Divi's default blue | Elementor gives every button a background of the global accent colour (`button-trait.php`, `Group_Control_Background` default `Global_Colors::COLOR_ACCENT`) and white text, 15 px, 12/24 px padding, 3 px radius (`assets/css/frontend.css:1998`), and the kit's *Theme Style → Buttons* can override all of it. The converter carries only values the widget sets explicitly. |
| Counter shows `233%` / `58%%` | `number.innerContent` must be numeric; Divi appends its own sign when `number.advanced.enablePercentSign` is on, and it defaults to on (`number-counter/module.json`). Counter `title_color` is never mapped. |
| Image carousel renders 40 px thumbnails | Emitted as a `divi/text` of inline `<img style="max-height:60px">`. |
| Filterable gallery shows the whole media library | `divi/gallery` reads `image.advanced.galleryIds` (attachment IDs, `GalleryModule.php:754`); the handler writes `galleryGrid.innerContent.images[].src`, which nothing reads, and Divi's fallback query lists every attachment. |
| Map is blank | To be reproduced on the probe page before fixing (see Render check). Candidates: the iframe stripped on save, or the module's entity handling (`CodeModule.php:293`). |
| Social icons all Facebook | Elementor stores the icon under `social_icon` as `{value, library}`; `resolveNetwork()` reads it as a string, finds nothing, and falls back to `facebook`. |
| Menu on a white bar | `divi/menu` default render attributes set `module.decoration.background.desktop.value.color = #ffffff` (`menu/module-default-render-attributes.json`). |
| Info box, flip box render nothing | Blurb reads `title.innerContent.desktop.value.text` (headingLink), `content.innerContent`, `imageIcon.innerContent.desktop.value.{useIcon,icon{type,unicode,weight},src}`. The handlers write a plain string title, `module.advanced.text`, and a FontAwesome class as the icon. `IconBoxConverter` already writes the right shape. |
| Pricing table empty | Item reads `title.innerContent`, `subtitle.innerContent`, `currencyFrequency.innerContent.{currency,per}`, `price.innerContent`, `content.innerContent` (one feature per line, leading `-` = excluded, `PricingTablesItemModule.php:322`), `button.innerContent.{text,linkUrl}`, `module.advanced.featured`. The handler writes `module.advanced.title/priceText/…`. |
| Team member: name only | Reads `position.innerContent`, `content.innerContent`, `image.innerContent.desktop.value.url` (`TeamMemberModule.php:98`), `social.innerContent.desktop.value.{facebookUrl,twitterUrl,linkedinUrl,googleUrl}`. Handler writes `module.advanced.position/description`, `image.innerContent…src`. |
| Testimonial: quote only | Reads `author.innerContent`, `jobTitle.innerContent`, `company.innerContent.{text,linkUrl}`, `portrait.innerContent.desktop.value.src`. Handler writes `company.innerContent.{author,company}` and `module.advanced.portrait`. |
| Countdown at zero | Reads `content.advanced.dateTime` (`CountdownTimerModule.php:361`, `strtotime`); VB field `divi/timepicker`, default `2020-03-23 01:30`. Handler writes `module.advanced.countdownDate`. |
| Progress bar "Array" | `progress_bar_value` is a slider `{unit,size}`. |
| CTA body, pricing subtitle, ElementsKit subtitle, post grid categories dropped | Settings never read or never written (`docs/known-issues.md`). |
| Pro: one of header/footer missing, page body hidden | Two default `et_template` posts, each with only its own area's meta; Divi applies one template per page and treats a missing `_et_body_layout_id` + `_et_body_layout_enabled` as "override and hide". |

## Part 1 — Verification layer

### 1a. Schema test (PHPUnit, runs offline)

**Extracted schema, committed.** `scripts/divi-module-schema.php` reads every `module.json` under `references/Divi/…/module-library/src/components/` and writes `fixtures/divi-schema/modules.json`:

```json
{
  "diviVersion": "5.7.4",
  "modules": {
    "divi/heading": {
      "childrenName": [],
      "attributes": {
        "title": {
          "elementType": "heading",
          "groups": { "innerContent": { "groupType": "group-item", "subNames": [] },
                      "decoration": { "font": {} } }
        },
        "module": { "elementType": "", "groups": { "advanced": { "link": {}, "text": {}, … }, "decoration": { … }, "meta": { "meta": {} } } }
      }
    }
  }
}
```

`subNames` come from `items[*].subName` / `groups[*].item.subName` (for `group-items` and `into-multiple-groups` content attrs such as `button.innerContent` → `text, linkUrl, …`). The same script writes `fixtures/divi-schema/fa-icons.json` from `references/Divi/includes/builder/feature/icon-manager/full_icons_list.json` (entries whose `styles` include `fa`): FontAwesome name → `{unicode, weight}`, used by Part 2 and generated into the plugin as `includes/data/fa-icons.php`. A guard test (`DiviModuleSchemaFixtureTest`) regenerates in memory and fails if the committed files drift from `references/Divi`; it is skipped, with a message, when `references/Divi` is absent.

**Assertion helper.** `tests/support/DiviModuleSchema.php` exposes `assertBlocksValid(array $blocks, string $context)`. For every block, recursively:

1. `name` is a module in the schema.
2. Nesting: when the parent's `childrenName` is non-empty, the child's name is in it (`divi/pricing-tables` → `divi/pricing-table`, `divi/social-media-follow` → `divi/social-media-follow-network`, `divi/counters` → `divi/counter`, `divi/section` → `divi/row`).
3. Every top-level settings key is an attribute of that module, or `css` (Divi's custom-CSS attribute; allowed keys are the module's `customCssFields` plus `freeForm`).
4. Every second-level key is a group the attribute declares (`innerContent`, `decoration`, `advanced`, `meta`), and every third-level key under `decoration`/`advanced` is declared there. `innerContent` and leaf groups are followed by the responsive envelope: breakpoint keys in `desktop|tablet|phone`, state keys in `value|hover|sticky`.
5. Value shape, for the groups the converter writes: the keys under `value` are in an allow-list per option group, each list sourced from the server-side style declarations (`server/Packages/Module/Options/<Group>/…`) and cited in the helper's docblock — `font`, `background`, `spacing`, `sizing`, `border`, `boxShadow`, `layout`, `text`, `link`, `filters`, `zIndex`, `overflow`, `position`. For content attrs with `subNames`, the value is an object whose keys are in `subNames`; for `elementType: heading` the value is a string; for `headingLink` and `button` an object with `text`; for `image` an object with `src` or `url` as that module's renderer reads it (recorded per module in the helper, with the renderer line cited); icons are objects with `type`, `unicode`, `weight`.

Failures list the block name, the offending path and the context (fixture name, page, widget), so a new handler bug reads as "divi/testimonial: company.innerContent.desktop.value.author is not a declared subName (fixture eael-testimonial)".

**Where it runs.** The helper is called from every place the converter already produces output, so the checked paths come from real output rather than a hand list:

- `ConverterFixtureTest` (every `fixtures/elementor/*.json`).
- `AddonSettingNamesTest::convert()` (every add-on probe).
- New `KitPagesSchemaTest`: the pages inside `references/kits/ceramic-studio.zip` and `painting-company.zip` (read with `ZipArchive`), converted with the kit's own `site-settings.json` globals supplied through `edc_kit_globals`, so core widgets are checked in real-world shapes.
- The demo's `DocumentConversionTest` (`vendor/bin/phpunit -c demo/phpunit.xml`), for the seven Ferncourt pages and both HFE templates.

**Expected failures.** The helper takes a map of `(block, path) → reason` for bugs not yet fixed. The plan adds it in the first task with every known-broken path listed, and each fix removes its entries; the last task deletes the map. The suite stays green at every commit while never hiding a path that was not on the list.

### 1b. Render check (Playwright against the demo stack, port 8040)

**Probe page.** `demo/content/probes/core-widgets.php`, a document in the same DSL as the pages, holding the core-widget cases the Ferncourt site avoids: a two-column section at 90 vh with the left column carrying a cover background image and a spacer (the Ceramic Studio hero), a `span` heading at 12 vw with weight, letter spacing and colour, a button that sets only a global background colour, a counter `58` with suffix `%` and a counter `240` with suffix `+`, an image carousel of four seeded photos with `slides_to_show: 4`, a core gallery of three photos, social icons for Instagram and LinkedIn, and a Google Maps widget. The add-on cases are already on the Ferncourt pages (info box and testimonial on Home, flip box and filterable gallery on Spaces, pricing tables on Memberships, team member and progress bar on About, countdown on Events, HFE menu in the header).

`demo/lib/documents.php` gains `insert_document()` extracted from `seed.php`; `demo/lib/seed-probes.php` inserts every `probes/*.php` as a page marked with the same `_ferncourt_document` meta, published. `document_names()` and `starting-state.php` stay unaware of probes: the probe is created by the render check and removed by the reset check that follows.

**Stage.** `demo/verify.sh render` (new `check_render`, placed between `converted` and `reset` in `ALL_CHECKS`): seed probes, activate Divi, commit the probe and the seven pages as drafts (`commit-conversions.php` gains an optional `--probes` argument and records `kind: page|probe` in `converted.json`), then `PW_STAGE=render npx playwright test … render`. `playwright.config.ts` ignores `render.spec.ts` unless `PW_STAGE=render`.

**Spec.** `demo/tests/render.spec.ts` logs in, opens each draft preview at 1440 × 900, scrolls to settle, and asserts what a viewer sees. Every assertion names the Elementor widget it stands for. The list grows with the fixes; at the end it covers:

- probe: the left column's box is at least 400 px tall and its computed `background-image` names the seeded file; the span heading's computed font size is at least 100 px and its colour is the kit colour; the button's computed background is the accent colour and its text colour white; counter texts settle at `58%` and `240` (no sign); four gallery images each at least 200 px wide, no pagination; the gallery has three items; the social list has `et-social-instagram` and `et-social-linkedin` and nothing else; the map has a visible `iframe` at least 300 px tall (or, after diagnosis, whatever the fix renders).
- home: three blurbs with their titles and icons; each testimonial has its portrait image, author and position.
- spaces: flip-box blurbs with titles; the gallery has exactly the filterable gallery's image count.
- memberships: each pricing table shows title, price, period, every feature and the button.
- about: each team member shows image, position and description; the bar counter shows the percentage.
- events: the countdown's `data-end-timestamp` is in the future and the days digits are not `000`.
- header/footer (any page): the menu's computed background is transparent; footer social icons are Instagram and LinkedIn.
- contact: social icons and the map.

`demo/README.md` documents the stage and the probe.

## Part 2 — Fixes

Each fix follows systematic debugging: a failing schema assertion or unit test (and a failing render assertion where the demo shows it), then the smallest change, then proof by both. One commit per fix.

### 2.1 Column background (core)

`ColumnConverter` stops wrapping children in a `divi/group`. Background, overlay (already expressed as a gradient layer in `module.decoration.background`) and padding stay on the column. `StyleMapper::mapContentPosition` already maps the section's `content_position` to the row's `alignItems`; the section's `column_position` (Elementor's *Column Position*, `stretch|top|middle|bottom`) now maps to `module.decoration.layout.desktop.value.alignItems` on the row (`stretch` → `stretch`, others via the existing `normalizeFlexAlignment`), and `content_position` on a column keeps mapping to the column's `justifyContent`. `fixtures/divi/column-overlay.json` is regenerated and reviewed by hand. `SectionConverter::liftColumnBackgroundToSection` is unchanged.

### 2.2 Heading level span, p, div (core; approved)

`HeadingConverter` emits `divi/text` when the level is not `h1…h6`: `content.innerContent.desktop.value` is `<span>…</span>` (or `<p>`, `<div>`), and the typography StyleMapper produced for `title.decoration.font.font` is moved to `content.decoration.bodyFont.body.font` (same value keys: size, weight, family, letterSpacing, lineHeight, color, style, textAlign). The module's spacing, sizing and other `module.*` attrs are unchanged. The conversion report counts it as `text`. The same rule applies to `ElementskitHeadingConverter` through a shared helper.

### 2.3 Button defaults (core)

Elementor's cascade for the `button` widget is reproduced for the properties Divi renders differently by default:

| Property | 1. widget | 2. kit *Theme Style → Buttons* | 3. Elementor built-in |
|---|---|---|---|
| background colour | `background_color` (direct or global) | `button_background_color` | global colour `accent`, else `#69727d` |
| text colour | `button_text_color` | `button_text_color` | `#ffffff` |
| typography | `typography_*` | `button_typography_*` | global typography `accent`, size `15px` |
| padding | `text_padding` | `button_padding` | `12px 24px` |
| border radius | `border_radius` | `button_border_radius` | `3px` |
| border | `border_*` | `button_border_*` | none |

Column 2 arrives through the existing `edc_kit_globals` filter as a new `buttons` entry: the free plugin's provider reads the active kit's `_elementor_page_settings`; Pro's `KitGlobalsParser` reads the same keys from the uploaded kit's `site-settings.json`. `StyleMapper::map('button', …)` applies columns 2 and 3 only for properties the widget left unset, and only for the standalone `button` widget (callers that map composite widgets' buttons are unchanged). Paths are the ones already used: `button.decoration.background.*.value.color`, `button.decoration.font.font.*.value.{color,size,…}`, `button.decoration.spacing`, `button.decoration.border`.

### 2.4 Counter (core; affixes approved)

`CounterConverter` writes the numeric part of the ending number to `number.innerContent`, sets `number.advanced.enablePercentSign` to `on` only when the suffix is `%` and `off` otherwise, and reports any other prefix or suffix as not carried over (new kind `counter_affix`, rendered by `NotCarriedOverRenderer` as "Counter prefix/suffix") so the demo's zero-warning check stays meaningful. `title_color` and `title_typography_*` map to `title.decoration.font.font` (StyleMapper gains the counter's secondary font path).

### 2.5 Image carousel and galleries (core, EAEL)

A shared `AttachmentResolver` (free plugin, `includes/helpers/`) turns `{id, url}` into an attachment ID: the given ID when it is an image attachment on this site, else `attachment_url_to_postid($url)`, else 0. Outside WordPress (unit tests) it returns the ID as given.

- `ImageCarouselConverter` emits `divi/gallery` with `image.advanced.galleryIds`, `module.advanced.postsNumber` = image count (Divi paginates at 4 by default), `module.advanced.showTitleAndCaption = off`, and layout by `slides_to_show`: `1` → `module.advanced.fullwidth = on` (Divi's slider), otherwise `galleryGrid.decoration.layout.desktop.value.gridColumnCount` = the count (Elementor's default is 3). `autoplay` → `module.advanced.auto`, `autoplay_speed` → `autoSpeed`. When no image resolves to an attachment, the current inline-image fallback is kept without the 60 px cap, and a warning says why.
- `EaelFilterableGalleryConverter` emits `image.advanced.galleryIds` the same way, `postsNumber` = count, column count from EAEL's `columns` control (read from the EAEL 6.6.7 source), and reports the filter buttons, item names and captions as not carried over (kind `gallery_extras`, approved: no media-library edits).
- `GalleryConverter` (core gallery) gains `postsNumber` = count.

### 2.6 Map (core)

Reproduced on the probe page first. If Divi strips or escapes the `<iframe>` in `divi/code`, the fallback order is: `divi/map` with `map.innerContent.desktop.value = {address, lat, lng, zoom}` when the site has a Google Maps API key (`et_google_api_settings` option), else the code module with whatever markup Divi does render. If the code module renders the iframe once the block is written correctly (for example the `&amp;` in the URL or entity handling), that is the fix. The outcome and its evidence go in `docs/known-issues.md`.

### 2.7 Social icons and menu (core, HFE)

`SocialIconsConverter::resolveNetwork()` reads `social_icon.value` (`fab fa-instagram`), then the legacy `social` string, and maps `facebook-f` / `linkedin-in` / `x-twitter` / `square-*` variants to Divi network slugs from `SocialMediaFollowItemModule::get_social_networks()`; an unknown network becomes a warning instead of Facebook. `HfeNavigationMenuConverter` writes `module.decoration.background.desktop.value.color = rgba(255,255,255,0)` unless HFE's own menu background colour is set, in which case that colour is used.

### 2.8 Blurbs: info box and flip box (EAEL)

Both handlers adopt `IconBoxConverter`'s shape: `title.innerContent.desktop.value = {text}`, `content.innerContent`, `imageIcon.innerContent.desktop.value = {useIcon: on, icon: {type: fa, unicode, weight}}` via the generated FontAwesome map (`includes/data/fa-icons.php`; unknown icon → Divi's star with a warning), or `{src}` for an image. `IconBoxConverter` and `IconConverter` switch to the same map (the icon widget's `icon.innerContent` becomes the icon object Divi reads). The flip box keeps front and back content in the body.

### 2.9 Pricing table (EAEL)

Paths per the root-cause table; subtitle carried; features joined with `\n`; EAEL's per-item "available" toggle (name read from the EAEL 6.6.7 source) becomes a leading `-`; `eael_pricing_table_featured = yes` → `module.advanced.featured = on`. `eael_pricing_table_sub_title` is read.

### 2.10 Team member and testimonial (EAEL)

Paths per the root-cause table. Team member social links from `eael_team_member_social_profile_links` items (`social_new.value` → network, `link.url`) fill `social.innerContent.desktop.value.{facebook,twitter,linkedin,google}Url`; other networks are reported as not carried over. Testimonial: `eael_testimonial_company_title` → `jobTitle.innerContent`.

### 2.11 Countdown (EAEL)

`content.advanced.dateTime.desktop.value` in `Y-m-d H:i`, normalised from EAEL's stored value (format confirmed from the EAEL 6.6.7 control definition); an unparsable date is a warning.

### 2.12 Dropped content (EAEL, ElementsKit)

- Progress bar: `progress_bar_value.size`, scalar fallback, default `50`, `progress_bar_value_dynamic` only when `progress_bar_value_type = dynamic`.
- CTA box: body = subtitle followed by `eael_cta_content`.
- ElementsKit heading: when `ekit_heading_sub_title_show = yes`, the subtitle becomes a `divi/text` before the heading.
- Post grid: `category_ids` → `post.advanced.categories` in the shape `BlogModule.php` reads; a non-`post` `post_type` or other `<taxonomy>_ids` are reported as not carried over.

### 2.13 Pro Theme Builder (Pro 1.2.1)

`DiviThemeBuilderExporter` keeps one default template: `upsertTemplatePost()` looks for the plugin's own default template first (`_edc_tb_source` = `template:default`), then any published `_et_default` template attached to the live Theme Builder, and adds the slot's layout to it. Every save writes `_et_body_layout_id = 0` and `_et_body_layout_enabled = '1'`, and for the slot it is not setting, `_et_<slot>_layout_id = 0` + `_et_<slot>_layout_enabled = '1'` when that meta is absent. `ThemeBuilderDedupeTest` gains "header then footer → one template with both layouts and the body enabled". `demo/lib/theme-builder.php` drops its manual repair.

### 2.14 HFE conflict notice (free) and HFE templates in the picker (free + Pro)

- An admin notice in the free plugin when Header Footer Elementor is active, the active theme is Divi, and a published `et_header_layout` or `et_footer_layout` exists: deactivate HFE, with the reason (its `remove_all_actions('wp_head')` inside Divi's header buffer fatals every page). The same sentence is appended to Pro's header/footer import result.
- `InstalledPostSource` and `ElementorPageRepository` include `elementor-hf` posts, mapping `ehf_template_type` `type_header`/`type_footer` to `template_type`; `ElementorImportParser` replaces `hfe-template` with those values and adds the footer case; Pro's upload applies the chosen slot as `template_type`. Covered by `InstalledPostSourceTest`, `ElementorPageRepositoryTest`, `HeaderTemplateConversionTest`.

## Reporting

Nothing is dropped silently. New not-carried-over kinds: `counter_affix`, `gallery_extras`, `social_network`, `query_filter`. Warnings are reserved for things the user should act on (an icon that could not be mapped, a date that could not be parsed, images that are not in the media library).

## Documentation and versions

- `docs/known-issues.md`: each fixed item moves to "Fixed in 3.0.2" (or "Fixed in Pro 1.2.1") with its commit; the map outcome and anything Divi cannot express stay open with evidence.
- `docs/conversion-map.md`: rows for the changed widgets.
- `demo/README.md`: the render check and the probe page. Demo page comments naming a workaround are removed with the workaround (progress bar back on About, pricing subtitles, CTA body, ElementsKit subtitle).
- Free `readme.txt`: `= 3.0.2 =` under Changelog and Upgrade Notice; `Version:`, `EDC_PLUGIN_VERSION`, `Stable tag`, `demo/versions.env` and `ReleaseMetadataTest` to 3.0.2. Pro to 1.2.1 the same way.

## Test plan

- `vendor/bin/phpunit` green at every commit (696 + new; the one PHPUnit deprecation is pre-existing).
- `vendor/bin/phpunit -c demo/phpunit.xml` green (22 + schema assertions).
- `demo/verify.sh` green including the new `render` check; `demo/reset.sh` afterwards.
- After the core fixes and again at the end: import Ceramic Studio, screenshot its five pages before and after (`demo/README.md`, "Trying another kit"), and compare by eye; the after shots are committed under `demo/output/kit-screenshots/`.
- `demo/build.sh` is only needed if the seed changes; the probe is inserted by the render check, not the seed.
