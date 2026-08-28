# Conversion Coverage & Rollback Implementation Plan (2.3.0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist import history, surface what did not convert, let users undo an import, and — with explicit consent — report unsupported widget names to divi5lab so widget coverage work is aimed by evidence.

**Architecture:** One new store (`ImportHistory`, an option holding the last 25 runs) is the foundation; the coverage screen, rollback, and telemetry are all thin readers over it. The plugin half is inert until the layoutlab endpoint exists, so the endpoint is built and deployed first.

**Tech Stack:** PHP 8.0+/WP 5.9+, PHPUnit 13 with the repo's WP stub bootstrap; layoutlab is Next.js + Drizzle + Postgres, tested with `vitest run`.

**Spec:** `docs/superpowers/specs/2026-08-28-conversion-coverage-and-rollback-design.md`

## Global Constraints

- **Repos:** `PLUGIN_REPO` = `/Users/Lucas/Documents/JHMG-Local/jhmg-elementor-to-divi5` (branch `feat/coverage-and-rollback`, already created, already holds the spec commit). `SITE_REPO` = `/Users/Lucas/Documents/JHMG-Local/layoutlab` (branch `feat/coverage-endpoint` off `main`).
- **Deploy order:** the layoutlab endpoint ships BEFORE the plugin release, so no consented report is ever dropped. Tasks 1-4 (plugin, no telemetry) and Tasks 5-6 (site) are independent; Task 7 (plugin telemetry) assumes 5-6 are merged.
- **House rules:** TDD — write the failing test, watch it fail, then implement. Full suite plus `find plugin -name '*.php' -exec php -l {} \;` before every plugin commit. Never commit on a checked-out `main`.
- **Namespaces:** free plugin is `ElementorDivi5Converter\`, PSR-4-ish via `includes/helpers/class-autoloader.php`. `Foo\Bar\BazQux` resolves to `includes/foo/bar/class-baz-qux.php` (namespace segments lowercased into directories; class name kebab-cased). New namespaces `ElementorDivi5Converter\History` and `ElementorDivi5Converter\Telemetry` therefore need `includes/history/` and `includes/telemetry/`.
- **Text domain** on every user-facing string: `jhmg-converter-for-elementor-to-divi`.
- **Rollback trashes, never deletes.** `wp_trash_post()` only. Every post ID must be verified against the `_edc_import_source` post meta before being touched.
- **Telemetry sends distinct widget type NAMES only** — no counts, no versions, no site identifier, no URLs, no post content. Off by default.
- **Coverage `product` identifier** is `elementor-to-divi5`. It is deliberately NOT one of the licensing `PLUGIN_PRODUCTS` slugs.
- **wp.org release is 2.3.0.** 2.2.0 is never published; its changelog entries fold into the 2.3.0 section.

---

### Task 1: `ImportHistory` store

The foundation every later task reads. Import results currently live in a one-hour transient; this persists the parts that must outlive it.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/history/class-import-history.php`
- Test: `tests/ImportHistoryTest.php`

**Interfaces:**
- Produces (Tasks 2, 3, 4, 7 all depend on these exact names):

```php
namespace ElementorDivi5Converter\History;

class ImportHistory {
    const OPTION   = 'edc_import_history';
    const MAX_RUNS = 25;

    public function record( string $import_id, array $results ): void;
    public function all(): array;                          // newest first
    public function find( string $import_id ): ?array;
    public function mark_rolled_back( string $import_id ): void;
    public function coverage(): array;                     // ranked, see below
    public static function widget_types( array $results ): array; // flat, de-duplicated
}
```

A recorded run is:

```php
[
  'id' => string, 'at' => string /* 'Y-m-d H:i:s' UTC */,
  'post_ids' => int[], 'unsupported' => string[],
  'succeeded' => int, 'failed' => int, 'rolled_back' => bool,
]
```

`coverage()` returns, sorted by `runs` descending then `type` ascending:

```php
[ [ 'type' => string, 'runs' => int, 'last_seen' => string ], ... ]
```

Note the source shape: each result item carries `'unsupported' => [ ['id'=>?, 'elType'=>?, 'widgetType'=>?], ... ]` (see `ConverterEngine::$unsupportedWidgets`). Use `widgetType`, falling back to `elType` when `widgetType` is null, and skip entries where both are null.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/ImportHistoryTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\History\ImportHistory;

class ImportHistoryTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
    }

    private function result( bool $ok, int $post_id, array $types = [] ): array {
        return [
            'success'     => $ok,
            'post_id'     => $post_id,
            'unsupported' => array_map(
                fn( $t ) => [ 'id' => 'x', 'elType' => 'widget', 'widgetType' => $t ],
                $types
            ),
        ];
    }

    public function test_records_post_ids_counts_and_distinct_widget_types(): void {
        $h = new ImportHistory();
        $h->record( 'run1', [
            $this->result( true, 11, [ 'lottie', 'lottie' ] ),
            $this->result( true, 12, [ 'hotspot' ] ),
            $this->result( false, 0 ),
        ] );

        $run = $h->find( 'run1' );
        $this->assertSame( [ 11, 12 ], $run['post_ids'] );
        $this->assertSame( 2, $run['succeeded'] );
        $this->assertSame( 1, $run['failed'] );
        $this->assertSame( [ 'lottie', 'hotspot' ], $run['unsupported'] );
        $this->assertFalse( $run['rolled_back'] );
    }

    public function test_falls_back_to_eltype_when_widget_type_is_null(): void {
        $h = new ImportHistory();
        $h->record( 'r', [ [
            'success' => true, 'post_id' => 5,
            'unsupported' => [ [ 'id' => 'a', 'elType' => 'container', 'widgetType' => null ] ],
        ] ] );
        $this->assertSame( [ 'container' ], $h->find( 'r' )['unsupported'] );
    }

    public function test_newest_run_comes_first(): void {
        $h = new ImportHistory();
        $h->record( 'old', [ $this->result( true, 1 ) ] );
        $h->record( 'new', [ $this->result( true, 2 ) ] );
        $this->assertSame( 'new', $h->all()[0]['id'] );
    }

    public function test_prunes_to_the_most_recent_25_runs(): void {
        $h = new ImportHistory();
        for ( $i = 0; $i < 30; $i++ ) {
            $h->record( "run$i", [ $this->result( true, $i + 1 ) ] );
        }
        $all = $h->all();
        $this->assertCount( ImportHistory::MAX_RUNS, $all );
        $this->assertSame( 'run29', $all[0]['id'] );
        $this->assertNull( $h->find( 'run0' ), 'oldest runs are pruned' );
    }

    public function test_coverage_ranks_by_number_of_runs_a_type_appeared_in(): void {
        $h = new ImportHistory();
        $h->record( 'a', [ $this->result( true, 1, [ 'lottie', 'hotspot' ] ) ] );
        $h->record( 'b', [ $this->result( true, 2, [ 'lottie' ] ) ] );
        $h->record( 'c', [ $this->result( true, 3, [ 'lottie' ] ) ] );

        $coverage = $h->coverage();
        $this->assertSame( 'lottie', $coverage[0]['type'] );
        $this->assertSame( 3, $coverage[0]['runs'] );
        $this->assertSame( 'hotspot', $coverage[1]['type'] );
        $this->assertSame( 1, $coverage[1]['runs'] );
    }

    public function test_coverage_is_empty_when_everything_converted(): void {
        $h = new ImportHistory();
        $h->record( 'a', [ $this->result( true, 1 ) ] );
        $this->assertSame( [], $h->coverage() );
    }

    public function test_mark_rolled_back_flags_only_that_run(): void {
        $h = new ImportHistory();
        $h->record( 'a', [ $this->result( true, 1 ) ] );
        $h->record( 'b', [ $this->result( true, 2 ) ] );
        $h->mark_rolled_back( 'a' );
        $this->assertTrue( $h->find( 'a' )['rolled_back'] );
        $this->assertFalse( $h->find( 'b' )['rolled_back'] );
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `cd $PLUGIN_REPO && vendor/bin/phpunit --filter ImportHistoryTest`
Expected: errors — class `ImportHistory` not found.

- [ ] **Step 3: Implement the store**

```php
<?php
/**
 * Durable record of recent imports.
 *
 * Import results otherwise live only in a one-hour transient. The coverage
 * screen needs unsupported widget types across runs, and rollback needs the
 * post IDs a run created long after that hour is up, so both read from here.
 */

namespace ElementorDivi5Converter\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImportHistory {

    const OPTION   = 'edc_import_history';

    /** Unbounded growth in wp_options is a common cause of slow-site reports. */
    const MAX_RUNS = 25;

    public function record( string $import_id, array $results ): void {
        $post_ids = [];
        foreach ( $results as $r ) {
            if ( ! empty( $r['success'] ) && ! empty( $r['post_id'] ) ) {
                $post_ids[] = (int) $r['post_id'];
            }
        }

        $succeeded = count( array_filter( $results, static fn( $r ) => ! empty( $r['success'] ) ) );

        $runs = $this->all();
        array_unshift( $runs, [
            'id'          => $import_id,
            'at'          => gmdate( 'Y-m-d H:i:s' ),
            'post_ids'    => $post_ids,
            'unsupported' => self::widget_types( $results ),
            'succeeded'   => $succeeded,
            'failed'      => count( $results ) - $succeeded,
            'rolled_back' => false,
        ] );

        update_option( self::OPTION, array_slice( $runs, 0, self::MAX_RUNS ) );
    }

    /** @return array Newest first. */
    public function all(): array {
        $runs = get_option( self::OPTION, [] );
        return is_array( $runs ) ? $runs : [];
    }

    public function find( string $import_id ): ?array {
        foreach ( $this->all() as $run ) {
            if ( ( $run['id'] ?? '' ) === $import_id ) {
                return $run;
            }
        }
        return null;
    }

    public function mark_rolled_back( string $import_id ): void {
        $runs = $this->all();
        foreach ( $runs as $i => $run ) {
            if ( ( $run['id'] ?? '' ) === $import_id ) {
                $runs[ $i ]['rolled_back'] = true;
            }
        }
        update_option( self::OPTION, $runs );
    }

    /**
     * Widget types ranked by how many runs each appeared in — a type that
     * breaks every import matters more than one that broke a single page.
     *
     * @return array<int, array{type:string, runs:int, last_seen:string}>
     */
    public function coverage(): array {
        $seen = [];
        foreach ( $this->all() as $run ) {
            foreach ( $run['unsupported'] ?? [] as $type ) {
                if ( ! isset( $seen[ $type ] ) ) {
                    $seen[ $type ] = [ 'type' => $type, 'runs' => 0, 'last_seen' => '' ];
                }
                $seen[ $type ]['runs']++;
                if ( ( $run['at'] ?? '' ) > $seen[ $type ]['last_seen'] ) {
                    $seen[ $type ]['last_seen'] = $run['at'] ?? '';
                }
            }
        }

        $coverage = array_values( $seen );
        usort( $coverage, static function ( $a, $b ) {
            return $b['runs'] <=> $a['runs'] ?: strcmp( $a['type'], $b['type'] );
        } );

        return $coverage;
    }

    /**
     * Flatten every result item's unsupported entries into a de-duplicated
     * list of type names, preserving first-seen order.
     */
    public static function widget_types( array $results ): array {
        $types = [];
        foreach ( $results as $r ) {
            foreach ( $r['unsupported'] ?? [] as $entry ) {
                $type = $entry['widgetType'] ?? $entry['elType'] ?? null;
                if ( is_string( $type ) && $type !== '' && ! in_array( $type, $types, true ) ) {
                    $types[] = $type;
                }
            }
        }
        return $types;
    }
}
```

- [ ] **Step 4: Run the test and confirm it passes**

Run: `vendor/bin/phpunit --filter ImportHistoryTest`
Expected: 7 tests pass.

- [ ] **Step 5: Full suite, lint, commit**

```bash
vendor/bin/phpunit
find plugin -name '*.php' -exec php -l {} \; | grep -v "No syntax errors" || true
git add plugin/jhmg-converter-for-elementor-to-divi/includes/history/class-import-history.php tests/ImportHistoryTest.php
git commit -m "feat(free): durable import history store, capped at 25 runs"
```

---

### Task 2: Record every import into the history

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-admin-page.php` (the `handle_import()` block that calls `ReviewPrompt::record_run()`, immediately before `set_transient( 'edc_batch_' . $import_id, ... )`)
- Test: `tests/ImportHistoryTest.php` (extend)

**Interfaces:**
- Consumes: `ImportHistory::record()`, `ImportHistory::find()` from Task 1.
- Produces: nothing new; after this task every import appears in history.

Note `$import_id` is currently generated AFTER `record_run()`. It must be generated before the history call, since the history is keyed by it.

- [ ] **Step 1: Write the failing test**

Append to `tests/ImportHistoryTest.php`:

```php
    public function test_admin_page_records_history_keyed_by_import_id(): void {
        $src = (string) file_get_contents(
            __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-admin-page.php'
        );
        $this->assertStringContainsString( 'ImportHistory', $src );
        $this->assertMatchesRegularExpression(
            '/\$import_id\s*=\s*\$this->generate_import_id\(\);[\s\S]{0,400}?->record\(\s*\$import_id\s*,\s*\$results\s*\)/',
            $src,
            'history must be recorded with the same import_id the results transient uses'
        );
    }
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `vendor/bin/phpunit --filter test_admin_page_records_history`
Expected: FAIL — `ImportHistory` not referenced in the admin page.

- [ ] **Step 3: Wire it in**

In `class-admin-page.php`, add the import near the existing `use` statement at the top:

```php
use ElementorDivi5Converter\History\ImportHistory;
```

Then replace this block:

```php
        // Counted here rather than on the results screen: that view renders from
        // a transient keyed in the URL, so refreshing it would inflate the total.
        ( new ReviewPrompt() )->record_run( $results );

        $import_id = $this->generate_import_id();
        set_transient( 'edc_batch_' . $import_id, $results, HOUR_IN_SECONDS );
```

with:

```php
        // Counted here rather than on the results screen: that view renders from
        // a transient keyed in the URL, so refreshing it would inflate the total.
        ( new ReviewPrompt() )->record_run( $results );

        $import_id = $this->generate_import_id();

        // Durable record: the transient below expires in an hour, but the
        // coverage screen and rollback both need this run afterwards.
        ( new ImportHistory() )->record( $import_id, $results );

        set_transient( 'edc_batch_' . $import_id, $results, HOUR_IN_SECONDS );
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit --filter ImportHistoryTest`
Expected: 8 tests pass.

- [ ] **Step 5: Full suite, lint, commit**

```bash
vendor/bin/phpunit
find plugin -name '*.php' -exec php -l {} \; | grep -v "No syntax errors" || true
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-admin-page.php tests/ImportHistoryTest.php
git commit -m "feat(free): record every import into the durable history"
```

---

### Task 3: Coverage screen

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-coverage-panel.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-admin-page.php` (call the panel at the end of `render_landing()`, just before its closing `}`)
- Test: `tests/CoveragePanelTest.php`

**Interfaces:**
- Consumes: `ImportHistory::coverage()`, `ImportHistory::all()` from Task 1.
- Produces:

```php
namespace ElementorDivi5Converter\Admin;

