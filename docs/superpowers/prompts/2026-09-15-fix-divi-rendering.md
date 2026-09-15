# Fix the converter so converted pages render correctly in Divi 5

You are working on the JHMG "Converter for Elementor to Divi 5" WordPress plugin (free plugin
plus a Pro add-on). Pages convert "cleanly" by every existing test and by the plugin's own
conversion report, yet when the converted Divi 5 draft is opened with Divi active, many modules
render empty, tiny, unstyled or wrong. This has to be fixed before a product demo video can be
recorded. Nothing under `plugin/` has been changed to address it yet; everything below was
found on 2026-09-15 while building a demo site, and is recorded in `docs/known-issues.md`.

Read this whole prompt, then `docs/known-issues.md`, before touching code.

## Where things are

Two checkouts of the same repository:

| Path | Branch | What |
|---|---|---|
| `/Users/Lucas/Documents/JHMG-Local/jhmg-elementor-to-divi5` | `fix/correctness-pass-2026-08` at `2e1d0a9` | Main checkout. `2e1d0a9` is the free plugin's 3.0.1 release (Pro is 1.2.0). 27 commits ahead of `main` (`e3b8006`, 3.0.0); `main` has nothing newer. |
| `/Users/Lucas/Documents/JHMG-Local/jhmg-elementor-to-divi5-demo-site` | `demo-site` | Git worktree: `2e1d0a9` plus the demo site under `demo/`. |

Uncommitted in the main checkout:

- `docs/known-issues.md` — **the findings this work is based on. Commit it first** (see Step 1). Everything else uncommitted there (`plugin/jhmg-converter-for-elementor-to-divi-pro/includes/licensing/class-license-client.php`, deleted `references/…/.svn/pristine/*` files, `tests/e2e/screenshots/*.png`, `.phpunit.result.cache`, `.DS_Store`) is the user's own work in progress: leave it alone, do not stage it.

Repository layout that matters:

- `plugin/jhmg-converter-for-elementor-to-divi/` — free plugin. Converter core in `includes/converter/` (`class-converter-engine.php`, `class-base-elementor-converter.php`, `registry/class-converter-registry.php`, one handler per widget in `handlers/`), style translation in `includes/stylemapper/class-style-mapper.php`, output in `includes/exporters/class-divi-block-serializer.php` and `class-divi-exporter.php`, the dry run / commit pipeline in `includes/conversion/` (`ConversionPreflight`, `ConversionCommitter`, `InstalledPostSource`).
- `plugin/jhmg-converter-for-elementor-to-divi-pro/` — Pro add-on. Theme Builder export in `includes/exporters/class-divi-theme-builder-exporter.php`.
- `references/Divi/` — Divi 5.7.4, the exact theme the output targets. **The authority on what Divi renders is the generated module definitions:** `references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<module>/module.json` (108 modules). Each lists the module's `attributes` tree, `styleProps` selectors and defaults. Never edit anything under `references/`.
- `references/elementor.4.1.3.zip`, `references/essential-addons-for-elementor-lite.6.6.7.zip`, `references/header-footer-elementor.2.8.8.zip` — the add-on sources; `references/kits/ceramic-studio.zip` and `painting-company.zip` — two free Elementor Kit Library kits (core widgets only) that reproduce the core-widget bugs.
- `docs/divi5-schema.md`, `docs/divi5-storage.md`, `docs/conversion-map.md`, `docs/conversion-reporting.md`, `docs/elementor-schema.md` — existing notes on both formats. `RELEASE.md` — the wordpress.org release procedure.
- `tests/` — PHPUnit 13 (`vendor/bin/phpunit`, 696 tests, one pre-existing PHPUnit deprecation is normal). `tests/bootstrap.php` stubs WordPress in memory. `tests/AddonSettingNamesTest.php` and `tests/AddonSettingNamesSweepTest.php` (with `scripts/addon-setting-names.php`) are the pattern for testing widget converters against the add-ons' real setting names. `tests/e2e/` — Playwright 1.60 against the root `docker-compose.yml` stack on port 8000 (not needed for this work; it rewrites tracked screenshots when run).
- Output format: Divi 5 stores pages as Gutenberg-style blocks, `<!-- wp:divi/<module> {json attrs} -->…<!-- /wp:divi/<module> -->`; content values sit under `innerContent`, styling under `decoration`, behaviour under `advanced` (see `docs/divi5-storage.md`).

