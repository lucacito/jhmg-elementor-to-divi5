# Conversion Jobs

Tools → Conversion Jobs lets a site with thousands of Elementor pages be
converted over many sessions while always showing what is done, pending and
failed. Everything is plain PHP + jQuery; there is no build step.

## Where state lives

| What | Where | Why |
|---|---|---|
| "This page is converted" | post meta on the **source** post: `_edc_converted_at`, `_edc_converted_version` (+ `_edc_converted_manually`) | Works for sites with no job rows; `(post_id, meta_key)` is indexed by core, so the pending query stays an index anti-join at 10k+ posts |
| Page has no usable Elementor content | `_edc_unconvertible` | Lets the pending count reach zero |
| A job and its per-page results | tables `{prefix}edc_jobs`, `{prefix}edc_job_items` (dbDelta, version option `edc_db_version`) | One row per page; a serialized option would not scale |
| Undo for a finished job | `ImportHistory` run `job-<id>` | The existing Undo covers jobs unchanged |

`_edc_*` post meta is deliberately kept on uninstall; the tables are dropped.

## Pending

`PendingQuery` is one SQL statement. A post is pending when it is built with
Elementor, its `_elementor_data` starts `[{`, it is a live post of a public type
(not `elementor_library`), has no converted/unconvertible flag, and has not
failed in a job (failed pages are listed under their job, to retry).
Data that starts well but does not decode is caught when the page is run and
leaves the list then. Measured on 12,000 pages (MySQL 8, Docker): count 0.07s,
keyset page 0.07s, offset-8000 page 0.06s, 0.4 MB held.

## Running

The browser calls `edc_jobs_run` repeatedly. Each call converts pages for up to
8 s / 25 pages, then returns. A conditional-UPDATE lock stops two tabs running
one job. A page left "running" by a request that died is failed, not retried
forever. Resume = run again; Retry = put failed items back to pending first.

## Rules enforced on the server (not only in the UI)

* More than `edc_direct_conversion_limit` (free: 1) pages per job → `pro_required`.
* More than 50 pages → a mode must be chosen explicitly **and** confirmed.
* Submitted IDs must still be pending Elementor pages of an allowed type.
* Every endpoint: `manage_options`, the `edc_jobs` nonce, Divi 5 present.

## Back-compat

`Backfill` runs once on admin_init and flags sources of existing live copies
(`_edc_source_post_id`) and pages replaced in place (`_edc_import_source =
replaced`), labelled `legacy`. A trashed copy (an undone conversion) does not
count.

## Testing

`vendor/bin/phpunit` · `tests/integration/run.sh {jobs-store,pending-query,backfill}.php`
(real MySQL, needs docker-compose) · `npx playwright test tests/e2e/conversion-jobs.spec.ts`
