# Conversion Coverage & Rollback — Design (2.3.0)

**Date:** 2026-08-28
**Repos:** `jhmg-elementor-to-divi5` (plugin), `layoutlab` (telemetry endpoint)
**Status:** approved, ready for an implementation plan

## Goal

Give users a durable picture of what the converter could not convert, let them undo an
import, and — with explicit consent — feed the unsupported-widget signal back to
divi5lab so future widget coverage work is aimed by evidence instead of guesswork.

This release bundles the already-built, unreleased 2.2.0 work (Pro price correction,
price-drop notice, wp.org review prompt). **2.2.0 is never published**; the wp.org
release is 2.3.0 and its changelog folds both sets of changes, so users never see a
version number that did not exist for them.

## Motivation

The plugin has 100+ active installs against 2,824 downloads and one review. Nothing in
the product tells a user what did not convert beyond a single run's transient report,
and nothing tells Lucas which widgets matter across users. The result is that "add more
widget coverage" — the obvious next feature — has no evidence behind it.

## Architecture

### The shared foundation: persisted import history

Import results currently live in a one-hour transient (`edc_batch_<id>`, written in
`AdminPage::handle_import()`). Both features in this release need data that outlives
that hour: coverage needs unsupported widget types across runs, rollback needs the post
IDs a run created. So the core of 2.3.0 is one store that both read.

`ImportHistory` (new, `includes/history/class-import-history.php`), backed by the
option `edc_import_history`:

```php
[
  [
    'id'          => string,   // the existing import_id
    'at'          => string,   // 'Y-m-d H:i:s', UTC
    'post_ids'    => int[],    // posts this run created
    'unsupported' => string[], // widget types from every result item in the run,
                               // flattened and de-duplicated to one entry per type
    'succeeded'   => int,
    'failed'      => int,
    'rolled_back' => bool,
  ],
  ...
]
```

Written in `handle_import()` alongside the existing `ReviewPrompt::record_run()` call.
Capped at the **25 most recent runs**, pruned on write — unbounded growth in
`wp_options` is a common cause of slow-site complaints.

Both features are thin readers over this store. Building it once is the reason they are
in the same release; built separately, each would grow its own half of it.

### Coverage screen (free)

A new section on the plugin's existing admin page (`Tools -> Elementor to Divi 5`),
below the import form rather than behind a separate menu entry — it is a read-only
summary, and a second top-level item for it would be clutter. It lists every Elementor
widget type this site hit that did not convert, ranked by the number of runs it appeared in, with a last-seen
date. Empty state when everything converted.

This screen also hosts the telemetry opt-in — the one moment a user is already looking
at precisely the data being requested, which is the only honest place to ask.

### Rollback (free)

Each history entry gets an Undo control.

- Uses `wp_trash_post()`, never `wp_delete_post()`. An undo that permanently destroys
  content is not an undo; the WP trash is the safety net.
- Every post ID is verified against the existing `_edc_import_source` post meta before
  being touched, so a post the user has since adopted and edited cannot be swept away by
  a stale batch record.
- Guarded by a nonce and `manage_options`.
- Reports how many posts were trashed and how many were skipped (already gone, or no
  longer plugin-owned).
- Marks the run `rolled_back` rather than deleting the record, so the history stays
  honest.

This is the first destructive operation the plugin has ever performed. The ownership
guard is the part that most deserves tests.

### Telemetry (free plugin + layoutlab)

**Consent.** Opt-in checkbox on the coverage screen, off by default, stored in
`edc_telemetry_consent`. `readme.txt` gains the external-service disclosure wp.org
requires (guideline 7). Nothing is ever sent without consent.

**Payload.** The distinct unsupported widget type names accumulated since the last
send, plus the product slug so the two converters can be told apart:

```json
{ "product": "elementor-to-divi5", "widget_types": ["woocommerce-menu-cart", "nested-carousel"] }
```

`product` is a coverage identifier for the free converters (`elementor-to-divi5`,
`divi-to-elementor`). It is deliberately NOT one of the licensing `PLUGIN_PRODUCTS`
slugs, which name paid products and must not be conflated with anonymous reports.

```json
```

No versions, no site identifier, no URLs, no post content, no user data.

**Distinct names, not raw counts.** Each site contributes at most one vote per widget
type per weekly report. Without this, the ranking is a picture of whichever user
converts most, rather than of what most users need. This is strictly less data than
sending counts.

**Throttle.** At most one report per site per week, tracked in
`edc_telemetry_last_sent`. Client-side and therefore not tamper-proof; that is
acceptable for honest telemetry and is not a security boundary.

**When it fires.** On `admin_init`, when consent is present and the last send is more
than seven days old — never during a conversion, so a slow endpoint can never delay the
work the user actually came to do.

**Transport.** `wp_remote_post()`, non-blocking, all failures silently swallowed.
Telemetry must never slow or break a conversion.

**Endpoint.** New `POST /api/plugin/coverage` in layoutlab:

- Public and unauthenticated — a GPL plugin cannot hold a shared secret.
- Zod-validated, with hard caps on array length and per-string length.
- `rateLimit()` per IP. Note `lib/rate-limit/index.ts` is in-memory per serverless
  instance and self-documents as a stopgap, so it is not sufficient alone.
- Second line of defence: a DB-side global cap of **5,000 accepted reports per day**
  across all products (roughly 50x the plausible honest volume at 100+ installs
  reporting weekly). Beyond it the endpoint returns 200 and discards, so a bad actor
  can inflate noise but not the storage bill, and honest clients never see an error.
- New `plugin_coverage_reports` table: `{ id, product, widget_types, received_at }`.
  The ranked list is a `GROUP BY` over it.

## Testing

- **PHPUnit (plugin):** the history store including the 25-run prune; the coverage
  aggregate and its ranking; rollback's ownership guard (a post whose
  `_edc_import_source` meta is absent must be skipped); rollback trashing rather than
  deleting; consent gating (nothing sent when consent is absent); the weekly throttle.
- **Vitest (layoutlab):** endpoint validation, rejection of oversized payloads, rate
  limiting, and the daily cap.
- House rules apply: TDD, full suite plus a `php -l` sweep before each plugin commit.

## Risks and limitations

- **Opt-in telemetry consent rates are typically low single digits.** The ranked list
  will be thin for months. This is a slow instrument; it should not be treated as a
  blocker for widget coverage work, only as a way to aim it over time.
- **Rollback is destructive.** The ownership guard and the use of trash rather than
  delete are the mitigations, and both need explicit tests.
- **The weekly throttle is client-side** and can be bypassed by anyone editing the
  plugin. Acceptable: this is telemetry, not authentication.
- **Two repos must ship together.** The plugin's telemetry is inert until the layoutlab
  endpoint is live. The endpoint should be deployed first so no report is ever dropped.

## Out of scope

- Direct-from-Elementor-site conversion and conversion preview (planned for 3.0.0; they
  share a dry-run engine and should be designed together).
- Widening widget coverage itself, which this release exists to aim.
- Any change to the free/Pro boundary. Everything here is free.