The demo site (`demo/` on the `demo-site` branch) is the render test bed:

- `demo/build.sh` builds a full WordPress at http://localhost:8040 from nothing (~10 min): Hello Elementor active, Divi 5.7.4 installed but inactive, Elementor 4.1.3, Essential Addons 6.6.7, Header Footer Elementor 2.8.8, ElementsKit Lite 4.0.5, Premium Addons 4.11.104, Contact Form 7 6.1.7, both converter plugins **bind-mounted from that worktree's `plugin/` directory** (so the stack sees your edits live). Login `admin` / `ferncourt-demo`. `demo/wp <args>` runs WP-CLI against it.
- The seeded site "Ferncourt Coworking" has seven Elementor pages built from supported add-on widgets (`demo/content/pages/*.php`), an HFE header and footer, and a Divi Theme Builder header/footer.
- `demo/verify.sh` runs five checks; `demo/verify.sh converted` converts every page to a Divi draft and writes `demo/output/screenshots/<page>-elementor.png` beside `<page>-divi.png`. `demo/reset.sh` restores the snapshot in ~10 s. `vendor/bin/phpunit -c demo/phpunit.xml` runs the pages through the real converter offline (22 tests).
- `demo/README.md` explains the rest, including "Trying another kit" (import `references/kits/ceramic-studio.zip`, screenshot originals and drafts with `demo/tools/kit-shots.cjs`).
- The demo build works around two Pro/theme bugs (below) in `demo/lib/theme-builder.php` and `demo/mu-plugins/ferncourt-demo.php`, and avoids several converter bugs in its page content (comments in the page files say which). Those workarounds are not fixes.

## The core finding

The test suite and the conversion report only check the converter's *own* output: that setting names are read and that text lands somewhere in the block tree. Nothing checks that the emitted attribute paths and shapes are ones Divi 5.7.4 actually renders. Ceramic Studio's five pages pass the dry run with zero errors, zero unsupported widgets and zero warnings, and still render broken. So this work has two halves: fix the individual bugs, **and add the missing verification layer** so the class of bug cannot come back:

