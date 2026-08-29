# Direct Conversion & Conversion Preview — Design (3.0.0)

**Date:** 2026-08-28
**Repo:** `jhmg-elementor-to-divi5` (plugin; free + Pro companion)
**Status:** approved in brainstorm, awaiting spec review

## Goal

Remove the export/upload round trip from first run. A user with Elementor installed
should be able to open the plugin, pick a page from a list, see what the conversion
will produce, and convert it — without ever leaving WordPress or touching a JSON file.

Both features rest on one capability: running a full conversion **without committing
it**. That dry-run engine is the deliverable; the picker and the preview are two
consumers of it.

## Motivation

2.3.0 made the plugin safer and more honest. It did not make it easier to start.

2,824 downloads have produced 100+ active installs and one review — roughly 3.5%
activation. The drop happens before anyone sees output: today the first thing the
plugin asks for is a JSON export the user must go and produce in Elementor. That is
homework demanded before any value is shown, and it is the single largest identified
cause of first-run abandonment.

Pro conversion is downstream of free success. Fixing the top of the funnel is worth
more than protecting the bottom of it.

## The free/Pro decision

**Decided: the line stays on *scope*, not on *source*.**

| | Free | Pro |
|---|---|---|
| Source | JSON upload **and** installed Elementor pages | same, plus kit ZIP |
| Scope | one page per run | unlimited pages per run |
| Theme Builder headers/footers | no (degrades to page, as today) | yes |
| Global styles | no | yes |
| Preview | yes | yes |

### Why not "all direct conversion is Pro"

It was the stronger candidate on defensibility. Kit ZIP import is gated by tedium, not
capability — a determined free user can export pages one at a time. Direct conversion
is a genuine capability difference and maps cleanly to willingness-to-pay: an agency
holding a 40-page Elementor site will pay $25 to skip 40 export/upload cycles.

It was rejected because it charges for the repair to a funnel that is not delivering
people to the paywall. The conversion rate from free-user to Pro-buyer is applied to a
pool that the export step has already drained. Gating the fix protects a revenue stream
by starving its input.

### Why the scope split keeps Pro defensible

Pro is not weakened by this; it is upgraded. "Convert your whole Elementor site in
place, no exports" is a stronger pitch than "upload a kit ZIP", and it is the same
buyer. The free tier's shape is unchanged in kind — one page at a time, unlimited runs
— only the friction is removed.

### wordpress.org guideline 5

The seam is a **quantity limit, not a disabled feature**. The free plugin ships the
complete picker, the complete preflight engine, and the complete commit loop, and caps
the selection at one page:

```php
$limit = (int) apply_filters( 'edc_direct_conversion_limit', 1 );
```

Pro returns a higher limit and swaps the picker's radios for checkboxes. No Pro-only
implementation ships inside the free tree in a disabled state; there is nothing to
unlock, only a boundary to raise. This is the standard freemium quantity pattern rather
than an argument the reviewer has to be talked into.

The seam exists from the first commit. Retrofitting it later is the failure mode this
decision is being made early to avoid.

## Architecture

### Current state

`BatchImporter::import()` does four jobs in one loop: resolve the source, convert,
write posts, and stamp meta. Preview needs jobs 1–2 without 3–4. Direct conversion
needs a different job 1. So the class splits along the seams it already has.

Two pieces of the work are already written and unused:

- `ElementorDocumentParser` (`includes/parsers/class-elementor-document-parser.php`)
  reads `_elementor_data` out of post meta, handles every observed encoding shape, has
  a passing test, and is called by nothing in production. It is the live-site reader.
- `DiviExporter::save()` accepts a `$dry_run` flag that returns meta but never
  serializes `post_content` — half a dry run, and the missing half is exactly what a
  preview must show. This flag is **removed** in favour of `ConversionPreflight`; two
  competing dry-run concepts is worse than one.

### New: `includes/conversion/`

**`ConversionSource` (interface)** — `items(): array`, yielding the normalized item
shape `ElementorImportParser::makeItem()` already produces, plus `source_ref`:

```php
[
  'title'         => string,
  'post_type'     => string,   // 'page' | 'post'
  'post_name'     => string,
  'template_type' => string,   // '' | 'header' | 'footer'
  'elements'      => array,    // raw Elementor element tree
  'source_ref'    => [
      'kind'    => 'upload' | 'installed',
      'post_id' => int|null,   // installed only
      'file'    => string|null // upload only, basename for display
  ],
]
```

- **`UploadedJsonSource`** — wraps the existing `ElementorImportParser`. Behaviour
  unchanged; it gains only the `source_ref` field.
- **`InstalledPostSource`** — new. Given post IDs, loads each post's meta, runs
  `ElementorDocumentParser`, and derives title / post_type / post_name from the post
  itself. For `elementor_library` posts it reads `_elementor_template_type` into
  `template_type`, so an installed header degrades to a page with the existing Pro
  warning exactly as an uploaded header does today.