class CoveragePanel {
    public function __construct( ?\ElementorDivi5Converter\History\ImportHistory $history = null );
    public function markup(): string;   // '' when there is no history at all
    public function render(): void;
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/CoveragePanelTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\CoveragePanel;
use ElementorDivi5Converter\History\ImportHistory;

class CoveragePanelTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_caps']    = true;
    }

    private function record( ImportHistory $h, string $id, array $types ): void {
        $h->record( $id, [ [
            'success' => true, 'post_id' => 1,
            'unsupported' => array_map(
                fn( $t ) => [ 'id' => 'x', 'elType' => 'widget', 'widgetType' => $t ],
                $types
            ),
        ] ] );
    }

    public function test_renders_nothing_before_any_import(): void {
        $this->assertSame( '', ( new CoveragePanel( new ImportHistory() ) )->markup() );
    }

    public function test_lists_unsupported_types_ranked(): void {
        $h = new ImportHistory();
        $this->record( $h, 'a', [ 'lottie', 'hotspot' ] );
        $this->record( $h, 'b', [ 'lottie' ] );

        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'lottie', $html );
        $this->assertStringContainsString( 'hotspot', $html );
        $this->assertLessThan(
            strpos( $html, 'hotspot' ),
            strpos( $html, 'lottie' ),
            'the type that broke more runs must be listed first'
        );
    }

    public function test_celebrates_full_coverage(): void {
        $h = new ImportHistory();
        $this->record( $h, 'a', [] );
        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'Everything converted', $html );
    }

    public function test_escapes_widget_type_names(): void {
        $h = new ImportHistory();
        $this->record( $h, 'a', [ '<script>alert(1)</script>' ] );
        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `vendor/bin/phpunit --filter CoveragePanelTest`
Expected: errors — class `CoveragePanel` not found.

- [ ] **Step 3: Implement the panel**

```php
<?php
/**
 * Read-only summary of what this site's imports could not convert.
 *
 * Lives below the import form on the plugin's existing Tools page rather than
 * behind its own menu entry — it is a summary, not a destination.
 */

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\History\ImportHistory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CoveragePanel {

    private ImportHistory $history;

    public function __construct( ?ImportHistory $history = null ) {
        $this->history = $history ?? new ImportHistory();
    }

    public function render(): void {
        // Every interpolation in markup() is escaped at the point of use.
        echo $this->markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function markup(): string {
        $runs = $this->history->all();
        if ( empty( $runs ) ) {
            return '';
        }

        $coverage = $this->history->coverage();

        if ( empty( $coverage ) ) {
            return '<div class="edc-card edc-card--success"><h2>'
                . esc_html__( 'Conversion coverage', 'jhmg-converter-for-elementor-to-divi' )
                . '</h2><p>'
                . esc_html__( 'Everything converted. No unsupported widgets across your recent imports.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p></div>';
        }

        $rows = '';
        foreach ( $coverage as $item ) {
            $rows .= sprintf(
                '<tr><td><code>%1$s</code></td><td>%2$d</td><td>%3$s</td></tr>',
                esc_html( $item['type'] ),
                (int) $item['runs'],
                esc_html( $item['last_seen'] )
            );
        }

        return sprintf(
            '<div class="edc-card"><h2>%1$s</h2><p class="description">%2$s</p>'
            . '<table class="widefat striped"><thead><tr><th>%3$s</th><th>%4$s</th><th>%5$s</th></tr></thead>'
            . '<tbody>%6$s</tbody></table></div>',
            esc_html__( 'Conversion coverage', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Elementor widgets from your recent imports that have no Divi 5 equivalent yet. These need rebuilding by hand.', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Widget', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Imports affected', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Last seen', 'jhmg-converter-for-elementor-to-divi' ),
            $rows
        );
    }
}
```

- [ ] **Step 4: Call it from the landing page**

At the very end of `AdminPage::render_landing()`, immediately before the method's closing brace, add:

```php
        ( new CoveragePanel() )->render();
```

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit --filter CoveragePanelTest`
Expected: 4 tests pass.

- [ ] **Step 6: Full suite, lint, commit**

```bash
vendor/bin/phpunit
find plugin -name '*.php' -exec php -l {} \; | grep -v "No syntax errors" || true
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-coverage-panel.php \
        plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-admin-page.php \
        tests/CoveragePanelTest.php
git commit -m "feat(free): conversion coverage panel ranking unsupported widgets"
```

---

### Task 4: Rollback an import

The first destructive operation this plugin performs. The ownership guard is the part that matters most.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/history/class-import-rollback.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-plugin.php` (register in the `is_admin()` block of `register_hooks()`, alongside `PriceDropNotice` and `ReviewPrompt`)
- Modify: `tests/bootstrap.php` (add a `wp_trash_post` stub)
- Test: `tests/ImportRollbackTest.php`

**Interfaces:**
- Consumes: `ImportHistory::find()`, `ImportHistory::mark_rolled_back()` from Task 1.
- Produces:

```php
namespace ElementorDivi5Converter\History;

class ImportRollback {
    const QUERY_ACTION = 'edc_rollback';
    const NONCE_ACTION = 'edc_rollback_import';

    public function __construct( ?ImportHistory $history = null );
    public function init(): void;
    public function rollback( string $import_id ): array; // ['trashed'=>int,'skipped'=>int]
    public function maybe_handle_request(): void;
}
```

- [ ] **Step 1: Add the `wp_trash_post` stub**

In `tests/bootstrap.php`, immediately before the `if ( file_exists( __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi/...' )` require block, add:

```php
if ( ! function_exists( 'wp_trash_post' ) ) {
    $GLOBALS['__test_trashed'] = [];

    function wp_trash_post( int $post_id ) {
        $GLOBALS['__test_trashed'][] = $post_id;
        return (object) [ 'ID' => $post_id ];
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/ImportRollbackTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\History\ImportRollback;

class ImportRollbackTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options']   = [];
        $GLOBALS['__test_postmeta']  = [];
        $GLOBALS['__test_trashed']   = [];
        $GLOBALS['__test_caps']      = true;
        $_GET                        = [];
    }

    private function seed( array $post_ids, array $owned ): ImportHistory {
        $h = new ImportHistory();
        $h->record( 'run1', array_map(
            fn( $id ) => [ 'success' => true, 'post_id' => $id, 'unsupported' => [] ],
            $post_ids
        ) );
        foreach ( $owned as $id ) {
            update_post_meta( $id, '_edc_import_source', 'file_upload' );
        }
        return $h;
    }

    public function test_trashes_posts_the_plugin_created(): void {
        $h = $this->seed( [ 10, 11 ], [ 10, 11 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( [ 10, 11 ], $GLOBALS['__test_trashed'] );
        $this->assertSame( 2, $out['trashed'] );
        $this->assertSame( 0, $out['skipped'] );
    }

    public function test_skips_posts_the_plugin_does_not_own(): void {
        // 11 has no _edc_import_source meta — the user may have replaced it.
        $h = $this->seed( [ 10, 11 ], [ 10 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'run1' );

        $this->assertSame( [ 10 ], $GLOBALS['__test_trashed'], 'must never touch a post it did not create' );
        $this->assertSame( 1, $out['trashed'] );
        $this->assertSame( 1, $out['skipped'] );
    }

    public function test_marks_the_run_rolled_back(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        ( new ImportRollback( $h ) )->rollback( 'run1' );
        $this->assertTrue( $h->find( 'run1' )['rolled_back'] );
    }

    public function test_unknown_run_does_nothing(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $out = ( new ImportRollback( $h ) )->rollback( 'nope' );
        $this->assertSame( [], $GLOBALS['__test_trashed'] );
        $this->assertSame( 0, $out['trashed'] );
    }

    public function test_request_requires_a_valid_nonce(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = 'forged';
        ( new ImportRollback( $h ) )->maybe_handle_request();
        $this->assertSame( [], $GLOBALS['__test_trashed'], 'a forged nonce must not trash anything' );
    }

    public function test_request_requires_manage_options(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $GLOBALS['__test_caps'] = false;
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );
        ( new ImportRollback( $h ) )->maybe_handle_request();
        $this->assertSame( [], $GLOBALS['__test_trashed'] );
    }

    public function test_valid_request_performs_the_rollback(): void {
        $h = $this->seed( [ 10 ], [ 10 ] );
        $_GET[ ImportRollback::QUERY_ACTION ] = 'run1';
        $_GET['_wpnonce'] = wp_create_nonce( ImportRollback::NONCE_ACTION );
        ( new ImportRollback( $h ) )->maybe_handle_request();
        $this->assertSame( [ 10 ], $GLOBALS['__test_trashed'] );
    }

    public function test_uses_trash_not_delete(): void {
        $src = (string) file_get_contents(
            __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi/includes/history/class-import-rollback.php'
        );
        $this->assertStringContainsString( 'wp_trash_post', $src );
        $this->assertStringNotContainsString( 'wp_delete_post', $src );
    }
}
```

- [ ] **Step 3: Run it and confirm it fails**

Run: `vendor/bin/phpunit --filter ImportRollbackTest`
Expected: errors — class `ImportRollback` not found.

- [ ] **Step 4: Implement the rollback**

```php
<?php
/**
 * Undo an import by moving the posts it created to the trash.
 *
 * Two deliberate safety properties:
 *  - It trashes, never deletes. An undo that destroys content is not an undo;
 *    WP's trash is the user's second chance.
 *  - It only touches posts still carrying the `_edc_import_source` meta this
 *    plugin wrote. A post the user has since replaced or adopted by hand is
 *    skipped, so a stale batch record can never sweep away real work.
 */

namespace ElementorDivi5Converter\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImportRollback {

    const QUERY_ACTION = 'edc_rollback';
    const NONCE_ACTION = 'edc_rollback_import';

    private ImportHistory $history;

    public function __construct( ?ImportHistory $history = null ) {
        $this->history = $history ?? new ImportHistory();
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_request' ] );
    }

    /** @return array{trashed:int, skipped:int} */
    public function rollback( string $import_id ): array {
        $run = $this->history->find( $import_id );

        if ( $run === null ) {
            return [ 'trashed' => 0, 'skipped' => 0 ];
        }

        $trashed = 0;
        $skipped = 0;

        foreach ( $run['post_ids'] ?? [] as $post_id ) {
            if ( get_post_meta( (int) $post_id, '_edc_import_source', true ) === '' ) {
                $skipped++;
                continue;
            }

            wp_trash_post( (int) $post_id );
            $trashed++;
        }

        $this->history->mark_rolled_back( $import_id );

        return [ 'trashed' => $trashed, 'skipped' => $skipped ];
    }

    public function maybe_handle_request(): void {
        if ( empty( $_GET[ self::QUERY_ACTION ] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        $this->rollback( sanitize_key( wp_unslash( $_GET[ self::QUERY_ACTION ] ) ) );
    }
}
```

- [ ] **Step 5: Write the failing test for the Undo control**

Without this the rollback is only reachable by hand-crafting a URL. Append to `tests/CoveragePanelTest.php`:

```php
    public function test_lists_recent_imports_with_an_undo_control(): void {
        $h = new ImportHistory();
        $h->record( 'run1', [ [ 'success' => true, 'post_id' => 7, 'unsupported' => [] ] ] );

        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'Recent imports', $html );
        $this->assertStringContainsString( \ElementorDivi5Converter\History\ImportRollback::QUERY_ACTION, $html );
        $this->assertStringContainsString( 'run1', $html );
    }

    public function test_rolled_back_runs_show_no_undo_control(): void {
        $h = new ImportHistory();
        $h->record( 'run1', [ [ 'success' => true, 'post_id' => 7, 'unsupported' => [] ] ] );
        $h->mark_rolled_back( 'run1' );

        $html = ( new CoveragePanel( $h ) )->markup();
        $this->assertStringContainsString( 'Undone', $html );
        $this->assertStringNotContainsString(
            \ElementorDivi5Converter\History\ImportRollback::QUERY_ACTION,
            $html,
            'an already-undone run must not offer Undo again'
        );
    }
```

Run: `vendor/bin/phpunit --filter CoveragePanelTest` — expect FAIL.

- [ ] **Step 6: Render the recent-imports table**

Add to the top of `class-coverage-panel.php`:

```php
use ElementorDivi5Converter\History\ImportRollback;
```

Add this method to `CoveragePanel`:

```php
    /** Recent runs, each undoable while it still owns the posts it created. */
    private function runs_table(): string {
        $rows = '';

        foreach ( $this->history->all() as $run ) {
            $undo = ! empty( $run['rolled_back'] )
                ? esc_html__( 'Undone', 'jhmg-converter-for-elementor-to-divi' )
                : sprintf(
                    '<a href="%1$s" class="button button-small">%2$s</a>',
                    esc_url(
                        add_query_arg( ImportRollback::QUERY_ACTION, $run['id'] )
                        . '&_wpnonce=' . wp_create_nonce( ImportRollback::NONCE_ACTION )
                    ),
                    esc_html__( 'Undo', 'jhmg-converter-for-elementor-to-divi' )
                );

            $rows .= sprintf(
                '<tr><td>%1$s</td><td>%2$d</td><td>%3$s</td></tr>',
                esc_html( $run['at'] ?? '' ),
                count( $run['post_ids'] ?? [] ),
                $undo
            );
        }

        return sprintf(
            '<div class="edc-card"><h2>%1$s</h2><p class="description">%2$s</p>'
            . '<table class="widefat striped"><thead><tr><th>%3$s</th><th>%4$s</th><th></th></tr></thead>'
            . '<tbody>%5$s</tbody></table></div>',
            esc_html__( 'Recent imports', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Undo moves the pages an import created to the trash. Pages you have edited since are left alone.', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'When', 'jhmg-converter-for-elementor-to-divi' ),
            esc_html__( 'Pages', 'jhmg-converter-for-elementor-to-divi' ),
            $rows
        );
    }
```

Then append `. $this->runs_table()` to BOTH return statements in `markup()` — the full-coverage case and the populated case — so the recent-imports table shows whenever there is any history.

- [ ] **Step 7: Register the rollback handler**

In `includes/helpers/class-plugin.php`, inside the `is_admin()` block of `register_hooks()`, after the `ReviewPrompt` line:

```php
            ( new \ElementorDivi5Converter\History\ImportRollback() )->init();
```

- [ ] **Step 8: Run the tests**

Run: `vendor/bin/phpunit --filter 'ImportRollbackTest|CoveragePanelTest'`
Expected: 8 rollback tests and 6 panel tests pass.

- [ ] **Step 9: Full suite, lint, commit**

```bash
vendor/bin/phpunit
find plugin -name '*.php' -exec php -l {} \; | grep -v "No syntax errors" || true
git add plugin/jhmg-converter-for-elementor-to-divi/includes/history/class-import-rollback.php \
        plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-coverage-panel.php \
        plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-plugin.php \
        tests/ImportRollbackTest.php tests/CoveragePanelTest.php tests/bootstrap.php
git commit -m "feat(free): roll back an import by trashing only the posts it created"
```

---

### Task 5: `plugin_coverage_reports` table (SITE_REPO)

**Files:**
- Modify: `db/schema.ts` (append after `pluginReleases`, around line 274)
- Test: `tests/coverage-schema.test.ts`

**Interfaces:**
- Produces (Task 6 imports this):

```ts
export const pluginCoverageReports: PgTable; // columns: id, product, widgetTypes, receivedAt
```

- [ ] **Step 1: Write the failing test**

```ts
// tests/coverage-schema.test.ts
import { describe, it, expect } from 'vitest';
import { pluginCoverageReports } from '@/db/schema';
import { getTableConfig } from 'drizzle-orm/pg-core';

describe('plugin_coverage_reports', () => {
  it('stores anonymous per-report widget type lists', () => {
    const t = getTableConfig(pluginCoverageReports);
    expect(t.name).toBe('plugin_coverage_reports');
    const cols = t.columns.map((c) => c.name).sort();
    expect(cols).toEqual(['id', 'product', 'received_at', 'widget_types']);
  });

  it('carries no column that could identify a site', () => {
    const cols = getTableConfig(pluginCoverageReports).columns.map((c) => c.name);
    for (const forbidden of ['site_url', 'site_hash', 'ip', 'user_id', 'email', 'license_key']) {
      expect(cols).not.toContain(forbidden);
    }
  });
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `cd $SITE_REPO && npx vitest run tests/coverage-schema.test.ts`
Expected: FAIL — `pluginCoverageReports` is not exported.

- [ ] **Step 3: Add the table**

Append to `db/schema.ts`:

```ts
// Anonymous conversion-coverage reports from the free converter plugins.
// Deliberately carries nothing that identifies a site: consent is opt-in and
// the payload is a list of Elementor widget type names and nothing else.
export const pluginCoverageReports = pgTable('plugin_coverage_reports', {
  id: text('id').primaryKey(),
  product: text('product').notNull(), // 'elementor-to-divi5' | 'divi-to-elementor'
  widgetTypes: jsonb('widget_types').notNull().$type<string[]>(),
  receivedAt: timestamp('received_at').notNull().defaultNow(),
}, (t) => ({
  productIdx: index('plugin_coverage_product_idx').on(t.product),
  receivedIdx: index('plugin_coverage_received_idx').on(t.receivedAt),
}));
```

- [ ] **Step 4: Run the test and generate the migration**

```bash
npx vitest run tests/coverage-schema.test.ts   # expect PASS
npm run db:generate
```

- [ ] **Step 5: Commit**

```bash
git checkout -b feat/coverage-endpoint
git add db/schema.ts db/migrations tests/coverage-schema.test.ts
git commit -m "feat(coverage): plugin_coverage_reports table for anonymous widget gap reports"
```

---

### Task 6: `POST /api/plugin/coverage` (SITE_REPO)

**Files:**
- Create: `app/api/plugin/coverage/route.ts`
- Create: `lib/coverage/schema.ts`
- Test: `tests/coverage-route.test.ts`

**Interfaces:**
- Consumes: `pluginCoverageReports` (Task 5); `rateLimit` from `@/lib/rate-limit`; `db` from `@/db/client`.
- Produces: `export const coveragePayloadSchema` in `lib/coverage/schema.ts`; `export async function POST(req: Request): Promise<Response>`.

Accepted body: `{ product: 'elementor-to-divi5' | 'divi-to-elementor', widget_types: string[] }` — at most 100 entries, each 1-64 chars. Endpoint is public and unauthenticated because a GPL plugin cannot hold a shared secret.

- [ ] **Step 1: Write the failing test**

```ts
// tests/coverage-route.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest';

const insertValues = vi.fn().mockResolvedValue(undefined);
vi.mock('@/db/client', () => ({
  db: {
    insert: () => ({ values: insertValues }),
    select: () => ({ from: () => ({ where: () => Promise.resolve([{ count: 0 }]) }) }),
  },
}));

import { POST } from '@/app/api/plugin/coverage/route';

function post(body: unknown) {
  return new Request('http://test/api/plugin/coverage', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: typeof body === 'string' ? body : JSON.stringify(body),
  });
}