1. A schema-level test: for every module the converter emits, every attribute path it writes must exist in that module's `module.json` `attributes` tree (and use the right value shape). Build the list of emitted paths from the converter's real output across the existing fixtures, not by hand.
2. A render-level check: convert, commit as a Divi draft, load it with Divi active, and assert what a viewer would see (image bounding boxes taller than a strip, the heading's computed font size, the button's background colour, the pricing table's text present in the DOM). The demo stack plus Playwright is the tool; `demo/tests/screenshots.spec.ts` and `demo/tools/kit-shots.cjs` show how pages are driven.

(There is a sibling project `/Users/Lucas/Documents/JHMG-Local/Divi 5 Deterministic Validator` and an MCP tool `mcp__ai-editor-divi5__validate_layout`; neither has been checked for this purpose. Look before writing a validator from scratch, but do not assume they fit.)

## What renders wrong (evidence from screenshots, 2026-09-15)

Every row passed the converter's content check: the text or value is present in the block. Sources: Ferncourt pages (add-on widgets) and Ceramic Studio (core widgets, sections/columns, Elementor 3.7.2).

Core widgets and structure — these break almost every real site, fix first:

| Source | Divi shows | What is known |
|---|---|---|
| **Column with a background image** (`background_image` + `background_size: cover`; the column stretched to the row's height in Elementor) | A strip ~12 px tall, on every Ceramic Studio page | The converter writes the background onto an empty `divi/group` inside the `divi/column` (`module.decoration.background.desktop.value.image = {url, position, size, repeat}`) instead of onto the column, and an empty group has no height. Fix the placement, and make sure the column gets the row's height (equal-height columns / min-height) so `cover` has something to cover. |
| **Heading with `header_size: span` (also `p`, `div`) and large custom typography** | Small default text; the kit's oversized display words lose size, weight and colour | Size `12vw`, weight, letter spacing and colour are written to `title.decoration.font.font.desktop.value` together with `headingLevel: "span"`. Divi's heading `styleProps` selector covers only `.et_pb_heading_container h1 … h6`, so a `span` gets no styling. Decide the mapping (e.g. keep the styling and pick a real heading level, or emit `divi/text`). |
| **Button with a background colour** | Ceramic Studio: white outline button with near-invisible text. Ferncourt header: background colour applied but text renders in Divi's default blue | Two different symptoms; trace both. Check what the button module needs for custom styles to apply (an enable flag on `button.decoration.button`?) and carry the text colour. |
| **`counter`** (Elementor core) | `240+` shows as `233%` (caught mid-animation) and `58%` as `58%%`; titles near-invisible on a dark section | The converter puts `prefix.number.suffix` into `number.innerContent`; Divi animates the numeric part and appends its own percent sign. Find the percent-sign attribute in `number-counter/module.json`, pass the plain number, and handle prefix/suffix explicitly. |
| **`image-carousel`** | A row of thumbnails ~40 px wide | Shape of the images list vs the Divi module's expectation. |
| **`eael-filterable-gallery`** → `divi/gallery` | Every image in the media library, paginated, captioned with file names | Images are written as `{src}` only; a Divi gallery with no attachment IDs falls back to all attachments. Check `gallery/module.json` (`galleryGrid`) for the expected shape (likely attachment IDs). Also carries no filter buttons, item names or captions. |
| **`google_maps`** → `divi/code` with an `<iframe>` | Blank space | Either the code module escapes the iframe or the attribute is wrong; a `divi/map` module exists. |
| **`social-icons`** (core) and HFE footer icons | Facebook icons in place of Instagram and LinkedIn; missing entirely on the Contact page | Network mapping. |
| HFE `navigation-menu` in the Theme Builder header | Menu on a white bar over the page background | Menu module background default. |

Add-on widgets — attributes are written, Divi renders nothing or only part:

| Elementor widget | Divi shows |
|---|---|
| `eael-info-box` (→ blurb) | Nothing: the section is empty |
| `eael-flip-box` | Nothing |
| `eael-pricing-table` (→ pricing tables) | Three empty grey boxes: no title, price, features or button |
| `eael-team-member` | Name only: no photo, job title or description |
| `eael-testimonial` | Quote only: no photo, name or role |
| `eael-countdown` | `000:00:00:00`. The converter writes the due date to `module.advanced.countdownDate`; `countdown-timer/module.json` names the attribute `content.advanced.dateTime` (label "Date") — also check the expected date format |

Converters that silently drop content (found by reading the handlers; details and suggested fixes in `docs/known-issues.md`):

- `eael-progress-bar`: `progress_bar_value` is a slider `{unit, size}`; the handler casts it to string → `"Array"` (reproduced).
- `eael-cta-box`: `eael_cta_content` (body text) never read.
- `eael-pricing-table`: `eael_pricing_table_sub_title` never read.
- `elementskit-heading`: `ekit_heading_sub_title` read but never written.
- `eael-filterable-gallery`: filters, item names, captions never read.
- `eael-post-grid`: `post_type` read but not written; `category_ids` (and `<taxonomy>_ids`) never read.
- Fancy text converts to a static heading with its first rotating phrase — by design, leave it.

Rendered correctly in the same runs, so use them as the reference for what "right" looks like: core heading (h1–h6), text editor, image widget, tabs, image box, ElementsKit dual button / heading / testimonial / video / accordion, EAEL data table, advanced accordion, advanced tabs, post grid, CTA box, Contact Form 7, Premium Addons blog.

Pro and theme-level (fix after the rendering bugs):

- **Pro saves the header and footer as two default Theme Builder templates**, each carrying only its own area's meta. Divi applies one template per page, so one of the two never shows; worse, a missing `_et_body_layout_id` + missing `_et_body_layout_enabled` reads as "override the body and hide it", so the page content disappears. `demo/lib/theme-builder.php` shows the manual repair (merge into one template, `_et_body_layout_id = 0`, `_et_body_layout_enabled = '1'`); the exporter should do that itself. `docs/known-issues.md` has the meta names.
- **Header Footer Elementor active under Divi crashes every page** once a Theme Builder header exists (`Call to a member function do_action() on array` from `wp_head`): both HFE and Divi's Theme Builder take over `get_header`, and HFE's `remove_all_actions('wp_head')` runs inside Divi's buffer window. Not our bug, but our users hit it right after converting; the plugin should at least warn (admin notice when HFE is active with a Theme Builder header, or post-conversion guidance).
- **HFE templates never reach the Theme Builder** through the normal UI (the `elementor-hf` post type is not listed; the Pro upload is typed `page`). Suggested fix is in `docs/known-issues.md`. Lower priority: the demo works around it.

## How to work

1. **Set up.** In the main checkout, commit `docs/known-issues.md` alone on `fix/correctness-pass-2026-08` (`git add docs/known-issues.md`, nothing else). Then, in the demo worktree, create the working branch from `demo-site` so the demo stack's bind mounts point at the code you are changing: `git checkout -b fix/divi-rendering-2026-09 demo-site`, and cherry-pick the known-issues commit onto it. All converter and test changes go on this branch; at the end it merges into `fix/correctness-pass-2026-08` (bringing `demo/` along, which is intended) and then into `main`. Confirm this branching with the user before the first commit.
2. **Use the superpowers skills.** Start with brainstorming (this is architectural: it adds a verification layer), write a spec and a plan, then execute task by task. For each rendering bug use systematic-debugging: reproduce first (a failing PHPUnit test asserting the attribute path against `module.json`, and/or a failing render assertion on the demo stack), then fix, then prove. TDD throughout; small commits, one fix per commit, ending with `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
3. **Ground every attribute in `module.json`.** When a handler writes a path, show where that path is defined in the module's `attributes` tree. Read setting names from the add-on sources in `references/*.zip`, not from memory.
4. **Verify by rendering, not only by unit tests.** After each group of fixes: `demo/reset.sh`, then `demo/verify.sh converted`, and look at the screenshot pairs (Read the PNGs); then import Ceramic Studio and screenshot its five pages before/after (`demo/README.md`, "Trying another kit"). A fix is done when the Divi screenshot matches the Elementor one for that element. If `demo/build.sh` is needed (it is after `git checkout` of a branch that changes the seed), it takes ~10 minutes; run it in the background.
5. **Keep the existing suites green:** `vendor/bin/phpunit` (696, one deprecation), `vendor/bin/phpunit -c demo/phpunit.xml` (22), `demo/verify.sh` (all five checks pass today; keep it that way). Once a converter bug is fixed, remove the demo content workaround for it (the page file comments name them, e.g. put `eael-progress-bar` back on About) so the demo exercises the fix.
6. **Record as you go.** Update `docs/known-issues.md`: move each fixed item to a "Fixed in 3.0.2" section with the commit; leave open items open. Add a changelog entry in `plugin/jhmg-converter-for-elementor-to-divi/readme.txt` (`= 3.0.2 =` under Changelog and under Upgrade Notice — `tests/ReleaseMetadataTest.php` asserts the count) and bump `Version:` in the plugin header, `EDC_PLUGIN_VERSION`, `Stable tag`, and the tests that pin 3.0.1. If Pro changes, bump it to 1.2.1 the same way.
7. **Do not release without asking.** `RELEASE.md` covers the wordpress.org SVN process; it needs the user's SVN password, which must never be stored in files, commits or memory. Stop and ask when the branch is ready.

## Priorities

1. The verification layer (schema test + render check), because it decides whether anything else is really fixed.
2. Column background → group, heading `span`, button background/text, counter suffix, image carousel, gallery IDs, map, social icons.
3. The add-on widgets that render empty (blurb, flip box, pricing tables, team member, testimonial, countdown), then the dropped-content converters.
4. Pro Theme Builder templates and the HFE warning; HFE → Theme Builder listing last.

## Constraints

- Never edit `references/`. Never run `wp plugin delete`/`theme delete` against the bind-mounted converter plugins or Divi in the demo stack (it would delete repo files).
- Do not stage the user's uncommitted changes in the main checkout.
- Keep the demo stack on port 8040 as the only demo stack; the root `docker-compose.yml` on port 8000 is a separate e2e stack.
- Report failures as failures. If a module cannot be made to render (Divi has no equivalent attribute), say so, log it as a warning in the conversion report so the user sees it, and record it in `docs/known-issues.md` — do not paper over it.