**`ConversionPlan`** — an immutable value object. What a dry run produced:

```php
[
  'items' => [
    [
      'title'         => string,
      'post_type'     => string,
      'post_name'     => string,
      'template_type' => string,
      'source_ref'    => array,
      'blocks'        => array,   // converted Divi tree (ConverterEngine output 'divi')
      'content'       => string,  // serialized Divi 5 block markup
      'report'        => array,   // counts, warnings, skipped settings
      'unsupported'   => array,   // unsupported element entries
      'outline'       => array,   // structural preview tree (below)
      'error'         => string,  // '' unless this item failed to convert
    ],
  ],
  'limit'     => int,   // the effective edc_direct_conversion_limit
  'truncated' => bool,  // true when the selection exceeded the limit
]
```

**`ConversionPreflight`** — the dry-run engine. Takes a `ConversionSource`, runs
`ConverterEngine` and `DiviBlockSerializer` per item, builds the outline, returns a
`ConversionPlan`. **Performs no database writes of any kind.** This is the class both
new features consume, and the one that gets the heaviest test coverage.

**`ConversionCommitter`** — takes a `ConversionPlan` plus options
(`post_status`, `post_type`) and performs the writes: `wp_insert_post`, `DiviExporter`
meta, `_edc_import_source` stamping, Divi cache clears, Theme Builder delegation via
the existing `edc_theme_builder_exporter` filter. This is today's `BatchImporter`
minus conversion, with its per-item result shape preserved verbatim.

### `BatchImporter` after the split

`BatchImporter::import( array $items, array $options )` keeps its exact current
signature and result shape, implemented as preflight-then-commit. This matters: 399
tests and the Pro plugin's Theme Builder contract both depend on it, and this release
should not churn the Pro seam while it is adding one.

### Meta written by a direct conversion

- `_edc_import_source` = `'direct'` (uploads continue to write `'file_upload'`)
- `_edc_source_post_id` = the Elementor post the conversion was read from

`ImportRollback`'s guard already keys on the presence of `_edc_import_source`, so
direct conversions are undoable with no change to that class.

### The source page is never modified

A direct conversion **creates a new Divi draft**. It does not overwrite, replace, or
touch the Elementor post it read from, and it does not delete `_elementor_data`.

This is deliberate. In-place replacement is what a migrating user eventually wants, but
it is a one-way door: Undo cannot restore Elementor data the converter overwrote, and
a mistake lands on a live page. It needs its own design — snapshot, restore path,
explicit confirmation — and is out of scope for 3.0.0.

## The preview

### What it shows

A **structural preview**, not a rendered one:

1. **An outline** — the converted tree drawn as nested section / row / column boxes
   with module names in place, rendered as plain HTML from `ConversionPlan['outline']`.
   This answers the question users actually have: did my three-column layout survive.
2. **The conversion report** — modules converted, warnings, skipped settings, and the
   unsupported widgets **by name**, reusing the existing report card rendering from
   `AdminPage::render_report_cards()`.

Outline node shape:

```php
[
  'type'        => 'section' | 'row' | 'column' | 'module',
  'name'        => string,  // 'divi/heading'
  'label'       => string,  // 'Heading'
  'unsupported' => bool,    // rendered as a marked gap
  'children'    => array,
]
```

### Why not a rendered visual preview

Divi 5 generates its CSS per-post through a style manager keyed to a post ID. Rendering
detached block markup produces an unstyled skeleton — which reads as *broken output*
and damages trust more than showing nothing. Getting true pixels requires writing a
scratch post, which contradicts the premise of previewing before anything is written
and leaves orphaned rows behind whenever a request dies mid-flight.

The visual check already exists and is WordPress's own: the conversion lands as a
draft, and the user previews the draft. 3.0.0 is not building a parallel renderer. If
in-page rendering is wanted later it is additive on the same `ConversionPlan`.

### Naming

The control is labelled **"Check this page"**, and the result screen **"Conversion
report"** — not a bare "Preview", which promises pixels this does not deliver. 2.3.0
was the honesty release; overpromising in the next one would spend that.

### Preview does not persist

Previewing writes nothing at all, including no history row and no telemetry. Only a
commit records to `ImportHistory` and only a commit feeds coverage telemetry. Users
consented to sending unconvertible widget names for **imports**; harvesting abandoned
previews would be a different bargain than the one they agreed to.

The preview screen carries only the selected post IDs forward. The commit step re-runs
the conversion rather than reading back a stored plan — conversion is deterministic, so
re-running is simpler than caching a large transient and cannot serve stale output.

## Screens

### New: "Convert from this site"

A panel on the existing plugin landing page, alongside the JSON upload card. Shown only
when Elementor is detected (`_elementor_edit_mode` posts exist, or the Elementor plugin
is active); otherwise the card explains that direct conversion needs Elementor
installed and points at the upload path.