describe('POST /api/plugin/coverage', () => {
  beforeEach(() => insertValues.mockClear());

  it('accepts a valid report', async () => {
    const res = await POST(post({ product: 'elementor-to-divi5', widget_types: ['lottie'] }));
    expect(res.status).toBe(200);
    expect(insertValues).toHaveBeenCalledOnce();
  });

  it('400 on an unknown product', async () => {
    const res = await POST(post({ product: 'not-a-product', widget_types: ['lottie'] }));
    expect(res.status).toBe(400);
    expect(insertValues).not.toHaveBeenCalled();
  });

  it('400 on malformed json', async () => {
    expect((await POST(post('{nope'))).status).toBe(400);
  });

  it('400 when widget_types is oversized', async () => {
    const res = await POST(post({
      product: 'elementor-to-divi5',
      widget_types: Array.from({ length: 101 }, (_, i) => `w${i}`),
    }));
    expect(res.status).toBe(400);
  });

  it('400 when a widget type string is too long', async () => {
    const res = await POST(post({ product: 'elementor-to-divi5', widget_types: ['x'.repeat(65)] }));
    expect(res.status).toBe(400);
  });

  it('de-duplicates repeated types before storing', async () => {
    await POST(post({ product: 'elementor-to-divi5', widget_types: ['lottie', 'lottie', 'hotspot'] }));
    expect(insertValues.mock.calls[0][0].widgetTypes).toEqual(['lottie', 'hotspot']);
  });
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `npx vitest run tests/coverage-route.test.ts`
Expected: FAIL — module not found.

- [ ] **Step 3: Write the payload schema**

```ts
// lib/coverage/schema.ts
import { z } from 'zod';

// Anonymous by construction: a product identifier and a list of Elementor
// widget type names. Nothing here can identify a site or a person.
export const coveragePayloadSchema = z.object({
  product: z.enum(['elementor-to-divi5', 'divi-to-elementor']),
  widget_types: z.array(z.string().min(1).max(64)).min(1).max(100),
});

export type CoveragePayload = z.infer<typeof coveragePayloadSchema>;
```

- [ ] **Step 4: Write the route**

```ts
// app/api/plugin/coverage/route.ts
import { NextResponse } from 'next/server';
import { randomUUID } from 'node:crypto';
import { sql, gte } from 'drizzle-orm';
import { db } from '@/db/client';
import { pluginCoverageReports } from '@/db/schema';
import { rateLimit } from '@/lib/rate-limit';
import { coveragePayloadSchema } from '@/lib/coverage/schema';

// Roughly 50x plausible honest volume at current install counts. Past it we
// accept and discard rather than erroring, so honest clients never see a
// failure and a bad actor can inflate noise but not the storage bill.
const DAILY_CAP = 5000;

export async function POST(req: Request): Promise<Response> {
  const ip = req.headers.get('x-forwarded-for')?.split(',')[0]?.trim() ?? 'unknown';
  if (!rateLimit(`plugin-coverage:${ip}`, { limit: 5, windowMs: 60 * 60_000 }).ok) {
    return NextResponse.json({ error: 'rate_limited' }, { status: 429 });
  }

  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: 'invalid_json' }, { status: 400 });
  }

  const parsed = coveragePayloadSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: 'invalid_request' }, { status: 400 });
  }

  const since = new Date(Date.now() - 24 * 60 * 60_000);
  const [{ count }] = await db
    .select({ count: sql<number>`count(*)::int` })
    .from(pluginCoverageReports)
    .where(gte(pluginCoverageReports.receivedAt, since));

  if (count >= DAILY_CAP) {
    return NextResponse.json({ ok: true }, { status: 200 });
  }

  await db.insert(pluginCoverageReports).values({
    id: randomUUID(),
    product: parsed.data.product,
    widgetTypes: [...new Set(parsed.data.widget_types)],
  });

  return NextResponse.json({ ok: true }, { status: 200 });
}
```

- [ ] **Step 5: Run the tests**

Run: `npx vitest run tests/coverage-route.test.ts`
Expected: 6 tests pass.

- [ ] **Step 6: Full suite and commit**

```bash
npm run test
git add app/api/plugin/coverage/route.ts lib/coverage/schema.ts tests/coverage-route.test.ts
git commit -m "feat(coverage): public anonymous coverage-report endpoint with daily cap"
```

- [ ] **Step 7: Merge and deploy before Task 7 ships**

```bash
git checkout main && git merge --no-ff feat/coverage-endpoint -m "Merge feat/coverage-endpoint: anonymous plugin coverage reporting"
npm run db:migrate   # against prod, operator-confirmed
git push origin main # deploys
curl -s -X POST https://divi5lab.com/api/plugin/coverage \
  -H 'content-type: application/json' \
  -d '{"product":"elementor-to-divi5","widget_types":["smoke-test"]}'
# expect {"ok":true}
```

---

### Task 7: Consent-gated telemetry client (PLUGIN_REPO)

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/telemetry/class-coverage-telemetry.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-coverage-panel.php` (append the consent form to `markup()`)
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-plugin.php` (register in the `is_admin()` block)
- Test: `tests/CoverageTelemetryTest.php`

**Interfaces:**
- Consumes: `ImportHistory::coverage()` from Task 1.
- Produces:

```php
namespace ElementorDivi5Converter\Telemetry;

class CoverageTelemetry {
    const CONSENT_OPTION   = 'edc_telemetry_consent';
    const LAST_SENT_OPTION = 'edc_telemetry_last_sent';
    const QUERY_ACTION     = 'edc_telemetry_consent_set';
    const NONCE_ACTION     = 'edc_telemetry_consent';
    const PRODUCT          = 'elementor-to-divi5';
    const ENDPOINT         = 'https://divi5lab.com/api/plugin/coverage';
    const INTERVAL_DAYS    = 7;

    public function __construct( ?ImportHistory $history = null, ?string $today = null );
    public function init(): void;
    public function has_consent(): bool;
    public function due(): bool;
    public function payload(): array;      // ['product'=>string,'widget_types'=>string[]]
    public function maybe_send(): void;
    public function maybe_handle_consent(): void;
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/CoverageTelemetryTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\History\ImportHistory;
use ElementorDivi5Converter\Telemetry\CoverageTelemetry;

class CoverageTelemetryTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_caps']    = true;
        $_GET                      = [];
        edc_test_reset_hooks();
        edc_test_http_queue( [] );
    }

    private function seeded(): ImportHistory {
        $h = new ImportHistory();
        $h->record( 'a', [ [
            'success' => true, 'post_id' => 1,
            'unsupported' => [ [ 'id' => 'x', 'elType' => 'widget', 'widgetType' => 'lottie' ] ],
        ] ] );
        return $h;
    }

    public function test_sends_nothing_without_consent(): void {
        $t = new CoverageTelemetry( $this->seeded(), '2026-09-01' );
        $this->assertFalse( $t->has_consent() );
        $t->maybe_send();
        $this->assertSame( '', (string) get_option( CoverageTelemetry::LAST_SENT_OPTION, '' ) );
    }

    public function test_payload_is_names_only(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $payload = ( new CoverageTelemetry( $this->seeded(), '2026-09-01' ) )->payload();

        $this->assertSame( [ 'product', 'widget_types' ], array_keys( $payload ) );
        $this->assertSame( [ 'lottie' ], $payload['widget_types'] );
        $this->assertSame( 'elementor-to-divi5', $payload['product'] );
    }

    public function test_sends_once_consented_and_records_the_date(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $t = new CoverageTelemetry( $this->seeded(), '2026-09-01' );
        $this->assertTrue( $t->due() );
        $t->maybe_send();
        $this->assertSame( '2026-09-01', get_option( CoverageTelemetry::LAST_SENT_OPTION ) );
    }

    public function test_throttled_to_one_report_a_week(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        update_option( CoverageTelemetry::LAST_SENT_OPTION, '2026-09-01' );

        $this->assertFalse( ( new CoverageTelemetry( $this->seeded(), '2026-09-05' ) )->due() );
        $this->assertTrue( ( new CoverageTelemetry( $this->seeded(), '2026-09-09' ) )->due() );
    }

    public function test_sends_nothing_when_there_is_no_gap_to_report(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $empty = new ImportHistory();
        $empty->record( 'a', [ [ 'success' => true, 'post_id' => 1, 'unsupported' => [] ] ] );

        ( new CoverageTelemetry( $empty, '2026-09-01' ) )->maybe_send();
        $this->assertSame( '', (string) get_option( CoverageTelemetry::LAST_SENT_OPTION, '' ) );
    }

    public function test_consent_toggle_requires_nonce_and_capability(): void {
        $t = new CoverageTelemetry( $this->seeded(), '2026-09-01' );

        $_GET[ CoverageTelemetry::QUERY_ACTION ] = '1';
        $_GET['_wpnonce'] = 'forged';
        $t->maybe_handle_consent();
        $this->assertFalse( $t->has_consent() );

        $_GET['_wpnonce'] = wp_create_nonce( CoverageTelemetry::NONCE_ACTION );
        $GLOBALS['__test_caps'] = false;
        $t->maybe_handle_consent();
        $this->assertFalse( $t->has_consent() );

        $GLOBALS['__test_caps'] = true;
        $t->maybe_handle_consent();
        $this->assertTrue( $t->has_consent() );
    }

    public function test_payload_carries_no_identifying_field(): void {
        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $src = (string) file_get_contents(
            __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi/includes/telemetry/class-coverage-telemetry.php'
        );
        foreach ( [ 'home_url', 'site_url', 'get_bloginfo', 'wp_get_current_user', 'md5' ] as $forbidden ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $src,
                "telemetry must send nothing that could identify a site (found: $forbidden)"
            );
        }
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `vendor/bin/phpunit --filter CoverageTelemetryTest`
Expected: errors — class `CoverageTelemetry` not found.

- [ ] **Step 3: Implement the client**

```php
<?php
/**
 * Opt-in, anonymous report of Elementor widgets this site could not convert.
 *
 * Sends widget type NAMES only — no counts, no versions, no site identifier,
 * no URLs, no post content. Off by default; nothing leaves the site until the
 * user ticks the box on the coverage panel.
 *
 * Distinct names rather than counts is deliberate: each site then contributes
 * at most one vote per widget type per weekly report, so the resulting ranking
 * reflects what most users need rather than whoever converts most.
 */

namespace ElementorDivi5Converter\Telemetry;

use ElementorDivi5Converter\History\ImportHistory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CoverageTelemetry {

    const CONSENT_OPTION   = 'edc_telemetry_consent';
    const LAST_SENT_OPTION = 'edc_telemetry_last_sent';
    const QUERY_ACTION     = 'edc_telemetry_consent_set';
    const NONCE_ACTION     = 'edc_telemetry_consent';
    const PRODUCT          = 'elementor-to-divi5';
    const ENDPOINT         = 'https://divi5lab.com/api/plugin/coverage';
    const INTERVAL_DAYS    = 7;

    private ImportHistory $history;
    private string $today;

    public function __construct( ?ImportHistory $history = null, ?string $today = null ) {
        $this->history = $history ?? new ImportHistory();
        $this->today   = $today ?? gmdate( 'Y-m-d' );
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_consent' ] );
        // Never during a conversion: a slow endpoint must not delay the work
        // the user actually came here to do.
        add_action( 'admin_init', [ $this, 'maybe_send' ] );
    }

    public function has_consent(): bool {
        return (string) get_option( self::CONSENT_OPTION, '' ) === '1';
    }

    public function due(): bool {
        $last = (string) get_option( self::LAST_SENT_OPTION, '' );

        if ( $last === '' ) {
            return true;
        }

        return $this->today >= gmdate( 'Y-m-d', strtotime( $last . ' +' . self::INTERVAL_DAYS . ' days' ) );
    }

    /** @return array{product:string, widget_types:string[]} */
    public function payload(): array {
        return [
            'product'      => self::PRODUCT,
            'widget_types' => array_column( $this->history->coverage(), 'type' ),
        ];
    }

    public function maybe_send(): void {
        if ( ! $this->has_consent() || ! $this->due() ) {
            return;
        }

        $payload = $this->payload();

        if ( empty( $payload['widget_types'] ) ) {
            return;
        }

        wp_remote_post( self::ENDPOINT, [
            'timeout'  => 5,
            'blocking' => false,
            'headers'  => [ 'content-type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
        ] );

        update_option( self::LAST_SENT_OPTION, $this->today );
    }

    public function maybe_handle_consent(): void {
        if ( ! isset( $_GET[ self::QUERY_ACTION ] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        update_option(
            self::CONSENT_OPTION,
            sanitize_key( wp_unslash( $_GET[ self::QUERY_ACTION ] ) ) === '1' ? '1' : '0'
        );
    }
}
```

- [ ] **Step 4: Add the consent control to the coverage panel**

In `CoveragePanel::markup()`, before the final `return sprintf(...)` for the populated case, build the consent line and append it inside the card. Add at the top of the class:

```php
use ElementorDivi5Converter\Telemetry\CoverageTelemetry;
```

and add this private method:

```php
    /**
     * The opt-in lives here because this is the one screen where the user is
     * already looking at exactly the data being asked for.
     */
    private function consent_line(): string {
        $telemetry = new CoverageTelemetry( $this->history );
        $on        = $telemetry->has_consent();

        $url = add_query_arg( CoverageTelemetry::QUERY_ACTION, $on ? '0' : '1' )
            . '&_wpnonce=' . wp_create_nonce( CoverageTelemetry::NONCE_ACTION );

        return sprintf(
            '<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
            $on
                ? esc_html__( 'Sharing this list of widget names with divi5lab so these gaps get prioritised. No site address, no content, no personal data.', 'jhmg-converter-for-elementor-to-divi' )
                : esc_html__( 'Help prioritise these widgets? Sharing sends only the widget names above, once a week. No site address, no content, no personal data.', 'jhmg-converter-for-elementor-to-divi' ),
            esc_url( $url ),
            $on
                ? esc_html__( 'Stop sharing', 'jhmg-converter-for-elementor-to-divi' )
                : esc_html__( 'Share these widget names', 'jhmg-converter-for-elementor-to-divi' )
        );
    }
```

Then wire it into the populated-case return: change that format string's tail from
`'<tbody>%6$s</tbody></table></div>'` to `'<tbody>%6$s</tbody></table>%7$s</div>'`, and pass
`$this->consent_line()` as the seventh `sprintf()` argument (after `$rows`).

The consent line belongs only on the populated case — there is nothing to share when
nothing failed to convert.

- [ ] **Step 5: Register the telemetry hooks**

In `includes/helpers/class-plugin.php`, inside the `is_admin()` block:

```php
            ( new \ElementorDivi5Converter\Telemetry\CoverageTelemetry() )->init();
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit --filter 'CoverageTelemetryTest|CoveragePanelTest'`
Expected: all pass.

- [ ] **Step 7: Full suite, lint, commit**

```bash
vendor/bin/phpunit
find plugin -name '*.php' -exec php -l {} \; | grep -v "No syntax errors" || true
git add plugin/jhmg-converter-for-elementor-to-divi/includes/telemetry/class-coverage-telemetry.php \
        plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-coverage-panel.php \
        plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-plugin.php \
        tests/CoverageTelemetryTest.php
git commit -m "feat(free): opt-in anonymous coverage telemetry, widget names only"
```

---

### Task 8: Release prep — 2.3.0, folded changelog, privacy disclosure

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php` (header `Version:`, `EDC_PLUGIN_VERSION`)
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/readme.txt` (`Stable tag`, changelog, upgrade notice, new privacy section)
- Test: `tests/ReleaseMetadataTest.php`

**Interfaces:**
- Consumes: nothing. This is the last task before the wp.org release.

wp.org guideline 7 requires disclosing any external service the plugin contacts, what it sends, and when.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/ReleaseMetadataTest.php
use PHPUnit\Framework\TestCase;

class ReleaseMetadataTest extends TestCase {
    private const FREE = __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi';

    public function test_version_is_consistent_across_header_constant_and_readme(): void {
        $main   = (string) file_get_contents( self::FREE . '/jhmg-converter-for-elementor-to-divi.php' );
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertStringContainsString( 'Version:     2.3.0', $main );
        $this->assertStringContainsString( "EDC_PLUGIN_VERSION', '2.3.0'", $main );
        $this->assertStringContainsString( 'Stable tag: 2.3.0', $readme );
    }

    public function test_readme_discloses_the_external_service(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );
        $this->assertStringContainsString( 'External services', $readme );
        $this->assertStringContainsString( 'divi5lab.com/api/plugin/coverage', $readme );
        $this->assertStringContainsString( 'opt-in', $readme );
    }

    public function test_no_2_2_0_section_survives_the_fold(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );
        $this->assertStringNotContainsString( '= 2.2.0 =', $readme, '2.2.0 was never published' );
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `vendor/bin/phpunit --filter ReleaseMetadataTest`
Expected: 3 failures.

- [ ] **Step 3: Bump the version**

In the main plugin file, change ` * Version:     2.2.0` to ` * Version:     2.3.0`, and `define( 'EDC_PLUGIN_VERSION', '2.2.0' )` to `'2.3.0'`. In `readme.txt`, change `Stable tag: 2.2.0` to `Stable tag: 2.3.0`.

- [ ] **Step 4: Fold the changelog**

Replace the whole `= 2.2.0 =` block in `== Changelog ==` with:

```
= 2.3.0 =
* New: Conversion coverage — see every Elementor widget your imports could not convert, ranked by how many imports each affected
* New: Undo an import — moves the pages that import created to the trash, and never touches a page you have edited since
* New: Optional, opt-in sharing of unsupported widget names with divi5lab so the most-needed widgets get built first. Off by default; sends widget names only
* Pro is now $25/yr, reduced from $49/yr — same unlimited-sites license
* Fixed: the upgrade screen quoted the old $49/yr price; the price is now single-sourced so it cannot drift again
* New: a dismissible notice announcing the new price (dismissed per user, and it stops showing after 2026-10-27)
* New: after three successful conversions, the results screen asks once whether you'd like to review the plugin on WordPress.org — dismissible, snoozeable, per user, and never shown after a run that had any failures
```

And replace the `= 2.2.0 =` block in `== Upgrade Notice ==` with:

```
= 2.3.0 =
Adds a conversion coverage report and one-click undo for an import. Pro dropped to $25/yr from $49/yr — the same unlimited-sites license. The free plugin still converts unlimited single pages.
```

- [ ] **Step 5: Add the privacy disclosure**

Insert before `== Changelog ==`:

```
== External services ==

This plugin can optionally send a short report to divi5lab.com so that the most
commonly missing Elementor widgets get built first.

* **Service:** divi5lab.com coverage endpoint — https://divi5lab.com/api/plugin/coverage
* **What is sent:** the names of Elementor widget types your imports could not
  convert (for example `lottie`). Nothing else — no site address, no page
  content, no personal data, no license or account information.
* **When:** at most once a week, and only after you explicitly turn sharing on
  from the Conversion coverage panel. Sharing is off by default and nothing is
  sent until you enable it.
* **Turning it off:** use "Stop sharing" on the same panel at any time.
* Terms: https://divi5lab.com/terms — Privacy policy: https://divi5lab.com/privacy
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit --filter ReleaseMetadataTest`
Expected: 3 tests pass.

- [ ] **Step 7: Full suite, lint, commit, merge**

```bash
vendor/bin/phpunit
find plugin -name '*.php' -exec php -l {} \; | grep -v "No syntax errors" || true
git add plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php \
        plugin/jhmg-converter-for-elementor-to-divi/readme.txt tests/ReleaseMetadataTest.php
git commit -m "release(free): 2.3.0 — coverage, rollback, opt-in telemetry; folds unreleased 2.2.0"
git checkout main
git merge --no-ff feat/coverage-and-rollback -m "Merge feat/coverage-and-rollback: coverage, rollback, opt-in telemetry (2.3.0)"
git push origin main
```

- [ ] **Step 8: Cut the wp.org release (operator-gated)**

The endpoint from Task 6 must already be live. Then:

```bash
cd $PLUGIN_REPO
svn co https://plugins.svn.wordpress.org/jhmg-converter-for-elementor-to-divi wporg-svn  # if absent
rsync -a --delete --exclude='.svn' --exclude='.DS_Store' \
  plugin/jhmg-converter-for-elementor-to-divi/ wporg-svn/trunk/
cd wporg-svn
svn add --force -q trunk assets
svn status | grep '^!' | awk '{print $2}' | xargs -r -I{} svn rm {}
svn cp trunk tags/2.3.0
svn status | grep -v '^?'          # review before committing
svn ci -m "2.3.0: conversion coverage, import rollback, opt-in widget-gap sharing, Pro price \$25/yr" --username lucaslopvet
```

**Lucas runs the final `svn ci`.** It publishes to 100+ active installs with auto-update.

---

## Out of scope

- Direct-from-Elementor-site conversion and conversion preview (3.0.0 — they share a dry-run engine and should be designed together).
- Widening widget coverage itself, which this release exists to aim.
- Any change to the free/Pro boundary. Everything here is free.
- An admin view of aggregated coverage data on divi5lab (the table and endpoint land here; the reporting UI is separate work).