**Picker** — a table of Elementor-built posts: title, post type, status, modified date,
and an "already converted" badge for posts already referenced by
`_edc_source_post_id`. Radio buttons in free, checkboxes when the limit is above 1.
Paged at 20 with a title search box.

**Flow:** pick → *Check this page* → conversion report + outline → *Convert* or *Back*.
The convert step reuses the existing post-status / post-type controls and the existing
batch result screen, so a direct conversion ends on the same success screen, with the
same Undo affordance, as an upload does.

### `ElementorPageRepository`

The picker's query lives in its own class rather than inline in the admin page:
`WP_Query` over public post types plus `elementor_library`, `meta_key
=> '_elementor_edit_mode'`, `meta_value => 'builder'`, ordered by `post_modified`
descending, with search and paging. It exposes a small typed result so the admin screen
does not depend on `WP_Query` directly — and so tests do not depend on the harness's
`WP_Query` stub, which currently returns empty results unconditionally.

### Code placement

`includes/admin/class-admin-page.php` is 895 lines. The direct-conversion screen goes
into a new `includes/admin/class-direct-conversion-page.php` rather than growing that
file further. This is targeted at the code being touched; no broader refactor of the
admin surface is in scope.

## Security

- Every new handler checks `current_user_can( 'manage_options' )`, matching the
  existing admin handlers.
- Nonces on both the check and the convert steps, following the existing
  `check_admin_referer` pattern.
- Post IDs from the request are cast to int, and every selected post is re-verified as
  Elementor-built and readable by the current user before conversion — the picker's
  rendered list is not trusted as an allowlist on the way back in.
- The selection is capped server-side at `edc_direct_conversion_limit`; the limit is
  never enforced only in markup.

## Files

**New**

- `includes/conversion/class-conversion-source.php` (interface)
- `includes/conversion/class-uploaded-json-source.php`
- `includes/conversion/class-installed-post-source.php`
- `includes/conversion/class-conversion-plan.php`
- `includes/conversion/class-conversion-preflight.php`
- `includes/conversion/class-conversion-committer.php`
- `includes/admin/class-direct-conversion-page.php`
- `includes/admin/class-elementor-page-repository.php`

**Changed**

- `includes/admin/class-batch-importer.php` — delegates to preflight + committer
- `includes/exporters/class-divi-exporter.php` — remove the vestigial `$dry_run`
- `includes/admin/class-admin-page.php` — register the new screen, render the card
- `readme.txt`, landing copy, changelog — free/Pro wording for the new capability

**Pro**

- `includes/class-plugin.php` — register `edc_direct_conversion_limit`

## Testing

TDD throughout; full PHPUnit suite plus `find plugin -name '*.php' -exec php -l {} \;`
before every commit.

- **`ConversionPreflight`** — the heaviest coverage. Conversion output matches what
  `BatchImporter` produces today for the same input; outline structure for nested
  layouts; unsupported widgets marked in the outline; per-item failure isolated to that
  item's `error` without aborting the run. **An explicit test that a preflight run
  leaves the harness's in-memory post and meta stores byte-identical** — the no-writes
  property is the whole premise and is asserted, not assumed.
- **`InstalledPostSource`** — against the `_elementor_data` encodings already covered by
  `ElementorDocumentParserTest`; `elementor_library` template-type derivation; a post
  with absent or corrupt Elementor data yields a clean per-item error.
- **`ConversionCommitter`** — result shape identical to today's `BatchImporter`;
  `_edc_import_source` and `_edc_source_post_id` stamped correctly per source kind.
- **`BatchImporter`** — existing tests pass unchanged. This is the regression gate on
  the refactor.
- **Seam** — `SeamsTest` gains: limit defaults to 1 with no filter; a filtered limit
  raises it; a selection larger than the limit is truncated server-side and flagged
  `truncated`.
- **`ElementorPageRepository`** — query arguments asserted against an injected fake, so
  the test does not rely on the `WP_Query` stub.
- **Outline rendering** — asserted as string output, no Divi required.

## Out of scope for 3.0.0

- In-place replacement of the source Elementor page
- Rendered/visual preview
- Direct conversion of headers and footers into the Theme Builder from installed posts
  beyond the existing degrade-to-page behaviour
- Any change to licensing, telemetry consent, or the coverage endpoint

## Risks

- **Wider input distribution.** Reading live `_elementor_data` exposes the converter to
  third-party widgets, global widgets referenced by ID, and dynamic tags that a curated
  JSON export does not contain. Expect the unsupported-widget rate to rise. The preview
  is the mitigation: users see the gaps before committing rather than after. This also
  makes coverage telemetry considerably more representative, which is a real upside.
- **Large pages.** A heavy Elementor page converted on an admin request could approach
  PHP time or memory limits. Free is capped at one page per run, which bounds this;
  Pro's multi-page runs carry the existing kit-import risk profile and no new one.
- **Naming expectation.** Some users will read "preview" as pixels regardless of the
  label. Accepted, and mitigated by wording rather than by scope.
