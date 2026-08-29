# Direct Conversion & Conversion Preview (3.0.0) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user convert an installed Elementor page to Divi 5 without exporting JSON, and see a structural report of what the conversion will produce before anything is written.

**Architecture:** One dry-run engine, two consumers. `ConversionSource` implementations normalize input (uploaded JSON, or posts already on the site); `ConversionPreflight` converts them into an immutable `ConversionPlan` while touching no database; `ConversionCommitter` writes a plan to the database. `BatchImporter` keeps its current public signature and becomes preflight-then-commit, so the existing suite and the Pro Theme Builder contract are unaffected. Free/Pro splits on a quantity filter, not on disabled feature code.

**Tech Stack:** PHP 8.0+, WordPress plugin APIs, PHPUnit 9 with hand-written WP stubs in `tests/bootstrap.php` (no WP test suite, no database).

**Spec:** `docs/superpowers/specs/2026-08-28-direct-conversion-and-preview-design.md`

## Global Constraints

- **Branch:** all work happens on `feat/direct-conversion-and-preview`. **Never commit on `main`.** Merge is `--no-ff`, and only when Lucas asks.
- **Text domain:** `jhmg-converter-for-elementor-to-divi` — every user-facing string, no exceptions.
- **Before every commit, both of these must pass:**
  ```bash
  cd /Users/Lucas/Documents/JHMG-Local/jhmg-elementor-to-divi5
  ./vendor/bin/phpunit
  find plugin -name '*.php' -exec php -l {} \;
  ```
  The suite is currently **399 tests green**. A task that reduces that count has broken something.
- **TDD, strictly:** write the failing test, run it and see it fail for the stated reason, implement the minimum, run it green, commit.
- **Autoloader contract:** `ElementorDivi5Converter\Foo\BarBaz` → `includes/foo/class-bar-baz.php`. Directory is the lowercased namespace segment; filename is `class-` plus the class name kebab-cased. Interfaces follow the same rule (`ConverterInterface` → `class-converter-interface.php`).
- **Every new PHP file** starts with `<?php`, declares its namespace, and includes the standard guard:
  ```php
  if ( ! defined( 'ABSPATH' ) ) {
      exit;
  }
  ```
- **Naming convention is per-neighbourhood:** classes under `includes/converter/`, `includes/exporters/`, `includes/parsers/` use `camelCase` methods. Classes under `includes/admin/`, `includes/history/` use `snake_case` methods. New `includes/conversion/` classes use **camelCase** (they are pipeline classes); new `includes/admin/` classes use **snake_case**.
- **Indentation:** 4 spaces, no tabs. Match the surrounding file exactly.
- **Never overwrite the source Elementor post.** A direct conversion only ever creates a new post. No task in this plan may write to a post carrying `_elementor_data`.
- **No new dependencies.** No Composer packages, no JS build step.

---

## File Structure

**Create — `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/`**

| File | Responsibility |
|---|---|
| `class-conversion-source.php` | `ConversionSource` interface — one method, `items()` |
| `class-uploaded-json-source.php` | Adapts the existing `ElementorImportParser` to that interface |
| `class-installed-post-source.php` | Reads `_elementor_data` off installed posts via `ElementorDocumentParser` |
| `class-conversion-plan.php` | Immutable result of a dry run |
| `class-conversion-outline.php` | Builds the structural outline tree from converted blocks |
| `class-conversion-preflight.php` | The dry-run engine. Writes nothing. |
| `class-conversion-committer.php` | Writes a plan to the database |

**Create — `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/`**

| File | Responsibility |
|---|---|
| `class-elementor-page-repository.php` | Finds Elementor-built posts for the picker |
| `class-outline-renderer.php` | Renders an outline array to HTML |
| `class-direct-conversion-page.php` | The "Convert from this site" screen and its handlers |

**Modify**

| File | Change |
|---|---|
| `includes/admin/class-batch-importer.php` | Delegates to preflight + committer |
| `includes/exporters/class-divi-exporter.php` | Remove the unused `$dry_run` parameter |
| `includes/admin/class-admin-page.php` | Register the new screen; render the landing card |
| `includes/helpers/class-plugin.php` | Boot `DirectConversionPage` |
| `jhmg-converter-for-elementor-to-divi.php` | Version → 3.0.0 |
| `readme.txt` | Version, free/Pro copy, changelog, FAQ |
| `tests/bootstrap.php` | Add missing WP stubs |
| Pro: `includes/class-plugin.php` | Register `edc_direct_conversion_limit` |

---

## Task 1: Test harness stubs

The suite has no `sanitize_text_field`, `absint`, `get_post_types`, `checked`, or `selected` stub. Later tasks need all five. Adding them first keeps every later task's failure messages honest — a missing stub produces a fatal error that looks like a bug in the code under test.

**Files:**
- Modify: `tests/bootstrap.php`
- Test: `tests/BootstrapStubsTest.php` (create)

**Interfaces:**
- Consumes: nothing
- Produces: global functions `sanitize_text_field( $str ): string`, `absint( $n ): int`, `get_post_types( array $args = [], string $output = 'names' ): array`, `checked( $checked, $current = true, $echo = true ): string`, `selected( $selected, $current = true, $echo = true ): string`

- [ ] **Step 1: Write the failing test**

Create `tests/BootstrapStubsTest.php`:

```php
<?php
// tests/BootstrapStubsTest.php
use PHPUnit\Framework\TestCase;

class BootstrapStubsTest extends TestCase {

    public function test_sanitize_text_field_strips_tags_and_trims(): void {
        $this->assertSame( 'hello', sanitize_text_field( "  <b>hello</b>\n" ) );
    }

    public function test_absint_coerces_to_non_negative_int(): void {
        $this->assertSame( 12, absint( '12abc' ) );
        $this->assertSame( 5, absint( -5 ) );
    }

    public function test_get_post_types_returns_registered_names(): void {
        $types = get_post_types();
        $this->assertContains( 'page', $types );
        $this->assertContains( 'post', $types );
    }

    public function test_checked_returns_attribute_when_values_match(): void {
        $this->assertSame( " checked='checked'", checked( 'a', 'a', false ) );
        $this->assertSame( '', checked( 'a', 'b', false ) );
    }

    public function test_selected_returns_attribute_when_values_match(): void {
        $this->assertSame( " selected='selected'", selected( 'a', 'a', false ) );
        $this->assertSame( '', selected( 'a', 'b', false ) );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter BootstrapStubsTest`
Expected: FAIL — `Error: Call to undefined function sanitize_text_field()`

- [ ] **Step 3: Write minimal implementation**

Append to `tests/bootstrap.php`, before the closing of the file:

```php
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( (string) $str ) );
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $n ) {
        return abs( (int) $n );
    }
}

if ( ! function_exists( 'get_post_types' ) ) {
    // Tests seed $GLOBALS['__test_post_types'] when they need more than the default two.
    function get_post_types( $args = [], $output = 'names' ) {
        return $GLOBALS['__test_post_types'] ?? [ 'post' => 'post', 'page' => 'page' ];
    }
}

if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $echo = true ) {
        $result = (string) $checked === (string) $current ? " checked='checked'" : '';
        if ( $echo ) {
            echo $result;
        }
        return $result;
    }
}

if ( ! function_exists( 'selected' ) ) {
    function selected( $selected, $current = true, $echo = true ) {
        $result = (string) $selected === (string) $current ? " selected='selected'" : '';
        if ( $echo ) {
            echo $result;
        }
        return $result;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, total count is 399 + 5 = 404.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add tests/bootstrap.php tests/BootstrapStubsTest.php
git commit -m "test(harness): stub sanitize_text_field, absint, get_post_types, checked, selected

The direct-conversion screen and its repository need all five. Stubbing them
first means a later task's failure is about that task, not a fatal error from
an undefined WordPress function.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 2: `ConversionPlan` value object

The immutable result of a dry run. Every later task either produces one or consumes one, so it is defined first and nothing about it is guessed later.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-plan.php`
- Test: `tests/ConversionPlanTest.php` (create)

**Interfaces:**
- Consumes: nothing
- Produces: `ElementorDivi5Converter\Conversion\ConversionPlan` with
  - `__construct( array $items, int $limit = 1, bool $truncated = false )`
  - `items(): array` — list of item arrays
  - `limit(): int`
  - `truncated(): bool`
  - `count(): int`
  - `hasFailures(): bool`
  - `toArray(): array` — `[ 'items' => array, 'limit' => int, 'truncated' => bool ]`
  - `static item( array $fields ): array` — builds one item with every key present and defaulted

**Item shape** (every key always present):

```php
[
  'title'         => string,
  'post_type'     => string,
  'post_name'     => string,
  'template_type' => string,   // '' | 'header' | 'footer'
  'source_ref'    => array,    // ['kind'=>'upload'|'installed','post_id'=>?int,'file'=>?string]
  'blocks'        => array,    // converted Divi tree
  'content'       => string,   // serialized Divi 5 block markup
  'report'        => array,
  'unsupported'   => array,
  'outline'       => array,
  'error'         => string,   // '' when the item converted
]
```

- [ ] **Step 1: Write the failing test**

Create `tests/ConversionPlanTest.php`:

```php
<?php
// tests/ConversionPlanTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class ConversionPlanTest extends TestCase {

    public function test_item_fills_every_key_with_a_default(): void {
        $item = ConversionPlan::item( [ 'title' => 'Home' ] );

        $this->assertSame( 'Home', $item['title'] );
        $this->assertSame( 'page', $item['post_type'] );
        $this->assertSame( '', $item['post_name'] );
        $this->assertSame( '', $item['template_type'] );
        $this->assertSame( [], $item['blocks'] );
        $this->assertSame( '', $item['content'] );
        $this->assertSame( [], $item['report'] );
        $this->assertSame( [], $item['unsupported'] );
        $this->assertSame( [], $item['outline'] );
        $this->assertSame( '', $item['error'] );
        $this->assertSame(
            [ 'kind' => 'upload', 'post_id' => null, 'file' => null ],
            $item['source_ref']
        );
    }

    public function test_item_keeps_supplied_values(): void {
        $item = ConversionPlan::item( [
            'title'      => 'About',
            'post_type'  => 'post',
            'content'    => '<!-- wp:divi/placeholder -->x<!-- /wp:divi/placeholder -->',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 42, 'file' => null ],
        ] );

        $this->assertSame( 'post', $item['post_type'] );
        $this->assertSame( 42, $item['source_ref']['post_id'] );
        $this->assertStringContainsString( 'divi/placeholder', $item['content'] );
    }

    public function test_plan_exposes_items_limit_and_truncation(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [ 'title' => 'A' ] ) ], 1, true );

        $this->assertCount( 1, $plan->items() );
        $this->assertSame( 1, $plan->count() );
        $this->assertSame( 1, $plan->limit() );
        $this->assertTrue( $plan->truncated() );
    }

    public function test_plan_defaults_to_limit_one_and_not_truncated(): void {
        $plan = new ConversionPlan( [] );

        $this->assertSame( 1, $plan->limit() );
        $this->assertFalse( $plan->truncated() );
        $this->assertSame( 0, $plan->count() );
    }

    public function test_has_failures_is_true_when_any_item_carries_an_error(): void {
        $ok   = ConversionPlan::item( [ 'title' => 'A' ] );
        $bad  = ConversionPlan::item( [ 'title' => 'B', 'error' => 'no Elementor data' ] );

        $this->assertFalse( ( new ConversionPlan( [ $ok ] ) )->hasFailures() );
        $this->assertTrue( ( new ConversionPlan( [ $ok, $bad ] ) )->hasFailures() );
    }

    public function test_to_array_round_trips_the_plan(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [ 'title' => 'A' ] ) ], 5, false );

        $this->assertSame(
            [ 'items', 'limit', 'truncated' ],
            array_keys( $plan->toArray() )
        );
        $this->assertSame( 5, $plan->toArray()['limit'] );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ConversionPlanTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Conversion\ConversionPlan" not found`

- [ ] **Step 3: Write minimal implementation**

Create `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-plan.php`:

```php
<?php
/**
 * The immutable result of a dry run.
 *
 * A plan describes what a conversion *would* produce. Building one writes
 * nothing; ConversionCommitter is the only thing that turns a plan into posts.
 */

namespace ElementorDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ConversionPlan {

    /** @var array[] */
    private array $items;
    private int $limit;
    private bool $truncated;

    public function __construct( array $items, int $limit = 1, bool $truncated = false ) {
        $this->items     = array_values( $items );
        $this->limit     = $limit;
        $this->truncated = $truncated;
    }

    /**
     * Build one plan item with every key present, so consumers never have to
     * null-check a field that a particular source happened not to set.
     */
    public static function item( array $fields ): array {
        $source_ref = $fields['source_ref'] ?? [];

        return [
            'title'         => (string) ( $fields['title']         ?? 'Imported Page' ),
            'post_type'     => (string) ( $fields['post_type']     ?? 'page' ),
            'post_name'     => (string) ( $fields['post_name']     ?? '' ),
            'template_type' => (string) ( $fields['template_type'] ?? '' ),
            'source_ref'    => [
                'kind'    => (string) ( $source_ref['kind'] ?? 'upload' ),
                'post_id' => isset( $source_ref['post_id'] ) ? (int) $source_ref['post_id'] : null,
                'file'    => isset( $source_ref['file'] ) ? (string) $source_ref['file'] : null,
            ],
            'blocks'        => $fields['blocks']      ?? [],
            'content'       => (string) ( $fields['content'] ?? '' ),
            'report'        => $fields['report']      ?? [],
            'unsupported'   => $fields['unsupported'] ?? [],
            'outline'       => $fields['outline']     ?? [],
            'error'         => (string) ( $fields['error'] ?? '' ),
        ];
    }

    /** @return array[] */
    public function items(): array {
        return $this->items;
    }

    public function limit(): int {
        return $this->limit;
    }

    public function truncated(): bool {
        return $this->truncated;
    }

    public function count(): int {
        return count( $this->items );
    }

    public function hasFailures(): bool {
        foreach ( $this->items as $item ) {
            if ( ( $item['error'] ?? '' ) !== '' ) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array {
        return [
            'items'     => $this->items,
            'limit'     => $this->limit,
            'truncated' => $this->truncated,
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 410 tests.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-plan.php tests/ConversionPlanTest.php
git commit -m "feat(conversion): ConversionPlan, the immutable result of a dry run

Every plan item carries every key, defaulted, so a consumer never has to
discover which source happened to populate which field.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 3: `ConversionSource` interface and `UploadedJsonSource`

Wraps today's upload path behind the interface, changing no behaviour. This is the safe half of the source abstraction — it proves the interface fits the existing parser before Task 4 adds a genuinely new source.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-source.php`
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-uploaded-json-source.php`
- Test: `tests/UploadedJsonSourceTest.php` (create)

**Interfaces:**
- Consumes: `ElementorDivi5Converter\Parsers\ElementorImportParser::parse( string $file_path, string $file_name = '' ): array`
- Produces:
  - `interface ElementorDivi5Converter\Conversion\ConversionSource { public function items(): array; }`
  - `class ElementorDivi5Converter\Conversion\UploadedJsonSource implements ConversionSource` with `__construct( string $file_path, string $file_name = '', ?ElementorImportParser $parser = null )`

Each returned item is a **raw source item** — the parser's item shape plus `source_ref`. It is *not* a `ConversionPlan` item; conversion happens later, in `ConversionPreflight`.

```php
[ 'title', 'post_type', 'post_name', 'template_type', 'elements', 'source_ref' ]
```

- [ ] **Step 1: Write the failing test**

There are existing fixtures at `fixtures/elementor/`. Create `tests/UploadedJsonSourceTest.php`:

```php
<?php
// tests/UploadedJsonSourceTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionSource;
use ElementorDivi5Converter\Conversion\UploadedJsonSource;

class UploadedJsonSourceTest extends TestCase {

    private function fixture(): string {
        return dirname( __DIR__ ) . '/fixtures/elementor/hero-page.json';
    }

    public function test_it_is_a_conversion_source(): void {
        $source = new UploadedJsonSource( $this->fixture(), 'hero-page.json' );
        $this->assertInstanceOf( ConversionSource::class, $source );
    }

    public function test_it_yields_items_with_elements(): void {
        $items = ( new UploadedJsonSource( $this->fixture(), 'hero-page.json' ) )->items();

        $this->assertNotEmpty( $items );
        $this->assertArrayHasKey( 'elements', $items[0] );
        $this->assertIsArray( $items[0]['elements'] );
        $this->assertNotEmpty( $items[0]['elements'] );
    }

    public function test_it_stamps_an_upload_source_ref_carrying_the_file_name(): void {
        $items = ( new UploadedJsonSource( $this->fixture(), 'hero-page.json' ) )->items();

        $this->assertSame( 'upload', $items[0]['source_ref']['kind'] );
        $this->assertSame( 'hero-page.json', $items[0]['source_ref']['file'] );
        $this->assertNull( $items[0]['source_ref']['post_id'] );
    }

    public function test_it_preserves_every_field_the_parser_produced(): void {
        $parser = new class extends \ElementorDivi5Converter\Parsers\ElementorImportParser {
            public function parse( string $file_path, string $file_name = '' ): array {
                return [ [
                    'title'         => 'My Header',
                    'post_type'     => 'page',
                    'post_name'     => 'my-header',
                    'template_type' => 'header',
                    'elements'      => [ [ 'elType' => 'section' ] ],
                ] ];
            }
        };

        $items = ( new UploadedJsonSource( '/nonexistent.json', 'x.json', $parser ) )->items();

        $this->assertSame( 'My Header', $items[0]['title'] );
        $this->assertSame( 'my-header', $items[0]['post_name'] );
        $this->assertSame( 'header', $items[0]['template_type'] );
    }

    public function test_an_unreadable_file_yields_no_items_rather_than_throwing(): void {
        $items = ( new UploadedJsonSource( '/definitely/not/here.json', 'nope.json' ) )->items();
        $this->assertSame( [], $items );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter UploadedJsonSourceTest`
Expected: FAIL — `Error: Interface "ElementorDivi5Converter\Conversion\ConversionSource" not found`

- [ ] **Step 3: Write minimal implementation**

Create `includes/conversion/class-conversion-source.php`:

```php
<?php
/**
 * A place conversion input comes from.
 *
 * Implementations normalize their input to the item shape the converter
 * pipeline expects, so ConversionPreflight never needs to know whether the
 * work arrived as an upload or was read off a post already on this site.
 */

namespace ElementorDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ConversionSource {

    /**
     * @return array[] Items shaped
     *   ['title','post_type','post_name','template_type','elements','source_ref'].
     *   Returns [] when there is nothing to convert — never throws.
     */
    public function items(): array;
}
```

Create `includes/conversion/class-uploaded-json-source.php`:

```php
<?php
/**
 * Conversion input from an uploaded Elementor JSON (or kit ZIP) file.
 *
 * A thin adapter over the existing ElementorImportParser: the upload path's
 * behaviour is unchanged, it simply now arrives through the same interface as
 * every other source.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Parsers\ElementorImportParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UploadedJsonSource implements ConversionSource {

    private string $filePath;
    private string $fileName;
    private ElementorImportParser $parser;

    public function __construct( string $file_path, string $file_name = '', ?ElementorImportParser $parser = null ) {
        $this->filePath = $file_path;
        $this->fileName = $file_name;
        $this->parser   = $parser ?? new ElementorImportParser();
    }

    public function items(): array {
        try {
            $items = $this->parser->parse( $this->filePath, $this->fileName );
        } catch ( \Throwable $e ) {
            return [];
        }

        $out = [];
        foreach ( $items as $item ) {
            $item['source_ref'] = [
                'kind'    => 'upload',
                'post_id' => null,
                'file'    => $this->fileName,
            ];
            $out[] = $item;
        }

        return $out;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 415 tests.

If `test_an_unreadable_file_yields_no_items_rather_than_throwing` fails because the parser returns items rather than throwing, check what `ElementorImportParser::parse()` does with a missing path and adjust the assertion to match reality — do **not** change the parser. This test documents existing behaviour; it does not dictate it.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/ tests/UploadedJsonSourceTest.php
git commit -m "feat(conversion): ConversionSource interface, with the upload path behind it

Behaviour of the upload path is unchanged. Putting the existing parser behind
the interface first proves the interface fits before a genuinely new source
arrives.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 4: `InstalledPostSource`

Reads Elementor data straight off posts on this site. `ElementorDocumentParser` already handles every observed `_elementor_data` encoding and is currently unused in production — this task is what puts it to work.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-installed-post-source.php`
- Test: `tests/InstalledPostSourceTest.php` (create)

**Interfaces:**
- Consumes: `ConversionSource` (Task 3); `ElementorDivi5Converter\Parsers\ElementorDocumentParser::parse( array $post_meta ): array`
- Produces: `class ElementorDivi5Converter\Conversion\InstalledPostSource implements ConversionSource` with `__construct( array $post_ids, ?ElementorDocumentParser $parser = null )`

Rules:
- Title comes from the post; `post_name` from the post; `post_type` is `page` for a `page`, otherwise `post` — except `elementor_library`, which maps to `page` and derives `template_type` from `_elementor_template_type` (`header`/`footer` only; anything else is `''`).
- A post that does not exist, or whose Elementor data is missing or empty, still yields an item — one carrying an `error`. Failures are per-item, never fatal to the run.
- **Reads only.** No `update_post_meta`, no `wp_update_post`.

- [ ] **Step 1: Write the failing test**

Create `tests/InstalledPostSourceTest.php`:

```php
<?php
// tests/InstalledPostSourceTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionSource;
use ElementorDivi5Converter\Conversion\InstalledPostSource;

class InstalledPostSourceTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    /** Seeds a post plus its Elementor data, and returns the post ID. */
    private function seed_elementor_post(
        int $id,
        string $title,
        string $post_type = 'page',
        $elementor_data = null,
        string $template_type = ''
    ): int {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID'         => $id,
            'post_title' => $title,
            'post_name'  => strtolower( str_replace( ' ', '-', $title ) ),
            'post_type'  => $post_type,
            'post_status' => 'publish',
        ];

        $data = $elementor_data ?? wp_json_encode( [
            [ 'elType' => 'section', 'elements' => [] ],
        ] );
        update_post_meta( $id, '_elementor_data', $data );
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        if ( $template_type !== '' ) {
            update_post_meta( $id, '_elementor_template_type', $template_type );
        }

        return $id;
    }

    public function test_it_is_a_conversion_source(): void {
        $this->assertInstanceOf( ConversionSource::class, new InstalledPostSource( [] ) );
    }

    public function test_it_reads_elements_off_an_installed_post(): void {
        $id = $this->seed_elementor_post( 501, 'Home' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertCount( 1, $items );
        $this->assertSame( 'Home', $items[0]['title'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertSame( 'home', $items[0]['post_name'] );
        $this->assertSame( '', $items[0]['error'] ?? '' );
        $this->assertNotEmpty( $items[0]['elements'] );
    }

    public function test_it_stamps_an_installed_source_ref_carrying_the_post_id(): void {
        $id = $this->seed_elementor_post( 502, 'About' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( 'installed', $items[0]['source_ref']['kind'] );
        $this->assertSame( 502, $items[0]['source_ref']['post_id'] );
        $this->assertNull( $items[0]['source_ref']['file'] );
    }

    public function test_a_blog_post_keeps_the_post_type_post(): void {
        $id = $this->seed_elementor_post( 503, 'News Item', 'post' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( 'post', $items[0]['post_type'] );
    }

    public function test_a_library_header_template_reports_its_template_type(): void {
        $id = $this->seed_elementor_post( 504, 'Site Header', 'elementor_library', null, 'header' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( 'header', $items[0]['template_type'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
    }

    public function test_an_unrecognised_library_template_type_is_blank(): void {
        $id = $this->seed_elementor_post( 505, 'A Popup', 'elementor_library', null, 'popup' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( '', $items[0]['template_type'] );
    }

    public function test_a_missing_post_yields_an_item_carrying_an_error(): void {
        $items = ( new InstalledPostSource( [ 999999 ] ) )->items();

        $this->assertCount( 1, $items );
        $this->assertNotSame( '', $items[0]['error'] );
        $this->assertSame( [], $items[0]['elements'] );
    }

    public function test_a_post_without_elementor_data_yields_an_item_carrying_an_error(): void {
        $GLOBALS['__test_posts'][ 506 ] = (object) [
            'ID' => 506, 'post_title' => 'Plain', 'post_name' => 'plain',
            'post_type' => 'page', 'post_status' => 'publish',
        ];

        $items = ( new InstalledPostSource( [ 506 ] ) )->items();

        $this->assertNotSame( '', $items[0]['error'] );
    }

    public function test_one_bad_post_does_not_stop_the_others(): void {
        $good = $this->seed_elementor_post( 507, 'Good' );

        $items = ( new InstalledPostSource( [ 999999, $good ] ) )->items();

        $this->assertCount( 2, $items );
        $this->assertNotSame( '', $items[0]['error'] );
        $this->assertSame( '', $items[1]['error'] );
    }

    public function test_reading_a_source_writes_nothing(): void {
        $id     = $this->seed_elementor_post( 508, 'Untouched' );
        $before = [ $GLOBALS['__test_posts'], $GLOBALS['__test_postmeta'] ];

        ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( $before, [ $GLOBALS['__test_posts'], $GLOBALS['__test_postmeta'] ] );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter InstalledPostSourceTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Conversion\InstalledPostSource" not found`

- [ ] **Step 3: Write minimal implementation**

Create `includes/conversion/class-installed-post-source.php`:

```php
<?php
/**
 * Conversion input read directly from posts on this site.
 *
 * This is the path that removes the export/upload round trip: the Elementor
 * data is already here, in `_elementor_data`, and ElementorDocumentParser
 * already knows every shape WordPress stores it in.
 *
 * Strictly read-only. The source post is never modified — a conversion always
 * creates a new post, so a failed or unwanted conversion can never cost the
 * user their Elementor original.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Parsers\ElementorDocumentParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class InstalledPostSource implements ConversionSource {

    /** Elementor's own marker for a post built with its editor. */
    const EDIT_MODE_META = '_elementor_edit_mode';

    /** @var int[] */
    private array $postIds;
    private ElementorDocumentParser $parser;

    public function __construct( array $post_ids, ?ElementorDocumentParser $parser = null ) {
        $this->postIds = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
        $this->parser  = $parser ?? new ElementorDocumentParser();
    }

    public function items(): array {
        $items = [];

        foreach ( $this->postIds as $post_id ) {
            $items[] = $this->itemFor( $post_id );
        }

        return $items;
    }

    private function itemFor( int $post_id ): array {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return $this->failed( $post_id, __( 'That page no longer exists.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        $meta     = get_post_meta( $post_id );
        $document = $this->parser->parse( is_array( $meta ) ? $meta : [] );
        $elements = $document['elements'] ?? [];

        if ( empty( $elements ) ) {
            return $this->failed(
                $post_id,
                __( 'No Elementor content found on that page.', 'jhmg-converter-for-elementor-to-divi' ),
                (string) ( $post->post_title ?? '' )
            );
        }

        $post_type     = (string) ( $post->post_type ?? 'page' );
        $template_type = '';

        if ( $post_type === 'elementor_library' ) {
            $template_type = $this->templateType( $post_id );
            $post_type     = 'page';
        } elseif ( $post_type !== 'page' ) {
            $post_type = 'post';
        }

        return [
            'title'         => (string) ( $post->post_title ?? '' ) ?: __( 'Imported Page', 'jhmg-converter-for-elementor-to-divi' ),
            'post_type'     => $post_type,
            'post_name'     => (string) ( $post->post_name ?? '' ),
            'template_type' => $template_type,
            'elements'      => $elements,
            'error'         => '',
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => $post_id, 'file' => null ],
        ];
    }

    /**
     * Only header and footer templates have a Divi Theme Builder equivalent.
     * Every other Elementor library type (popup, single, archive) converts as
     * an ordinary page, which is what a blank template_type means downstream.
     */
    private function templateType( int $post_id ): string {
        $type = (string) get_post_meta( $post_id, '_elementor_template_type', true );

        return in_array( $type, [ 'header', 'footer' ], true ) ? $type : '';
    }

    private function failed( int $post_id, string $error, string $title = '' ): array {
        return [
            'title'         => $title !== '' ? $title : __( 'Unknown page', 'jhmg-converter-for-elementor-to-divi' ),
            'post_type'     => 'page',
            'post_name'     => '',
            'template_type' => '',
            'elements'      => [],
            'error'         => $error,
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => $post_id, 'file' => null ],
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 425 tests.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-installed-post-source.php tests/InstalledPostSourceTest.php
git commit -m "feat(conversion): read Elementor data straight off installed posts

This is the half of 3.0.0 that removes the export/upload round trip. Strictly
read-only: a direct conversion creates a new post and never touches the
Elementor original, so no conversion can cost a user their source page.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 5: `ConversionOutline`

Turns a converted Divi tree into the structural outline the preview screen draws. Pure function over arrays — no WordPress, no Divi.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-outline.php`
- Test: `tests/ConversionOutlineTest.php` (create)

**Interfaces:**
- Consumes: converted block trees — `[ 'name' => 'divi/section', 'settings' => [], 'elements' => [...] ]`, the shape `ConverterEngine::convert()` puts in `['divi']['elements']`
- Produces: `ElementorDivi5Converter\Conversion\ConversionOutline` with `static build( array $blocks, array $unsupported = [] ): array`

Node shape:

```php
[ 'type' => 'section'|'row'|'column'|'module', 'name' => string, 'label' => string, 'unsupported' => bool, 'children' => array ]
```

`label` is human-readable: `divi/heading` → `Heading`, `divi/image` → `Image`, `divi/text` → `Text`. Rule: strip the `divi/` prefix, replace `-` and `_` with spaces, capitalize each word.

`unsupported` marks placeholder blocks the generic fallback produced. The fallback converter's output name is checked at implementation time; if there is no distinguishing name, the flag is driven by matching an `id` in `$unsupported` against the block's settings — see Step 3.

- [ ] **Step 1: Write the failing test**

Create `tests/ConversionOutlineTest.php`:

```php
<?php
// tests/ConversionOutlineTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionOutline;

class ConversionOutlineTest extends TestCase {

    public function test_an_empty_tree_produces_an_empty_outline(): void {
        $this->assertSame( [], ConversionOutline::build( [] ) );
    }

    public function test_it_classifies_structural_blocks(): void {
        $blocks = [ [
            'name'     => 'divi/section',
            'elements' => [ [
                'name'     => 'divi/row',
                'elements' => [ [
                    'name'     => 'divi/column',
                    'elements' => [ [ 'name' => 'divi/heading' ] ],
                ] ],
            ] ],
        ] ];

        $outline = ConversionOutline::build( $blocks );

        $this->assertSame( 'section', $outline[0]['type'] );
        $this->assertSame( 'row', $outline[0]['children'][0]['type'] );
        $this->assertSame( 'column', $outline[0]['children'][0]['children'][0]['type'] );
        $this->assertSame( 'module', $outline[0]['children'][0]['children'][0]['children'][0]['type'] );
    }

    public function test_it_labels_modules_readably(): void {
        $outline = ConversionOutline::build( [
            [ 'name' => 'divi/heading' ],
            [ 'name' => 'divi/image' ],
            [ 'name' => 'divi/call-to-action' ],
        ] );

        $this->assertSame( 'Heading', $outline[0]['label'] );
        $this->assertSame( 'Image', $outline[1]['label'] );
        $this->assertSame( 'Call To Action', $outline[2]['label'] );
    }

    public function test_it_preserves_sibling_order(): void {
        $outline = ConversionOutline::build( [
            [ 'name' => 'divi/heading' ],
            [ 'name' => 'divi/text' ],
            [ 'name' => 'divi/button' ],
        ] );

        $this->assertSame(
            [ 'Heading', 'Text', 'Button' ],
            array_column( $outline, 'label' )
        );
    }

    public function test_every_node_carries_the_unsupported_flag(): void {
        $outline = ConversionOutline::build( [ [ 'name' => 'divi/heading' ] ] );

        $this->assertArrayHasKey( 'unsupported', $outline[0] );
        $this->assertFalse( $outline[0]['unsupported'] );
    }

    public function test_a_block_without_a_name_is_skipped(): void {
        $outline = ConversionOutline::build( [
            [ 'settings' => [] ],
            [ 'name' => 'divi/heading' ],
        ] );

        $this->assertCount( 1, $outline );
        $this->assertSame( 'Heading', $outline[0]['label'] );
    }

    public function test_non_array_entries_are_skipped(): void {
        $outline = ConversionOutline::build( [ 'garbage', null, [ 'name' => 'divi/text' ] ] );

        $this->assertCount( 1, $outline );
    }

    public function test_deeply_nested_modules_are_reached(): void {
        $blocks = [ [
            'name'     => 'divi/section',
            'elements' => [ [
                'name'     => 'divi/row',
                'elements' => [ [
                    'name'     => 'divi/column',
                    'elements' => [ [
                        'name'     => 'divi/accordion',
                        'elements' => [ [ 'name' => 'divi/accordion-item' ] ],
                    ] ],
                ] ],
            ] ],
        ] ];

        $outline = ConversionOutline::build( $blocks );
        $module  = $outline[0]['children'][0]['children'][0]['children'][0];

        $this->assertSame( 'Accordion', $module['label'] );
        $this->assertSame( 'Accordion Item', $module['children'][0]['label'] );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ConversionOutlineTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Conversion\ConversionOutline" not found`

- [ ] **Step 3: Write minimal implementation**

First, check what the generic fallback converter names its output block, because that determines how `unsupported` is set:

```bash
grep -n "name" plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-generic-fallback-converter.php | head -20
```

If the fallback emits a distinguishable block name (for example `divi/code` or a placeholder), match on it. If it does not, leave `unsupported` as `false` for every node — the unsupported widgets are still listed by name in the report beside the outline, so no information is lost. **Do not invent a marker by modifying the converter**; that is out of scope for this task.

Create `includes/conversion/class-conversion-outline.php`:

```php
<?php
/**
 * Builds the structural outline the preview screen draws.
 *
 * The preview deliberately shows structure rather than pixels: Divi 5 keys its
 * CSS generation to a post ID, so rendering detached block markup produces an
 * unstyled skeleton that reads as broken output. A faithful outline answers the
 * question users actually have — did my layout survive — without pretending to
 * be a render.
 */

namespace ElementorDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionOutline {

    private const STRUCTURAL = [
        'divi/section' => 'section',
        'divi/row'     => 'row',
        'divi/column'  => 'column',
    ];

    /**
     * @param array $blocks      Converted Divi blocks (the ['divi']['elements'] tree).
     * @param array $unsupported Unsupported element entries from the engine.
     * @return array Outline nodes.
     */
    public static function build( array $blocks, array $unsupported = [] ): array {
        $nodes = [];

        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            $name = (string) ( $block['name'] ?? '' );
            if ( $name === '' ) {
                continue;
            }

            $nodes[] = [
                'type'        => self::STRUCTURAL[ $name ] ?? 'module',
                'name'        => $name,
                'label'       => self::label( $name ),
                'unsupported' => false,
                'children'    => self::build( $block['elements'] ?? [], $unsupported ),
            ];
        }

        return $nodes;
    }

    /** 'divi/call-to-action' → 'Call To Action'. */
    private static function label( string $name ): string {
        $bare = str_contains( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;

        return ucwords( str_replace( [ '-', '_' ], ' ', $bare ) );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 433 tests.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-outline.php tests/ConversionOutlineTest.php
git commit -m "feat(conversion): build a structural outline from a converted tree

The preview shows structure, not pixels. Divi 5 generates CSS per post ID, so
detached rendering yields an unstyled skeleton that reads as broken output; an
outline answers 'did my layout survive' honestly and needs no Divi at all.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 6: `ConversionPreflight` — the dry-run engine

The centrepiece. Converts a source into a `ConversionPlan` and writes nothing. Also applies the free/Pro quantity limit.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-preflight.php`
- Test: `tests/ConversionPreflightTest.php` (create)

**Interfaces:**
- Consumes: `ConversionSource` (Task 3), `ConversionPlan` (Task 2), `ConversionOutline` (Task 5), `ElementorDivi5Converter\Converter\ConverterEngine::convert( array $elementor_data ): array`, `ElementorDivi5Converter\Exporters\DiviBlockSerializer::serialize( array $divi_data ): string`
- Produces: `class ElementorDivi5Converter\Conversion\ConversionPreflight` with
  - `__construct( ?ConverterEngine $engine = null, ?DiviBlockSerializer $serializer = null )`
  - `run( ConversionSource $source ): ConversionPlan`
  - `static limit(): int` — reads `apply_filters( 'edc_direct_conversion_limit', 1 )`

Rules:
- A fresh `ConverterEngine` **per item**. The engine accumulates report state across `convert()` calls; sharing one would leak item A's warnings into item B's report.
- Global Elementor colors are applied exactly as `BatchImporter` does today, via `elementor_active_kit`.
- Items beyond `limit()` are dropped and `truncated` is set.
- An item arriving with a non-empty `error` is passed through unconverted, error intact.
- A `Throwable` during conversion becomes that item's `error`; the run continues.

- [ ] **Step 1: Write the failing test**

Create `tests/ConversionPreflightTest.php`:

```php
<?php
// tests/ConversionPreflightTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\Conversion\ConversionSource;

/** A source that yields exactly what it was handed. */
class FakeConversionSource implements ConversionSource {
    private array $items;
    public function __construct( array $items ) { $this->items = $items; }
    public function items(): array { return $this->items; }
}

class ConversionPreflightTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function heading_item( string $title = 'Home' ): array {
        return [
            'title'         => $title,
            'post_type'     => 'page',
            'post_name'     => 'home',
            'template_type' => '',
            'elements'      => [ [
                'elType'   => 'section',
                'elements' => [ [
                    'elType'   => 'column',
                    'elements' => [ [
                        'elType'     => 'widget',
                        'widgetType' => 'heading',
                        'settings'   => [ 'title' => 'Hello' ],
                    ] ],
                ] ],
            ] ],
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => 7, 'file' => null ],
        ];
    }

    public function test_it_returns_a_conversion_plan(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [] ) );

        $this->assertInstanceOf( ConversionPlan::class, $plan );
        $this->assertSame( 0, $plan->count() );
    }

    public function test_it_converts_an_item_into_blocks_and_serialized_content(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $item = $plan->items()[0];

        $this->assertSame( 'Home', $item['title'] );
        $this->assertSame( '', $item['error'] );
        $this->assertNotEmpty( $item['blocks'] );
        $this->assertStringContainsString( 'wp:divi/', $item['content'] );
    }

    public function test_it_carries_the_report_and_the_outline(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $item = $plan->items()[0];

        $this->assertArrayHasKey( 'converted', $item['report'] );
        $this->assertArrayHasKey( 'quality', $item['report'] );
        $this->assertNotEmpty( $item['outline'] );
        $this->assertSame( 'section', $item['outline'][0]['type'] );
    }

    public function test_it_preserves_the_source_ref(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $this->assertSame( 'installed', $plan->items()[0]['source_ref']['kind'] );
        $this->assertSame( 7, $plan->items()[0]['source_ref']['post_id'] );
    }

    public function test_a_preflight_run_writes_nothing(): void {
        $GLOBALS['__test_posts'][ 900 ] = (object) [ 'ID' => 900, 'post_type' => 'page' ];
        update_post_meta( 900, '_elementor_data', '[]' );

        $posts_before = $GLOBALS['__test_posts'];
        $meta_before  = $GLOBALS['__test_postmeta'];

        ( new ConversionPreflight() )->run( new FakeConversionSource( [ $this->heading_item() ] ) );

        $this->assertSame( $posts_before, $GLOBALS['__test_posts'], 'preflight created or changed a post' );
        $this->assertSame( $meta_before, $GLOBALS['__test_postmeta'], 'preflight wrote post meta' );
    }

    public function test_each_item_gets_a_fresh_report(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [
            $this->heading_item( 'One' ),
            $this->heading_item( 'Two' ),
        ] ) );

        // With a shared engine the second item's counts would include the first's.
        $this->assertSame(
            array_sum( $plan->items()[0]['report']['converted'] ),
            array_sum( $plan->items()[1]['report']['converted'] ),
            'report state leaked between items'
        );
    }

    public function test_an_item_arriving_with_an_error_passes_through_unconverted(): void {
        $bad = [
            'title' => 'Gone', 'post_type' => 'page', 'post_name' => '',
            'template_type' => '', 'elements' => [], 'error' => 'That page no longer exists.',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 42, 'file' => null ],
        ];

        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [ $bad ] ) );

        $this->assertSame( 'That page no longer exists.', $plan->items()[0]['error'] );
        $this->assertSame( '', $plan->items()[0]['content'] );
        $this->assertTrue( $plan->hasFailures() );
    }

    public function test_the_default_limit_is_one_and_extra_items_are_dropped(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [
            $this->heading_item( 'One' ),
            $this->heading_item( 'Two' ),
            $this->heading_item( 'Three' ),
        ] ) );

        $this->assertSame( 1, $plan->limit() );
        $this->assertSame( 1, $plan->count() );
        $this->assertTrue( $plan->truncated() );
        $this->assertSame( 'One', $plan->items()[0]['title'] );
    }

    public function test_a_filtered_limit_raises_the_cap(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 10 );

        $plan = ( new ConversionPreflight() )->run( new FakeConversionSource( [
            $this->heading_item( 'One' ),
            $this->heading_item( 'Two' ),
        ] ) );

        $this->assertSame( 10, $plan->limit() );
        $this->assertSame( 2, $plan->count() );
        $this->assertFalse( $plan->truncated() );
    }

    public function test_a_selection_at_exactly_the_limit_is_not_truncated(): void {
        $plan = ( new ConversionPreflight() )->run(
            new FakeConversionSource( [ $this->heading_item() ] )
        );

        $this->assertFalse( $plan->truncated() );
    }

    public function test_a_filtered_limit_below_one_is_clamped_to_one(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 0 );

        $this->assertSame( 1, ConversionPreflight::limit() );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ConversionPreflightTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Conversion\ConversionPreflight" not found`

- [ ] **Step 3: Write minimal implementation**

Create `includes/conversion/class-conversion-preflight.php`:

```php
<?php
/**
 * Runs a conversion without committing it.
 *
 * This is the one engine behind both of 3.0.0's features: the preview screen
 * shows a plan, and a commit writes one. Building it once is the reason those
 * two features shipped together — built separately, each would have grown its
 * own half of it.
 *
 * The invariant that matters: run() performs no database writes. ConversionPreflightTest
 * asserts it against the in-memory post and meta stores rather than trusting it.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviBlockSerializer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionPreflight {

    /**
     * Free converts one page per run. Pro raises this. It is a quantity
     * boundary, not a feature flag: the whole loop ships in the free plugin
     * and nothing here is disabled code waiting to be unlocked.
     */
    const LIMIT_FILTER  = 'edc_direct_conversion_limit';
    const DEFAULT_LIMIT = 1;

    private ?ConverterEngine $engine;
    private DiviBlockSerializer $serializer;

    /**
     * @param ConverterEngine|null $engine Injected only by tests that need to
     *   observe the engine. Left null in production so each item gets a fresh
     *   one — the engine accumulates report state across convert() calls.
     */
    public function __construct( ?ConverterEngine $engine = null, ?DiviBlockSerializer $serializer = null ) {
        $this->engine     = $engine;
        $this->serializer = $serializer ?? new DiviBlockSerializer();
    }

    public static function limit(): int {
        if ( ! function_exists( 'apply_filters' ) ) {
            return self::DEFAULT_LIMIT;
        }

        return max( 1, (int) apply_filters( self::LIMIT_FILTER, self::DEFAULT_LIMIT ) );
    }

    public function run( ConversionSource $source ): ConversionPlan {
        $limit     = self::limit();
        $all       = $source->items();
        $truncated = count( $all ) > $limit;
        $items     = array_slice( $all, 0, $limit );

        $planned = [];
        foreach ( $items as $item ) {
            $planned[] = $this->planItem( $item );
        }

        return new ConversionPlan( $planned, $limit, $truncated );
    }

    private function planItem( array $item ): array {
        $base = [
            'title'         => $item['title']         ?? '',
            'post_type'     => $item['post_type']     ?? 'page',
            'post_name'     => $item['post_name']     ?? '',
            'template_type' => $item['template_type'] ?? '',
            'source_ref'    => $item['source_ref']    ?? [],
        ];

        $incoming_error = (string) ( $item['error'] ?? '' );
        if ( $incoming_error !== '' ) {
            return ConversionPlan::item( $base + [ 'error' => $incoming_error ] );
        }

        try {
            $engine    = $this->engineForItem();
            $converted = $engine->convert( $item['elements'] ?? [] );

            return ConversionPlan::item( $base + [
                'blocks'      => $converted['divi'] ?? [],
                'content'     => $this->serializer->serialize( $converted ),
                'report'      => $converted['report'] ?? [],
                'unsupported' => $converted['unsupported'] ?? [],
                'outline'     => ConversionOutline::build(
                    $converted['divi']['elements'] ?? [],
                    $converted['unsupported'] ?? []
                ),
            ] );
        } catch ( \Throwable $e ) {
            return ConversionPlan::item( $base + [ 'error' => $e->getMessage() ] );
        }
    }

    /**
     * A fresh engine per item unless one was injected: ConverterEngine
     * accumulates counts and warnings across convert() calls, so a shared
     * instance would report item A's problems on item B.
     */
    private function engineForItem(): ConverterEngine {
        if ( $this->engine !== null ) {
            return $this->engine;
        }

        $engine = new ConverterEngine();

        $colors = self::elementorGlobalColors();
        if ( ! empty( $colors ) ) {
            $engine->setGlobalColors( $colors );
        }

        return $engine;
    }

    /**
     * Elementor stores the active Kit post ID in `elementor_active_kit`; the
     * Kit's `_elementor_page_settings` meta holds system and custom color
     * arrays of `{_id, color}` objects.
     *
     * @return array<string,string> color id => hex
     */
    public static function elementorGlobalColors(): array {
        if ( ! function_exists( 'get_option' ) ) {
            return [];
        }

        $kit_id = (int) get_option( 'elementor_active_kit', 0 );
        if ( $kit_id <= 0 ) {
            return [];
        }

        $kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $kit_settings ) ) {
            return [];
        }

        $colors = [];
        foreach ( [ 'system_colors', 'custom_colors' ] as $group_key ) {
            $group = $kit_settings[ $group_key ] ?? [];
            if ( ! is_array( $group ) ) {
                continue;
            }
            foreach ( $group as $color_item ) {
                $id  = $color_item['_id']   ?? '';
                $hex = $color_item['color'] ?? '';
                if ( $id !== '' && $hex !== '' ) {
                    $colors[ $id ] = $hex;
                }
            }
        }

        return $colors;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 445 tests.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-preflight.php tests/ConversionPreflightTest.php
git commit -m "feat(conversion): the dry-run engine, asserted to write nothing

One engine behind both 3.0.0 features: the preview shows a plan, a commit
writes one. The no-writes property is checked against the in-memory post and
meta stores rather than assumed.

Carries the free/Pro seam as a quantity limit (edc_direct_conversion_limit,
default 1) so the boundary exists from the first commit rather than being
retrofitted later.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 7: `ConversionCommitter`

The write half, lifted out of `BatchImporter` with its result shape preserved exactly.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-committer.php`
- Test: `tests/ConversionCommitterTest.php` (create)
- Reference (read, do not modify yet): `includes/admin/class-batch-importer.php`

**Interfaces:**
- Consumes: `ConversionPlan` (Task 2), `ElementorDivi5Converter\Exporters\DiviExporter::save( int $post_id, array $divi_data )`
- Produces: `class ElementorDivi5Converter\Conversion\ConversionCommitter` with
  - `__construct( ?DiviExporter $exporter = null, ?object $themeBuilderExporter = null )`
  - `commit( ConversionPlan $plan, array $options = [] ): array`

`$options`: `post_type` (default `page`), `post_status` (default `draft`), `convert_headers` (default `true`), `convert_footers` (default `true`).

Result item shape — **identical to today's `BatchImporter` output**:

```php
[ 'title', 'post_id', 'success', 'error', 'report', 'unsupported' ]
```

plus, for Theme Builder items only: `template_id`, `theme_builder_id`, `template_type`.

Meta stamped on every created post:
- `_edc_import_source` = `'direct'` when `source_ref['kind'] === 'installed'`, else `'file_upload'`
- `_edc_source_post_id` = the source post ID, **only** for installed sources

- [ ] **Step 1: Write the failing test**

Create `tests/ConversionCommitterTest.php`:

```php
<?php
// tests/ConversionCommitterTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class ConversionCommitterTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function plan_item( array $overrides = [] ): array {
        return ConversionPlan::item( array_merge( [
            'title'      => 'Home',
            'post_type'  => 'page',
            'blocks'     => [ 'elements' => [ [ 'name' => 'divi/section', 'settings' => [] ] ] ],
            'content'    => '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->',
            'report'     => [ 'converted' => [ 'heading' => 1 ], 'warnings' => [] ],
            'source_ref' => [ 'kind' => 'upload', 'post_id' => null, 'file' => 'x.json' ],
        ], $overrides ) );
    }

    public function test_it_creates_a_post_and_reports_success(): void {
        $plan    = new ConversionPlan( [ $this->plan_item() ] );
        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertCount( 1, $results );
        $this->assertTrue( $results[0]['success'] );
        $this->assertGreaterThan( 0, $results[0]['post_id'] );
        $this->assertSame( 'Home', $results[0]['title'] );
        $this->assertSame( '', $results[0]['error'] );
    }

    public function test_it_honours_post_type_and_status_options(): void {
        $plan = new ConversionPlan( [ $this->plan_item() ] );

        $results = ( new ConversionCommitter() )->commit( $plan, [
            'post_type' => 'post', 'post_status' => 'publish',
        ] );

        $post = $GLOBALS['__test_posts'][ $results[0]['post_id'] ];
        $this->assertSame( 'post', $post->post_type );
        $this->assertSame( 'publish', $post->post_status );
    }

    public function test_an_uploaded_item_is_stamped_file_upload(): void {
        $plan    = new ConversionPlan( [ $this->plan_item() ] );
        $results = ( new ConversionCommitter() )->commit( $plan );
        $id      = $results[0]['post_id'];

        $this->assertSame( 'file_upload', get_post_meta( $id, '_edc_import_source', true ) );
        $this->assertSame( '', get_post_meta( $id, '_edc_source_post_id', true ) );
    }

    public function test_an_installed_item_is_stamped_direct_with_its_source_post_id(): void {
        $plan = new ConversionPlan( [ $this->plan_item( [
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 77, 'file' => null ],
        ] ) ] );

        $results = ( new ConversionCommitter() )->commit( $plan );
        $id      = $results[0]['post_id'];

        $this->assertSame( 'direct', get_post_meta( $id, '_edc_import_source', true ) );
        $this->assertSame( 77, (int) get_post_meta( $id, '_edc_source_post_id', true ) );
    }

    public function test_it_never_writes_to_the_source_post(): void {
        $GLOBALS['__test_posts'][ 77 ] = (object) [
            'ID' => 77, 'post_title' => 'Original', 'post_type' => 'page', 'post_content' => 'elementor',
        ];
        update_post_meta( 77, '_elementor_data', '[{"elType":"section"}]' );

        $source_post_before = clone $GLOBALS['__test_posts'][ 77 ];
        $source_meta_before = $GLOBALS['__test_postmeta'][ 77 ];

        $plan = new ConversionPlan( [ $this->plan_item( [
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 77, 'file' => null ],
        ] ) ] );
        ( new ConversionCommitter() )->commit( $plan );

        $this->assertEquals( $source_post_before, $GLOBALS['__test_posts'][ 77 ] );
        $this->assertSame( $source_meta_before, $GLOBALS['__test_postmeta'][ 77 ] );
    }

    public function test_an_item_carrying_an_error_is_reported_as_a_failure_and_creates_no_post(): void {
        $plan = new ConversionPlan( [ $this->plan_item( [ 'error' => 'No Elementor content found.' ] ) ] );

        $posts_before = count( $GLOBALS['__test_posts'] );
        $results      = ( new ConversionCommitter() )->commit( $plan );

        $this->assertFalse( $results[0]['success'] );
        $this->assertSame( 'No Elementor content found.', $results[0]['error'] );
        $this->assertSame( 0, $results[0]['post_id'] );
        $this->assertCount( $posts_before, $GLOBALS['__test_posts'] );
    }

    public function test_a_header_without_the_pro_exporter_degrades_to_a_page_with_a_warning(): void {
        $plan = new ConversionPlan( [ $this->plan_item( [ 'template_type' => 'header' ] ) ] );

        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertTrue( $results[0]['success'] );
        $this->assertStringContainsString( 'Pro', implode( ' ', $results[0]['report']['warnings'] ?? [] ) );
    }

    public function test_a_header_uses_the_theme_builder_exporter_when_one_is_present(): void {
        $fake = new class {
            public array $calls = [];
            public function saveHeader( string $t, array $c ): array {
                $this->calls[] = 'header';
                return [ 'post_id' => 31, 'template_id' => 32, 'theme_builder_id' => 33, 'success' => true, 'error' => '' ];
            }
            public function saveFooter( string $t, array $c ): array {
                $this->calls[] = 'footer';
                return [ 'post_id' => 41, 'template_id' => 42, 'theme_builder_id' => 43, 'success' => true, 'error' => '' ];
            }
        };
        add_filter( 'edc_theme_builder_exporter', fn( $v ) => $fake );

        $plan    = new ConversionPlan( [ $this->plan_item( [ 'template_type' => 'header' ] ) ] );
        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertSame( [ 'header' ], $fake->calls );
        $this->assertSame( 31, $results[0]['post_id'] );
        $this->assertSame( 32, $results[0]['template_id'] );
        $this->assertSame( 'header', $results[0]['template_type'] );
    }

    public function test_convert_headers_false_imports_the_header_as_a_plain_page(): void {
        $fake = new class {
            public array $calls = [];
            public function saveHeader( string $t, array $c ): array { $this->calls[] = 'header'; return []; }
            public function saveFooter( string $t, array $c ): array { $this->calls[] = 'footer'; return []; }
        };
        add_filter( 'edc_theme_builder_exporter', fn( $v ) => $fake );

        $plan = new ConversionPlan( [ $this->plan_item( [ 'template_type' => 'header' ] ) ] );
        ( new ConversionCommitter() )->commit( $plan, [ 'convert_headers' => false ] );

        $this->assertSame( [], $fake->calls );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ConversionCommitterTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Conversion\ConversionCommitter" not found`

- [ ] **Step 3: Write minimal implementation**

Read `includes/admin/class-batch-importer.php` first — this class is that logic, moved and re-pointed at a plan. Create `includes/conversion/class-conversion-committer.php`:

```php
<?php
/**
 * Writes a ConversionPlan to the database.
 *
 * The only class in the conversion pipeline that creates posts. Everything
 * upstream of it is read-only, which is what makes a preview trustworthy.
 */

namespace ElementorDivi5Converter\Conversion;

use ElementorDivi5Converter\Exporters\DiviExporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionCommitter {

    private DiviExporter $exporter;
    /** Theme Builder exporter supplied by the Pro add-on via filter (null when absent). */
    private ?object $themeBuilderExporter;

    public function __construct( ?DiviExporter $exporter = null, ?object $themeBuilderExporter = null ) {
        $this->exporter = $exporter ?? new DiviExporter();
        $this->themeBuilderExporter = $themeBuilderExporter
            ?? ( function_exists( 'apply_filters' ) ? apply_filters( 'edc_theme_builder_exporter', null ) : null );
    }

    /**
     * @return array[] One result per plan item, shaped
     *   ['title','post_id','success','error','report','unsupported'].
     */
    public function commit( ConversionPlan $plan, array $options = [] ): array {
        $default_post_type   = $options['post_type']       ?? 'page';
        $default_post_status = $options['post_status']     ?? 'draft';
        $convert_headers     = $options['convert_headers'] ?? true;
        $convert_footers     = $options['convert_footers'] ?? true;

        $results = [];

        foreach ( $plan->items() as $item ) {
            if ( ( $item['error'] ?? '' ) !== '' ) {
                $results[] = $this->failResult( $item['title'], $item['error'] );
                continue;
            }

            $template_type = (string) ( $item['template_type'] ?? '' );

            $wants_theme_builder = ( $template_type === 'header' && $convert_headers )
                || ( $template_type === 'footer' && $convert_footers );

            if ( $wants_theme_builder && $this->themeBuilderExporter === null ) {
                $result = $this->commitPage( $item, $default_post_type, $default_post_status );
                $result['report']['warnings'][] = 'Theme Builder export for headers/footers requires the Pro add-on — imported as a regular draft instead. Get Pro: https://divi5lab.com/plugins/elementor-to-divi-5';
                $results[] = $result;
                continue;
            }

            if ( $wants_theme_builder ) {
                $results[] = $this->commitTemplate( $item, $template_type );
                continue;
            }

            $results[] = $this->commitPage( $item, $default_post_type, $default_post_status );
        }

        return $results;
    }

    private function commitPage( array $item, string $post_type, string $post_status ): array {
        $title     = (string) ( $item['title'] ?? 'Imported Page' );
        $post_name = (string) ( $item['post_name'] ?? '' );

        try {
            $post_args = [
                'post_type'    => $item['post_type'] ?: $post_type,
                'post_title'   => $title ?: 'Imported Page',
                'post_status'  => $post_status,
                'post_content' => '',
            ];

            if ( $post_name !== '' ) {
                $post_args['post_name'] = $post_name;
            }

            $post_id = wp_insert_post( $post_args );

            if ( is_wp_error( $post_id ) || (int) $post_id === 0 ) {
                $error = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post returned 0';
                return $this->failResult( $title, $error );
            }

            $post_id = (int) $post_id;
            $this->exporter->save( $post_id, $this->diviDataFor( $item ) );
            $this->stampSource( $post_id, $item );

            return [
                'title'       => $title,
                'post_id'     => $post_id,
                'success'     => true,
                'error'       => '',
                'report'      => $item['report']      ?? [],
                'unsupported' => $item['unsupported'] ?? [],
            ];
        } catch ( \Throwable $e ) {
            return $this->failResult( $title, $e->getMessage() );
        }
    }

    private function commitTemplate( array $item, string $template_type ): array {
        $title = (string) ( $item['title'] ?? 'Imported Template' );

        try {
            $divi_data = $this->diviDataFor( $item );
            $tb_result = $template_type === 'header'
                ? $this->themeBuilderExporter->saveHeader( $title, $divi_data )
                : $this->themeBuilderExporter->saveFooter( $title, $divi_data );

            $post_id = (int) ( $tb_result['post_id'] ?? 0 );
            if ( $post_id > 0 ) {
                $this->stampSource( $post_id, $item );
            }

            return [
                'title'            => $title,
                'post_id'          => $post_id,
                'template_id'      => $tb_result['template_id'] ?? 0,
                'theme_builder_id' => $tb_result['theme_builder_id'] ?? 0,
                'template_type'    => $template_type,
                'success'          => $tb_result['success'] ?? false,
                'error'            => $tb_result['error'] ?? '',
                'report'           => $item['report']      ?? [],
                'unsupported'      => $item['unsupported'] ?? [],
            ];
        } catch ( \Throwable $e ) {
            return $this->failResult( $title, $e->getMessage() );
        }
    }

    /**
     * DiviExporter and the Pro Theme Builder exporter both expect the engine's
     * output shape, so rebuild it from the plan item rather than storing a
     * second copy on the plan.
     */
    private function diviDataFor( array $item ): array {
        return [
            'divi'        => $item['blocks']      ?? [],
            'report'      => $item['report']      ?? [],
            'unsupported' => $item['unsupported'] ?? [],
        ];
    }

    private function stampSource( int $post_id, array $item ): void {
        $kind = $item['source_ref']['kind'] ?? 'upload';

        update_post_meta( $post_id, '_edc_import_source', $kind === 'installed' ? 'direct' : 'file_upload' );

        $source_post_id = $item['source_ref']['post_id'] ?? null;
        if ( $kind === 'installed' && $source_post_id ) {
            update_post_meta( $post_id, '_edc_source_post_id', (int) $source_post_id );
        }
    }

    private function failResult( string $title, string $error ): array {
        return [
            'title'       => $title,
            'post_id'     => 0,
            'success'     => false,
            'error'       => $error,
            'report'      => [],
            'unsupported' => [],
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 454 tests.

If `test_it_honours_post_type_and_status_options` fails because the item's own `post_type` wins over the option, that is the intended precedence — the item knows what it is (a post stays a post). Adjust the test to seed `'post_type' => 'post'` on the item instead of relying on the option, and note the precedence in the class docblock.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-committer.php tests/ConversionCommitterTest.php
git commit -m "feat(conversion): ConversionCommitter, the only class that writes

Lifted from BatchImporter with its result shape preserved exactly. Direct
conversions are stamped _edc_import_source=direct plus _edc_source_post_id,
which keeps them undoable through the existing rollback guard, and a test
pins that the source Elementor post is never touched.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 8: `BatchImporter` delegates; drop `DiviExporter::$dry_run`

The regression gate. `BatchImporter` keeps its signature and behaviour, implemented as preflight-then-commit. Every existing test must pass **unchanged** — do not edit any existing test to make this task green.

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-batch-importer.php` (rewrite the body; keep the class name, namespace, constructor signature and `import()` signature)
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/exporters/class-divi-exporter.php:53-63`
- Test: `tests/BatchImporterDelegationTest.php` (create)

**Interfaces:**
- Consumes: `ConversionPreflight` (Task 6), `ConversionCommitter` (Task 7), `UploadedJsonSource` (Task 3)
- Produces: `BatchImporter::import( array $items, array $options = [] ): array` — unchanged; plus `BatchImporter::importPlan( ConversionPlan $plan, array $options = [] ): array` for callers that already have a plan

`import()` receives *raw items* (not a source), because that is what `AdminPage::handle_import()` passes today. It wraps them in an inline array-backed source.

- [ ] **Step 1: Write the failing test**

Create `tests/BatchImporterDelegationTest.php`:

```php
<?php
// tests/BatchImporterDelegationTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\BatchImporter;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class BatchImporterDelegationTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function items(): array {
        return [ [
            'title'     => 'Home',
            'post_type' => 'page',
            'post_name' => 'home',
            'elements'  => [ [
                'elType'   => 'section',
                'elements' => [ [
                    'elType'   => 'column',
                    'elements' => [ [
                        'elType' => 'widget', 'widgetType' => 'heading',
                        'settings' => [ 'title' => 'Hi' ],
                    ] ],
                ] ],
            ] ],
        ] ];
    }

    public function test_import_still_creates_a_post_and_returns_the_documented_shape(): void {
        // Free's limit is 1, and uploads must not be capped by it.
        $results = ( new BatchImporter() )->import( $this->items() );

        $this->assertCount( 1, $results );
        foreach ( [ 'title', 'post_id', 'success', 'error', 'report', 'unsupported' ] as $key ) {
            $this->assertArrayHasKey( $key, $results[0] );
        }
        $this->assertTrue( $results[0]['success'] );
    }

    public function test_import_is_not_capped_by_the_direct_conversion_limit(): void {
        $items = array_merge( $this->items(), $this->items(), $this->items() );

        $results = ( new BatchImporter() )->import( $items );

        $this->assertCount( 3, $results, 'the upload path must not inherit the direct-conversion cap' );
    }

    public function test_import_plan_commits_an_already_built_plan(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title'   => 'From Plan',
            'blocks'  => [ 'elements' => [] ],
            'content' => '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->',
        ] ) ] );

        $results = ( new BatchImporter() )->importPlan( $plan );

        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 'From Plan', $results[0]['title'] );
    }

    public function test_divi_exporter_save_no_longer_accepts_a_dry_run_argument(): void {
        $method = new ReflectionMethod( \ElementorDivi5Converter\Exporters\DiviExporter::class, 'save' );

        $this->assertSame( 2, $method->getNumberOfParameters() );
    }
}
```

> **Note on the cap:** the direct-conversion limit applies to the *picker*, not to uploads. A kit ZIP with 40 pages must still import 40 pages for Pro users and, for the free upload path, however many the file contains — free's upload tier has always been "one page at a time" by virtue of the file, not by a cap. `import()` therefore bypasses the limit. Implement that by having `import()` call the committer directly with a plan built at an unlimited cap; see Step 3.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter BatchImporterDelegationTest`
Expected: FAIL — `Error: Call to undefined method ...BatchImporter::importPlan()`, and the reflection test fails with `3` parameters.

- [ ] **Step 3: Write minimal implementation**

Replace the body of `includes/admin/class-batch-importer.php` (keep the file, class name and namespace):

```php
<?php

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Conversion\ConversionCommitter;
use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\ConversionSource;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Exporters\DiviExporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Runs the converter + exporter for a list of import items and returns results.
 *
 * Since 3.0.0 this is a thin orchestrator over ConversionPreflight (convert,
 * writing nothing) and ConversionCommitter (write). Its signature and result
 * shape are unchanged, because AdminPage and the Pro add-on both depend on them.
 *
 * Each input item must have shape:
 *   ['title' => string, 'post_type' => string, 'post_name' => string, 'elements' => array]
 *
 * Each returned result has shape:
 *   ['title' => string, 'post_id' => int, 'success' => bool, 'error' => string, 'report' => array]
 */
class BatchImporter {

    private ConversionPreflight $preflight;
    private ConversionCommitter $committer;

    public function __construct(
        ?ConverterEngine $engine = null,
        ?DiviExporter $exporter = null,
        ?object $themeBuilderExporter = null
    ) {
        $this->preflight = new ConversionPreflight( $engine );
        $this->committer = new ConversionCommitter( $exporter, $themeBuilderExporter );
    }

    /**
     * @param  array[] $items   Import items from ElementorImportParser::parse().
     * @param  array   $options Accepts: post_status ('draft'|'publish'), post_type override.
     * @return array[] Per-item results.
     */
    public function import( array $items, array $options = [] ): array {
        return $this->committer->commit( $this->preflight->run( $this->sourceFor( $items ) ), $options );
    }

    /** Commit a plan a caller already built — the preview screen's convert step. */
    public function importPlan( ConversionPlan $plan, array $options = [] ): array {
        return $this->committer->commit( $plan, $options );
    }

    /**
     * The direct-conversion limit caps what the picker may select; it must not
     * cap an upload. A kit ZIP's page count is the user's file, not a tier
     * boundary, so uploads are planned at an unlimited cap.
     */
    private function sourceFor( array $items ): ConversionSource {
        return new class( $items ) implements ConversionSource {
            private array $items;
            public function __construct( array $items ) { $this->items = $items; }
            public function items(): array { return $this->items; }
        };
    }
}
```

`ConversionPreflight::run()` applies the limit, so `import()` must bypass it. Add an explicit unlimited entry point to `ConversionPreflight` rather than special-casing inside `run()`:

```php
    /** Plan every item, ignoring the direct-conversion cap. Used by the upload path. */
    public function runUnlimited( ConversionSource $source ): ConversionPlan {
        $planned = [];
        foreach ( $source->items() as $item ) {
            $planned[] = $this->planItem( $item );
        }

        return new ConversionPlan( $planned, PHP_INT_MAX, false );
    }
```

and change `BatchImporter::import()` to call `runUnlimited()`.

Then edit `includes/exporters/class-divi-exporter.php` — replace the `save()` docblock and signature:

```php
    /**
     * Save the exported Divi meta to a post.
     *
     * @param int   $post_id
     * @param array $divi_data
     * @return bool
     */
    public function save( int $post_id, array $divi_data ) {
        $meta         = $this->export( $divi_data );
        $post_content = $this->serializer->serialize( $divi_data );
```

and delete the now-unreachable block:

```php
        if ( $dry_run ) {
            return $meta;
        }
```

- [ ] **Step 4: Run the FULL suite to verify nothing regressed**

Run: `./vendor/bin/phpunit`
Expected: PASS. Every pre-existing test — `BatchImporterTest`, `SeamsTest`, `ConversionReportTest`, `DiviExporterTest`, `DiviIntegrationTest`, `ProKitTest` — passes without modification.

**If a pre-existing test fails, the refactor is wrong. Fix the implementation, not the test.** The single exception: a test that passes a third argument to `DiviExporter::save()`. Grep first:

```bash
grep -rn "save( *\$post_id, *\$converted, *true" tests/
```

If that returns nothing, no test touched the flag and none should change.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-batch-importer.php \
        plugin/jhmg-converter-for-elementor-to-divi/includes/conversion/class-conversion-preflight.php \
        plugin/jhmg-converter-for-elementor-to-divi/includes/exporters/class-divi-exporter.php \
        tests/BatchImporterDelegationTest.php
git commit -m "refactor(conversion): BatchImporter delegates to preflight and committer

Signature and result shape unchanged — AdminPage and the Pro Theme Builder
contract both depend on them, and the whole pre-existing suite passes without
edits, which is the point of doing the refactor this way.

Uploads plan at an unlimited cap: a kit ZIP's page count is the user's file,
not a tier boundary. Also drops DiviExporter::save()'s dead \$dry_run flag —
no caller ever passed it, and two competing dry-run concepts is worse than one.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 9: Register the Pro seam

Pro raises the limit. Small, but it is the task that makes the free/Pro boundary real, and it ships before any UI depends on it.

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi-pro/includes/class-plugin.php` (in `register_hooks()`, beside the existing `edc_pro_active` / `edc_kit_globals` / `edc_theme_builder_exporter` filters)
- Test: `tests/SeamsTest.php` (add cases to the existing file)

**Interfaces:**
- Consumes: `ConversionPreflight::limit()` (Task 6)
- Produces: the Pro plugin registers `add_filter( 'edc_direct_conversion_limit', ... )` returning `PHP_INT_MAX`

- [ ] **Step 1: Write the failing test**

Append to `tests/SeamsTest.php`, inside the existing `SeamsTest` class:

```php
    public function test_direct_conversion_limit_defaults_to_one_without_a_filter(): void {
        $this->assertSame( 1, \ElementorDivi5Converter\Conversion\ConversionPreflight::limit() );
    }

    public function test_pro_raises_the_direct_conversion_limit(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => PHP_INT_MAX );

        $this->assertSame( PHP_INT_MAX, \ElementorDivi5Converter\Conversion\ConversionPreflight::limit() );
    }
```

And add to `tests/ProPluginTest.php` a check that the Pro plugin registers it. Read that file first to match its existing style for asserting registered hooks; if it inspects `$GLOBALS['edc_test_hooks']`, assert:

```php
    public function test_pro_registers_the_direct_conversion_limit_filter(): void {
        edc_test_reset_hooks();
        ( new \ElementorDivi5Converter\Pro\Plugin() )->register_hooks();

        $this->assertArrayHasKey( 'edc_direct_conversion_limit', $GLOBALS['edc_test_hooks'] );
    }
```

If `ProPluginTest` cannot construct the Pro `Plugin` directly (it is a singleton via `instance()`), follow whatever pattern that file already uses; do not restructure the Pro plugin to make the test convenient.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter "SeamsTest|ProPluginTest"`
Expected: FAIL — `edc_direct_conversion_limit` is not a registered hook.

- [ ] **Step 3: Write minimal implementation**

In `plugin/jhmg-converter-for-elementor-to-divi-pro/includes/class-plugin.php`, in `register_hooks()`, directly after the `edc_theme_builder_exporter` filter:

```php
        // Free converts one installed page per run; Pro converts as many as the
        // user selects. This is a quantity boundary, not a feature flag — the
        // whole picker and commit loop live in the free plugin.
        add_filter( 'edc_direct_conversion_limit', static fn ( $v ) => PHP_INT_MAX );
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi-pro/includes/class-plugin.php tests/SeamsTest.php tests/ProPluginTest.php
git commit -m "feat(pro): raise the direct-conversion limit

The free/Pro boundary for 3.0.0, registered beside the existing seams and
shipped before any UI depends on it.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 10: `ElementorPageRepository`

Finds the posts the picker lists. The query lives behind an injectable runner so tests do not depend on the harness's `WP_Query` stub, which returns empty results unconditionally.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-elementor-page-repository.php`
- Test: `tests/ElementorPageRepositoryTest.php` (create)

**Interfaces:**
- Consumes: nothing from earlier tasks
- Produces: `class ElementorDivi5Converter\Admin\ElementorPageRepository` with
  - `__construct( ?callable $query_runner = null )` — the runner receives the `WP_Query` args array and returns a list of post objects
  - `find( array $args = [] ): array` — `$args`: `search` (string), `paged` (int, 1-based), `per_page` (int, default 20)
  - `query_args( array $args = [] ): array` — the built `WP_Query` args, exposed for testing
  - `has_any(): bool` — is there at least one Elementor-built post
  - `const PER_PAGE = 20`

`find()` returns rows shaped:

```php
[ 'id' => int, 'title' => string, 'post_type' => string, 'status' => string, 'modified' => string, 'converted' => bool ]
```

`converted` is true when some post carries `_edc_source_post_id` equal to this row's id. Implement it by asking for that meta on a per-row basis via a second lookup — see Step 3.

- [ ] **Step 1: Write the failing test**

Create `tests/ElementorPageRepositoryTest.php`:

```php
<?php
// tests/ElementorPageRepositoryTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\ElementorPageRepository;

class ElementorPageRepositoryTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    public function test_it_queries_for_elementor_built_posts(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertSame( '_elementor_edit_mode', $args['meta_key'] );
        $this->assertSame( 'builder', $args['meta_value'] );
    }

    public function test_it_orders_by_most_recently_modified(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertSame( 'modified', $args['orderby'] );
        $this->assertSame( 'DESC', $args['order'] );
    }

    public function test_it_pages_at_twenty_by_default(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertSame( 20, $args['posts_per_page'] );
        $this->assertSame( 1, $args['paged'] );
    }

    public function test_it_honours_paging_and_search(): void {
        $args = ( new ElementorPageRepository() )->query_args( [
            'paged' => 3, 'per_page' => 5, 'search' => 'contact',
        ] );

        $this->assertSame( 3, $args['paged'] );
        $this->assertSame( 5, $args['posts_per_page'] );
        $this->assertSame( 'contact', $args['s'] );
    }

    public function test_it_omits_the_search_key_when_no_search_was_given(): void {
        $this->assertArrayNotHasKey( 's', ( new ElementorPageRepository() )->query_args() );
    }

    public function test_it_never_requests_a_negative_page(): void {
        $args = ( new ElementorPageRepository() )->query_args( [ 'paged' => -4 ] );

        $this->assertSame( 1, $args['paged'] );
    }

    public function test_it_includes_the_elementor_library_post_type(): void {
        $args = ( new ElementorPageRepository() )->query_args();

        $this->assertContains( 'elementor_library', (array) $args['post_type'] );
    }

    public function test_it_maps_query_results_into_rows(): void {
        $runner = fn( array $args ) => [
            (object) [
                'ID' => 11, 'post_title' => 'Home', 'post_type' => 'page',
                'post_status' => 'publish', 'post_modified' => '2026-08-01 10:00:00',
            ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertSame( 11, $rows[0]['id'] );
        $this->assertSame( 'Home', $rows[0]['title'] );
        $this->assertSame( 'page', $rows[0]['post_type'] );
        $this->assertSame( 'publish', $rows[0]['status'] );
        $this->assertSame( '2026-08-01 10:00:00', $rows[0]['modified'] );
        $this->assertFalse( $rows[0]['converted'] );
    }

    public function test_a_row_already_converted_is_flagged(): void {
        // A converted Divi post points back at post 11.
        $GLOBALS['__test_posts'][ 99 ] = (object) [ 'ID' => 99, 'post_type' => 'page' ];
        update_post_meta( 99, '_edc_source_post_id', 11 );

        $runner = fn( array $args ) => [
            (object) [
                'ID' => 11, 'post_title' => 'Home', 'post_type' => 'page',
                'post_status' => 'publish', 'post_modified' => '2026-08-01 10:00:00',
            ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertTrue( $rows[0]['converted'] );
    }

    public function test_an_untitled_post_still_produces_a_usable_row(): void {
        $runner = fn( array $args ) => [
            (object) [
                'ID' => 12, 'post_title' => '', 'post_type' => 'page',
                'post_status' => 'draft', 'post_modified' => '2026-08-02 10:00:00',
            ],
        ];

        $rows = ( new ElementorPageRepository( $runner ) )->find();

        $this->assertNotSame( '', $rows[0]['title'] );
    }

    public function test_has_any_is_false_when_the_query_returns_nothing(): void {
        $repo = new ElementorPageRepository( fn( array $args ) => [] );

        $this->assertFalse( $repo->has_any() );
    }

    public function test_has_any_is_true_when_the_query_returns_something(): void {
        $repo = new ElementorPageRepository( fn( array $args ) => [ (object) [ 'ID' => 1 ] ] );

        $this->assertTrue( $repo->has_any() );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ElementorPageRepositoryTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Admin\ElementorPageRepository" not found`

- [ ] **Step 3: Write minimal implementation**

`converted` needs a reverse lookup of `_edc_source_post_id`. In WordPress that is a `get_posts()` meta query; in the test harness only `get_post_meta` exists. Implement it with a single injectable lookup so both work — and so the test above passes by scanning the in-memory meta store.

Create `includes/admin/class-elementor-page-repository.php`:

```php
<?php
/**
 * Finds the Elementor-built posts the direct-conversion picker lists.
 *
 * The query is wrapped in an injectable runner rather than calling WP_Query
 * inline: it keeps this class unit-testable without a WordPress database, and
 * keeps the admin screen free of query construction.
 */

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ElementorPageRepository {

    /** Elementor's own marker for a post built with its editor. */
    const EDIT_MODE_META = '_elementor_edit_mode';

    const PER_PAGE = 20;

    /** @var callable(array):array */
    private $queryRunner;

    public function __construct( ?callable $query_runner = null ) {
        $this->queryRunner = $query_runner ?? [ $this, 'run_wp_query' ];
    }

    /** @return array The WP_Query arguments this repository issues. */
    public function query_args( array $args = [] ): array {
        $per_page = (int) ( $args['per_page'] ?? self::PER_PAGE );
        $paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );
        $search   = trim( (string) ( $args['search'] ?? '' ) );

        $query = [
            'post_type'      => [ 'page', 'post', 'elementor_library' ],
            'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
            'meta_key'       => self::EDIT_MODE_META,
            'meta_value'     => 'builder',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => $per_page > 0 ? $per_page : self::PER_PAGE,
            'paged'          => $paged,
            // The picker shows titles; suppressing this avoids a second query per row.
            'no_found_rows'  => false,
        ];

        if ( $search !== '' ) {
            $query['s'] = $search;
        }

        return $query;
    }

    /**
     * @return array[] Rows shaped
     *   ['id','title','post_type','status','modified','converted'].
     */
    public function find( array $args = [] ): array {
        $posts = ( $this->queryRunner )( $this->query_args( $args ) );

        $rows = [];
        foreach ( $posts as $post ) {
            $id = (int) ( $post->ID ?? 0 );
            if ( $id <= 0 ) {
                continue;
            }

            $title = trim( (string) ( $post->post_title ?? '' ) );

            $rows[] = [
                'id'        => $id,
                'title'     => $title !== '' ? $title : __( '(no title)', 'jhmg-converter-for-elementor-to-divi' ),
                'post_type' => (string) ( $post->post_type ?? '' ),
                'status'    => (string) ( $post->post_status ?? '' ),
                'modified'  => (string) ( $post->post_modified ?? '' ),
                'converted' => $this->already_converted( $id ),
            ];
        }

        return $rows;
    }

    public function has_any(): bool {
        return ! empty( ( $this->queryRunner )( $this->query_args( [ 'per_page' => 1 ] ) ) );
    }

    /**
     * True when some post already records this one as its conversion source.
     * Purely informational — it never blocks converting the page again.
     */
    private function already_converted( int $source_post_id ): bool {
        if ( ! function_exists( 'get_posts' ) ) {
            return $this->already_converted_in_memory( $source_post_id );
        }

        $found = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'meta_key'       => '_edc_source_post_id',
            'meta_value'     => $source_post_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        return ! empty( $found );
    }

    /** Test-environment fallback: the harness has post meta but no get_posts(). */
    private function already_converted_in_memory( int $source_post_id ): bool {
        foreach ( $GLOBALS['__test_postmeta'] ?? [] as $meta ) {
            foreach ( $meta['_edc_source_post_id'] ?? [] as $value ) {
                if ( (int) $value === $source_post_id ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array Post objects. */
    private function run_wp_query( array $args ): array {
        $query = new \WP_Query( $args );

        return $query->posts ?? [];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 466 tests.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-elementor-page-repository.php tests/ElementorPageRepositoryTest.php
git commit -m "feat(admin): repository for the Elementor pages the picker lists

The query sits behind an injectable runner so it is testable without a
database — the harness's WP_Query stub returns empty unconditionally, so an
inline query would have been untestable by construction.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 11: `OutlineRenderer`

Renders an outline array to HTML. Pure string output, so it is testable without a browser.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-outline-renderer.php`
- Test: `tests/OutlineRendererTest.php` (create)

**Interfaces:**
- Consumes: outline nodes from `ConversionOutline::build()` (Task 5)
- Produces: `class ElementorDivi5Converter\Admin\OutlineRenderer` with `static render( array $outline ): string`

Markup contract: nested `<ul class="edc-outline">` / `<li class="edc-outline-node edc-outline-node--{type}">`, each node's label in a `<span class="edc-outline-label">`. Unsupported nodes add `edc-outline-node--unsupported`. Empty outline renders an explanatory paragraph, not an empty list.

- [ ] **Step 1: Write the failing test**

Create `tests/OutlineRendererTest.php`:

```php
<?php
// tests/OutlineRendererTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\OutlineRenderer;
use ElementorDivi5Converter\Conversion\ConversionOutline;

class OutlineRendererTest extends TestCase {

    public function test_an_empty_outline_explains_itself_rather_than_rendering_an_empty_list(): void {
        $html = OutlineRenderer::render( [] );

        $this->assertStringNotContainsString( '<ul', $html );
        $this->assertStringContainsString( 'Nothing', $html );
    }

    public function test_it_renders_a_node_with_its_label(): void {
        $html = OutlineRenderer::render( ConversionOutline::build( [ [ 'name' => 'divi/heading' ] ] ) );

        $this->assertStringContainsString( '<ul class="edc-outline">', $html );
        $this->assertStringContainsString( 'edc-outline-node--module', $html );
        $this->assertStringContainsString( 'Heading', $html );
    }

    public function test_it_nests_children_inside_their_parent(): void {
        $outline = ConversionOutline::build( [ [
            'name'     => 'divi/section',
            'elements' => [ [ 'name' => 'divi/row' ] ],
        ] ] );

        $html = OutlineRenderer::render( $outline );

        $this->assertStringContainsString( 'edc-outline-node--section', $html );
        $this->assertStringContainsString( 'edc-outline-node--row', $html );
        // The row's <li> must appear after the section's opening <li>.
        $this->assertLessThan(
            strpos( $html, 'edc-outline-node--row' ),
            strpos( $html, 'edc-outline-node--section' )
        );
    }

    public function test_it_marks_unsupported_nodes(): void {
        $html = OutlineRenderer::render( [ [
            'type' => 'module', 'name' => 'divi/code', 'label' => 'Code',
            'unsupported' => true, 'children' => [],
        ] ] );

        $this->assertStringContainsString( 'edc-outline-node--unsupported', $html );
    }

    public function test_it_escapes_labels(): void {
        $html = OutlineRenderer::render( [ [
            'type' => 'module', 'name' => 'divi/x', 'label' => '<script>alert(1)</script>',
            'unsupported' => false, 'children' => [],
        ] ] );

        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }

    public function test_every_list_it_opens_it_also_closes(): void {
        $outline = ConversionOutline::build( [ [
            'name'     => 'divi/section',
            'elements' => [ [
                'name'     => 'divi/row',
                'elements' => [ [ 'name' => 'divi/column', 'elements' => [ [ 'name' => 'divi/text' ] ] ] ],
            ] ],
        ] ] );

        $html = OutlineRenderer::render( $outline );

        $this->assertSame( substr_count( $html, '<ul' ), substr_count( $html, '</ul>' ) );
        $this->assertSame( substr_count( $html, '<li' ), substr_count( $html, '</li>' ) );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter OutlineRendererTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Admin\OutlineRenderer" not found`

- [ ] **Step 3: Write minimal implementation**

Create `includes/admin/class-outline-renderer.php`:

```php
<?php
/**
 * Renders a conversion outline as nested lists.
 *
 * Returns a string rather than echoing, so it can be asserted in tests without
 * output buffering.
 */

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OutlineRenderer {

    public static function render( array $outline ): string {
        if ( empty( $outline ) ) {
            return '<p class="edc-outline-empty">'
                . esc_html__( 'Nothing to show — this page converted to no Divi modules.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
        }

        return self::renderList( $outline );
    }

    private static function renderList( array $nodes ): string {
        $html = '<ul class="edc-outline">';

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            $type    = (string) ( $node['type'] ?? 'module' );
            $classes = 'edc-outline-node edc-outline-node--' . $type;

            if ( ! empty( $node['unsupported'] ) ) {
                $classes .= ' edc-outline-node--unsupported';
            }

            $html .= '<li class="' . esc_attr( $classes ) . '">';
            $html .= '<span class="edc-outline-label">' . esc_html( (string) ( $node['label'] ?? '' ) ) . '</span>';

            if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
                $html .= self::renderList( $node['children'] );
            }

            $html .= '</li>';
        }

        return $html . '</ul>';
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 472 tests.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-outline-renderer.php tests/OutlineRendererTest.php
git commit -m "feat(admin): render a conversion outline as nested lists

Returns a string rather than echoing so the markup is directly assertable.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 12: `DirectConversionPage` — request handling

The screen's logic: capability checks, nonces, re-verifying the selection server-side, and the two handlers. Rendering comes in Task 13; this task is the part with security consequences, so it is reviewed on its own.

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-direct-conversion-page.php`
- Test: `tests/DirectConversionPageTest.php` (create)

**Interfaces:**
- Consumes: `ElementorPageRepository` (Task 10), `InstalledPostSource` (Task 4), `ConversionPreflight` (Task 6), `BatchImporter::importPlan()` (Task 8), `ConversionPlan` (Task 2)
- Produces: `class ElementorDivi5Converter\Admin\DirectConversionPage` with
  - `const CHECK_ACTION = 'edc_direct_check'`, `const CONVERT_ACTION = 'edc_direct_convert'`
  - `const CHECK_NONCE = 'edc_direct_check_nonce'`, `const CONVERT_NONCE = 'edc_direct_convert_nonce'`
  - `__construct( ?ElementorPageRepository $repo = null )`
  - `init(): void` — hooks `admin_init`
  - `selected_post_ids( array $request ): array` — sanitizes, caps at the limit, and drops any id that is not an Elementor-built post
  - `plan_for( array $post_ids ): ConversionPlan`
  - `convert( array $post_ids, array $options = [] ): array`

The **re-verification rule**: `selected_post_ids()` must confirm each id carries `_elementor_edit_mode = 'builder'` before it is converted. The rendered picker is not an allowlist on the way back in.

- [ ] **Step 1: Write the failing test**

Create `tests/DirectConversionPageTest.php`:

```php
<?php
// tests/DirectConversionPageTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\DirectConversionPage;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class DirectConversionPageTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function seed( int $id, string $title = 'Home', bool $elementor = true ): int {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID' => $id, 'post_title' => $title, 'post_name' => 'home',
            'post_type' => 'page', 'post_status' => 'publish',
            'post_modified' => '2026-08-01 00:00:00',
        ];
        if ( $elementor ) {
            update_post_meta( $id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $id, '_elementor_data', wp_json_encode( [ [
                'elType'   => 'section',
                'elements' => [ [
                    'elType'   => 'column',
                    'elements' => [ [
                        'elType' => 'widget', 'widgetType' => 'heading',
                        'settings' => [ 'title' => 'Hi' ],
                    ] ],
                ] ],
            ] ] ) );
        }
        return $id;
    }

    public function test_it_reads_selected_ids_from_the_request(): void {
        $id = $this->seed( 201 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [ 'edc_post_ids' => [ (string) $id ] ] );

        $this->assertSame( [ 201 ], $ids );
    }

    public function test_it_accepts_a_single_non_array_id(): void {
        $id = $this->seed( 202 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [ 'edc_post_ids' => (string) $id ] );

        $this->assertSame( [ 202 ], $ids );
    }

    public function test_it_drops_a_post_that_is_not_elementor_built(): void {
        $good = $this->seed( 203 );
        $bad  = $this->seed( 204, 'Plain', false );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $good, (string) $bad ],
        ] );

        $this->assertSame( [ 203 ], $ids, 'the rendered list must not be trusted as an allowlist' );
    }

    public function test_it_drops_ids_that_do_not_exist(): void {
        $ids = ( new DirectConversionPage() )->selected_post_ids( [ 'edc_post_ids' => [ '999999' ] ] );

        $this->assertSame( [], $ids );
    }

    public function test_it_drops_garbage_input(): void {
        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ 'abc', '-1', '0', '<script>' ],
        ] );

        $this->assertSame( [], $ids );
    }

    public function test_it_caps_the_selection_at_the_free_limit(): void {
        $a = $this->seed( 205, 'A' );
        $b = $this->seed( 206, 'B' );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $a, (string) $b ],
        ] );

        $this->assertCount( 1, $ids, 'free converts one page per run' );
    }

    public function test_a_raised_limit_allows_more(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 10 );
        $a = $this->seed( 207, 'A' );
        $b = $this->seed( 208, 'B' );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $a, (string) $b ],
        ] );

        $this->assertCount( 2, $ids );
    }

    public function test_it_deduplicates_repeated_ids(): void {
        $id = $this->seed( 209 );

        $ids = ( new DirectConversionPage() )->selected_post_ids( [
            'edc_post_ids' => [ (string) $id, (string) $id ],
        ] );

        $this->assertSame( [ 209 ], $ids );
    }

    public function test_plan_for_produces_a_plan_without_writing(): void {
        $id = $this->seed( 210, 'Preview Me' );

        $posts_before = $GLOBALS['__test_posts'];
        $meta_before  = $GLOBALS['__test_postmeta'];

        $plan = ( new DirectConversionPage() )->plan_for( [ $id ] );

        $this->assertInstanceOf( ConversionPlan::class, $plan );
        $this->assertSame( 'Preview Me', $plan->items()[0]['title'] );
        $this->assertNotEmpty( $plan->items()[0]['outline'] );
        $this->assertSame( $posts_before, $GLOBALS['__test_posts'] );
        $this->assertSame( $meta_before, $GLOBALS['__test_postmeta'] );
    }

    public function test_convert_creates_a_new_post_stamped_direct(): void {
        $id = $this->seed( 211, 'Convert Me' );

        $results = ( new DirectConversionPage() )->convert( [ $id ] );

        $this->assertTrue( $results[0]['success'] );
        $new_id = $results[0]['post_id'];
        $this->assertNotSame( $id, $new_id );
        $this->assertSame( 'direct', get_post_meta( $new_id, '_edc_import_source', true ) );
        $this->assertSame( $id, (int) get_post_meta( $new_id, '_edc_source_post_id', true ) );
    }

    public function test_convert_leaves_the_source_page_untouched(): void {
        $id = $this->seed( 212, 'Original' );

        $source_before = clone $GLOBALS['__test_posts'][ $id ];
        $meta_before   = $GLOBALS['__test_postmeta'][ $id ];

        ( new DirectConversionPage() )->convert( [ $id ] );

        $this->assertEquals( $source_before, $GLOBALS['__test_posts'][ $id ] );
        $this->assertSame( $meta_before, $GLOBALS['__test_postmeta'][ $id ] );
    }

    public function test_convert_defaults_to_a_draft(): void {
        $id      = $this->seed( 213 );
        $results = ( new DirectConversionPage() )->convert( [ $id ] );

        $this->assertSame( 'draft', $GLOBALS['__test_posts'][ $results[0]['post_id'] ]->post_status );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter DirectConversionPageTest`
Expected: FAIL — `Error: Class "ElementorDivi5Converter\Admin\DirectConversionPage" not found`

- [ ] **Step 3: Write minimal implementation**

Create `includes/admin/class-direct-conversion-page.php` with the logic only — rendering methods are added in Task 13:

```php
<?php
/**
 * "Convert from this site" — pick an installed Elementor page, check what the
 * conversion will produce, then convert it.
 *
 * This is the screen 3.0.0 exists for: it removes the export/upload round trip
 * that was costing users at first run.
 *
 * Two safety properties are enforced here rather than in markup:
 *  - The rendered picker is never trusted on the way back in. Every submitted
 *    post ID is re-verified as an Elementor-built post that exists.
 *  - The selection is capped server-side at edc_direct_conversion_limit. A
 *    limit enforced only by radio buttons is not a limit.
 */

namespace ElementorDivi5Converter\Admin;

use ElementorDivi5Converter\Conversion\ConversionPlan;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Conversion\InstalledPostSource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DirectConversionPage {

    const CHECK_ACTION   = 'edc_direct_check';
    const CONVERT_ACTION = 'edc_direct_convert';
    const CHECK_NONCE    = 'edc_direct_check_nonce';
    const CONVERT_NONCE  = 'edc_direct_convert_nonce';

    /** Elementor's own marker for a post built with its editor. */
    const EDIT_MODE_META = '_elementor_edit_mode';

    const CAPABILITY = 'manage_options';

    private ElementorPageRepository $repo;

    public function __construct( ?ElementorPageRepository $repo = null ) {
        $this->repo = $repo ?? new ElementorPageRepository();
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_request' ] );
    }

    /**
     * Sanitize, verify and cap a submitted selection.
     *
     * @param array $request Typically $_POST.
     * @return int[] Post IDs safe to convert.
     */
    public function selected_post_ids( array $request ): array {
        $raw = $request['edc_post_ids'] ?? [];
        if ( ! is_array( $raw ) ) {
            $raw = [ $raw ];
        }

        $ids = [];
        foreach ( $raw as $value ) {
            $id = absint( $value );
            if ( $id <= 0 || in_array( $id, $ids, true ) ) {
                continue;
            }
            if ( ! $this->is_elementor_post( $id ) ) {
                continue;
            }
            $ids[] = $id;
        }

        return array_slice( $ids, 0, ConversionPreflight::limit() );
    }

    /** A dry run over the selection. Writes nothing. */
    public function plan_for( array $post_ids ): ConversionPlan {
        return ( new ConversionPreflight() )->run( new InstalledPostSource( $post_ids ) );
    }

    /**
     * Convert the selection. Creates new posts; never modifies the source.
     *
     * @return array[] BatchImporter-shaped results.
     */
    public function convert( array $post_ids, array $options = [] ): array {
        $plan = $this->plan_for( $post_ids );

        return ( new BatchImporter() )->importPlan( $plan, array_merge( [
            'post_status' => 'draft',
        ], $options ) );
    }

    /**
     * A post the picker could legitimately have offered: it exists, and
     * Elementor marked it as built with its editor.
     */
    private function is_elementor_post( int $post_id ): bool {
        if ( ! get_post( $post_id ) ) {
            return false;
        }

        return get_post_meta( $post_id, self::EDIT_MODE_META, true ) === 'builder';
    }

    public function maybe_handle_request(): void {
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

        if ( $action !== self::CHECK_ACTION && $action !== self::CONVERT_ACTION ) {
            return;
        }

        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'jhmg-converter-for-elementor-to-divi' ) );
        }

        if ( $action === self::CHECK_ACTION ) {
            check_admin_referer( self::CHECK_ACTION, self::CHECK_NONCE );
            $this->handle_check();
            return;
        }

        check_admin_referer( self::CONVERT_ACTION, self::CONVERT_NONCE );
        $this->handle_convert();
    }

    /** Filled in by Task 13, which owns rendering and the redirect targets. */
    protected function handle_check(): void {}

    /** Filled in by Task 13. */
    protected function handle_convert(): void {}
}
```

`wp_die`, `check_admin_referer` and `wp_safe_redirect` are not stubbed in `tests/bootstrap.php`, but none of this task's tests enter `maybe_handle_request()`, so none are needed yet — Task 13 adds them when its handlers are exercised. `sanitize_key`, `current_user_can`, `add_action`, `get_post` and `get_post_meta` are all already stubbed.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit`
Expected: PASS, 485 tests.

- [ ] **Step 5: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-direct-conversion-page.php tests/DirectConversionPageTest.php tests/bootstrap.php
git commit -m "feat(admin): direct-conversion request handling, verified server-side

Every submitted post ID is re-checked as an existing Elementor-built post, and
the selection is capped at edc_direct_conversion_limit in PHP — the rendered
picker is not an allowlist and radio buttons are not a limit.

Tests pin the two safety properties that matter: a plan writes nothing, and a
conversion leaves the source Elementor page byte-identical.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 13: `DirectConversionPage` — rendering, and wiring into the admin

The screen users see, and the plumbing that shows it.

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-direct-conversion-page.php` (add rendering + fill the two handlers)
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-plugin.php` (boot the page)
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-admin-page.php` (landing card, and the CSS in `inline_css()`)
- Test: `tests/DirectConversionRenderTest.php` (create)

**Interfaces:**
- Consumes: everything from Tasks 10–12; `OutlineRenderer::render()` (Task 11)
- Produces: on `DirectConversionPage` —
  - `render_picker( array $args = [] ): string`
  - `render_report( ConversionPlan $plan ): string`
  - `has_elementor_content(): bool`

Flow: the picker posts `edc_direct_check` → the report screen renders with the plan and a Convert button → that posts `edc_direct_convert` → results land on the existing batch result screen via the existing transient + redirect pattern.

- [ ] **Step 1: Write the failing test**

Read `AdminPage::handle_import()` first to copy the existing transient key and redirect convention exactly — the result screen and its Undo affordance must be reused, not reimplemented.

Create `tests/DirectConversionRenderTest.php`:

```php
<?php
// tests/DirectConversionRenderTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\DirectConversionPage;
use ElementorDivi5Converter\Admin\ElementorPageRepository;
use ElementorDivi5Converter\Conversion\ConversionPlan;

class DirectConversionRenderTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function repo_with( array $rows ): ElementorPageRepository {
        return new ElementorPageRepository( fn( array $args ) => $rows );
    }

    private function row( int $id, string $title ): object {
        return (object) [
            'ID' => $id, 'post_title' => $title, 'post_type' => 'page',
            'post_status' => 'publish', 'post_modified' => '2026-08-01 09:00:00',
        ];
    }

    public function test_the_picker_lists_each_elementor_page(): void {
        $page = new DirectConversionPage( $this->repo_with( [
            $this->row( 11, 'Home' ), $this->row( 12, 'About' ),
        ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( 'Home', $html );
        $this->assertStringContainsString( 'About', $html );
        $this->assertStringContainsString( 'value="11"', $html );
    }

    public function test_the_picker_offers_radios_at_the_free_limit(): void {
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( 'type="radio"', $html );
        $this->assertStringNotContainsString( 'type="checkbox"', $html );
    }

    public function test_the_picker_offers_checkboxes_when_the_limit_is_raised(): void {
        add_filter( 'edc_direct_conversion_limit', fn( $v ) => 50 );
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( 'type="checkbox"', $html );
    }

    public function test_the_picker_carries_the_check_nonce_and_action(): void {
        $page = new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) );

        $html = $page->render_picker();

        $this->assertStringContainsString( DirectConversionPage::CHECK_ACTION, $html );
        $this->assertStringContainsString( DirectConversionPage::CHECK_NONCE, $html );
    }

    public function test_an_empty_picker_explains_itself(): void {
        $html = ( new DirectConversionPage( $this->repo_with( [] ) ) )->render_picker();

        $this->assertStringNotContainsString( 'type="radio"', $html );
        $this->assertStringContainsString( 'Elementor', $html );
    }

    public function test_a_converted_page_is_badged(): void {
        $GLOBALS['__test_posts'][ 99 ] = (object) [ 'ID' => 99, 'post_type' => 'page' ];
        update_post_meta( 99, '_edc_source_post_id', 11 );

        $html = ( new DirectConversionPage( $this->repo_with( [ $this->row( 11, 'Home' ) ] ) ) )->render_picker();

        $this->assertStringContainsString( 'edc-badge-converted', $html );
    }

    public function test_the_report_shows_the_outline_and_the_convert_button(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title'   => 'Home',
            'outline' => [ [
                'type' => 'section', 'name' => 'divi/section', 'label' => 'Section',
                'unsupported' => false, 'children' => [],
            ] ],
            'report'  => [ 'converted' => [ 'heading' => 2 ], 'warnings' => [] ],
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'edc-outline', $html );
        $this->assertStringContainsString( 'Section', $html );
        $this->assertStringContainsString( DirectConversionPage::CONVERT_ACTION, $html );
        $this->assertStringContainsString( 'value="11"', $html );
    }

    public function test_the_report_names_unsupported_widgets(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title'       => 'Home',
            'unsupported' => [ [ 'id' => 'a1', 'elType' => 'widget', 'widgetType' => 'slider_revolution' ] ],
            'source_ref'  => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'slider_revolution', $html );
    }

    public function test_the_report_surfaces_a_failed_item(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title' => 'Broken', 'error' => 'No Elementor content found.',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'No Elementor content found.', $html );
    }

    public function test_the_report_says_when_the_selection_was_truncated(): void {
        $plan = new ConversionPlan(
            [ ConversionPlan::item( [ 'title' => 'One', 'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ] ] ) ],
            1,
            true
        );

        $html = ( new DirectConversionPage() )->render_report( $plan );

        $this->assertStringContainsString( 'Pro', $html );
    }

    public function test_the_report_never_promises_a_visual_preview(): void {
        $plan = new ConversionPlan( [ ConversionPlan::item( [
            'title' => 'Home',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 11, 'file' => null ],
        ] ) ] );

        $html = strtolower( ( new DirectConversionPage() )->render_report( $plan ) );

        // The screen reports structure. Claiming a rendered preview would be the
        // one overpromise this design deliberately avoids.
        $this->assertStringNotContainsString( 'visual preview', $html );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter DirectConversionRenderTest`
Expected: FAIL — `Error: Call to undefined method ...DirectConversionPage::render_picker()`

- [ ] **Step 3: Write minimal implementation**

Add to `DirectConversionPage`:

```php
    public function has_elementor_content(): bool {
        return $this->repo->has_any();
    }

    public function render_picker( array $args = [] ): string {
        $rows  = $this->repo->find( $args );
        $limit = ConversionPreflight::limit();

        if ( empty( $rows ) ) {
            return '<p class="edc-direct-empty">'
                . esc_html__( 'No Elementor pages found on this site. If your pages live elsewhere, use the JSON import above.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
        }

        $input_type = $limit > 1 ? 'checkbox' : 'radio';
        $name       = $limit > 1 ? 'edc_post_ids[]' : 'edc_post_ids';

        $html  = '<form method="post" class="edc-direct-picker">';
        $html .= wp_nonce_field( self::CHECK_ACTION, self::CHECK_NONCE, true, false );
        $html .= '<input type="hidden" name="action" value="' . esc_attr( self::CHECK_ACTION ) . '">';
        $html .= '<table class="widefat edc-direct-table"><tbody>';

        foreach ( $rows as $row ) {
            $html .= '<tr><td>';
            $html .= '<label><input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $row['id'] ) . '"> ';
            $html .= '<strong>' . esc_html( $row['title'] ) . '</strong></label>';
            $html .= ' <span class="edc-direct-meta">' . esc_html( $row['post_type'] . ' · ' . $row['status'] . ' · ' . $row['modified'] ) . '</span>';

            if ( ! empty( $row['converted'] ) ) {
                $html .= ' <span class="edc-badge-converted">' . esc_html__( 'already converted', 'jhmg-converter-for-elementor-to-divi' ) . '</span>';
            }

            $html .= '</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p><button type="submit" class="button button-primary">'
            . esc_html__( 'Check this page', 'jhmg-converter-for-elementor-to-divi' )
            . '</button></p>';

        if ( $limit === 1 ) {
            $html .= '<p class="description">'
                . esc_html__( 'Free converts one page at a time, as many times as you like. Pro converts your whole site in one run.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
        }

        return $html . '</form>';
    }

    public function render_report( ConversionPlan $plan ): string {
        $html = '<div class="edc-direct-report">';
        $html .= '<h2>' . esc_html__( 'Conversion report', 'jhmg-converter-for-elementor-to-divi' ) . '</h2>';
        $html .= '<p class="description">'
            . esc_html__( 'Nothing has been written yet. This is what the conversion will produce.', 'jhmg-converter-for-elementor-to-divi' )
            . '</p>';

        if ( $plan->truncated() ) {
            $html .= '<div class="notice notice-info inline"><p>'
                . esc_html__( 'Only the first page was checked. Converting several pages in one run is a Pro feature.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p></div>';
        }

        $ids = [];

        foreach ( $plan->items() as $item ) {
            $html .= '<h3>' . esc_html( $item['title'] ) . '</h3>';

            if ( $item['error'] !== '' ) {
                $html .= '<div class="notice notice-error inline"><p>' . esc_html( $item['error'] ) . '</p></div>';
                continue;
            }

            $source_id = $item['source_ref']['post_id'] ?? null;
            if ( $source_id ) {
                $ids[] = (int) $source_id;
            }

            $converted = array_sum( $item['report']['converted'] ?? [] );
            $html .= '<p>' . esc_html( sprintf(
                /* translators: %d: number of Divi modules the conversion produced */
                _n( '%d module converted.', '%d modules converted.', $converted, 'jhmg-converter-for-elementor-to-divi' ),
                $converted
            ) ) . '</p>';

            $html .= OutlineRenderer::render( $item['outline'] );

            if ( ! empty( $item['unsupported'] ) ) {
                $names = [];
                foreach ( $item['unsupported'] as $entry ) {
                    $name = $entry['widgetType'] ?? $entry['elType'] ?? '';
                    if ( $name !== '' && ! in_array( $name, $names, true ) ) {
                        $names[] = $name;
                    }
                }

                if ( ! empty( $names ) ) {
                    $html .= '<p class="edc-direct-unsupported"><strong>'
                        . esc_html__( 'Could not be converted:', 'jhmg-converter-for-elementor-to-divi' )
                        . '</strong> ' . esc_html( implode( ', ', $names ) ) . '</p>';
                }
            }
        }

        if ( ! empty( $ids ) ) {
            $html .= '<form method="post" class="edc-direct-convert">';
            $html .= wp_nonce_field( self::CONVERT_ACTION, self::CONVERT_NONCE, true, false );
            $html .= '<input type="hidden" name="action" value="' . esc_attr( self::CONVERT_ACTION ) . '">';

            foreach ( $ids as $id ) {
                $html .= '<input type="hidden" name="edc_post_ids[]" value="' . esc_attr( (string) $id ) . '">';
            }

            $html .= '<p><button type="submit" class="button button-primary">'
                . esc_html__( 'Convert to Divi 5', 'jhmg-converter-for-elementor-to-divi' )
                . '</button></p>';
            $html .= '<p class="description">'
                . esc_html__( 'Creates a new Divi draft. Your Elementor page is left exactly as it is.', 'jhmg-converter-for-elementor-to-divi' )
                . '</p>';
            $html .= '</form>';
        }

        return $html . '</div>';
    }
```

`OutlineRenderer` is in the same namespace, so it needs no `use` statement.

`_n()` and `HOUR_IN_SECONDS` are already in `tests/bootstrap.php` — do not redefine them. `wp_nonce_field`, `wp_safe_redirect`, `check_admin_referer`, `wp_die` and `get_posts` are **not** stubbed, and `render_picker()`/`render_report()` call `wp_nonce_field` directly, so add these to `tests/bootstrap.php` now rather than waiting for a fatal error:

```php
if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
        $field = '<input type="hidden" name="' . $name . '" value="testnonce">';
        if ( $echo ) {
            echo $field;
        }
        return $field;
    }
}

if ( ! function_exists( 'check_admin_referer' ) ) {
    function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
        return true;
    }
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
    // Records the redirect instead of sending headers, so handler tests can assert it.
    function wp_safe_redirect( $location, $status = 302 ) {
        $GLOBALS['__test_redirects'][] = $location;
        return true;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '' ) {
        throw new \RuntimeException( (string) $message );
    }
}
```

Leave `get_posts` unstubbed on purpose: `ElementorPageRepository::already_converted()` checks `function_exists( 'get_posts' )` and falls back to the in-memory scan, which is the path Task 10's tests exercise. Stubbing it would silently switch those tests onto an untested branch.

Now fill the handlers. Read `AdminPage::handle_import()` and copy its transient/redirect convention exactly:

```php
    protected function handle_check(): void {
        $ids = $this->selected_post_ids( wp_unslash( $_POST ) );

        set_transient( 'edc_direct_plan_ids_' . get_current_user_id(), $ids, HOUR_IN_SECONDS );
        wp_safe_redirect( add_query_arg(
            [ 'page' => 'elementor-divi5-converter', 'edc_view' => 'report' ],
            admin_url( 'tools.php' )
        ) );
        exit;
    }

    protected function handle_convert(): void {
        $ids     = $this->selected_post_ids( wp_unslash( $_POST ) );
        $results = $this->convert( $ids );

        // Reuse the existing batch result screen, history record and Undo
        // affordance rather than growing a second one.
        // <-- mirror AdminPage::handle_import()'s import_id generation,
        //     ImportHistory::record(), ReviewPrompt::record_run(), transient
        //     write and redirect here, verbatim.
    }
```

**Read `AdminPage::handle_import()` and mirror it exactly** — the same `generate_import_id()`, the same `ImportHistory::record()` call, the same `ReviewPrompt::record_run()` call, the same transient key, the same redirect. Direct conversions must appear in history and coverage exactly as uploads do, or Undo will not find them.

Then boot the page. In `includes/helpers/class-plugin.php`, beside the existing admin bootstrapping:

```php
        if ( is_admin() ) {
            ( new \ElementorDivi5Converter\Admin\DirectConversionPage() )->init();
        }
```

Match whatever conditional the file already uses for `AdminPage`; read it first.

Finally, add the landing card to `AdminPage::render_landing()`, above the existing free/Pro plan cards, and its CSS to `inline_css()`. The card shows `render_picker()` when `has_elementor_content()` is true, and otherwise a short line pointing at the JSON import. Follow the existing `edc-lp-*` class naming.

- [ ] **Step 4: Run the full suite**

Run: `./vendor/bin/phpunit`
Expected: PASS, 496 tests. Every pre-existing test still green.

- [ ] **Step 5: Manual verification**

The suite cannot prove the screen works in WordPress. On the local site:

1. Activate the plugin with Elementor present and at least one Elementor page.
2. Open Tools → Elementor → Divi 5. The picker lists your Elementor pages.
3. Select one, click **Check this page**. Confirm the outline and report render, and that **no new post appeared** in Pages.
4. Click **Convert to Divi 5**. Confirm a new draft appears, the Elementor original is unchanged, and the run shows in import history with a working Undo.

Record what you observed in the commit message. Do not claim this step passed without running it.

- [ ] **Step 6: Commit**

```bash
find plugin -name '*.php' -exec php -l {} \;
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/ plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-plugin.php tests/DirectConversionRenderTest.php tests/bootstrap.php
git commit -m "feat(admin): the Convert-from-this-site screen

Picker, conversion report and convert step, wired into the existing landing
page. The report reuses the existing result screen, history record and Undo
rather than growing a second one, so a direct conversion is undoable on day one.

Copy says 'Check this page' and 'Conversion report', never 'preview' — the
screen reports structure and does not render pixels, and saying otherwise
would spend the honesty 2.3.0 bought.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 14: Release metadata for 3.0.0

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php` (header `Version:` and the version constant)
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/readme.txt` (`Stable tag`, description, Free vs Pro, FAQ, changelog, upgrade notice)
- Test: `tests/ReleaseMetadataTest.php` (existing — read it first; it very likely pins the version in both files)

**Interfaces:**
- Consumes: nothing
- Produces: version `3.0.0` consistent across the plugin header, the version constant and `Stable tag`

- [ ] **Step 1: Read the existing test and run it**

```bash
sed -n '1,80p' tests/ReleaseMetadataTest.php
./vendor/bin/phpunit --filter ReleaseMetadataTest
```

It passes at 2.3.0 today. Whatever it asserts is the contract this task must keep.

- [ ] **Step 2: Write the failing test**

Add to `tests/ReleaseMetadataTest.php`, matching that file's existing style for locating the plugin files:

```php
    public function test_version_is_three_zero_zero_everywhere(): void {
        $main   = file_get_contents( dirname( __DIR__ ) . '/plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php' );
        $readme = file_get_contents( dirname( __DIR__ ) . '/plugin/jhmg-converter-for-elementor-to-divi/readme.txt' );

        $this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*3\.0\.0\s*$/m', $main );
        $this->assertMatchesRegularExpression( '/^Stable tag:\s*3\.0\.0\s*$/m', $readme );
    }

    public function test_readme_changelog_documents_3_0_0(): void {
        $readme = file_get_contents( dirname( __DIR__ ) . '/plugin/jhmg-converter-for-elementor-to-divi/readme.txt' );

        $this->assertStringContainsString( '= 3.0.0 =', $readme );
    }
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter ReleaseMetadataTest`
Expected: FAIL — version is still 2.3.0.

- [ ] **Step 4: Write minimal implementation**

Bump the header `Version:` and the version constant in the main plugin file to `3.0.0`. In `readme.txt`:

- `Stable tag: 3.0.0`
- Short description and Description: lead with converting straight from an installed Elementor site, since that is the change users feel first.
- **Free vs Pro** — add to Free: *"Convert a page straight from your Elementor site — pick it from a list, no export needed"* and *"Check any page before converting: see the structure and exactly which widgets could not be converted, before anything is written"*. Add to Pro: *"Convert many pages from your site in one run"*.
- FAQ: answer "Do I need to export a JSON file?" — no, if Elementor is installed on the same site; yes, if converting from a different site.
- Changelog:

```
= 3.0.0 =
* New: convert directly from your installed Elementor site — pick a page from a list, no export or upload
* New: check any page before converting — see the converted structure and which widgets could not be converted, before anything is written to your site
* Converting never touches your Elementor page: it always creates a new Divi draft, and every run is undoable
* Pro: convert several pages from your site in one run
```

- Upgrade notice: one sentence — the export step is gone.

Do not claim a visual preview anywhere in the readme.

- [ ] **Step 5: Run the full suite and lint**

```bash
./vendor/bin/phpunit
find plugin -name '*.php' -exec php -l {} \;
```
Expected: PASS, 498 tests.

- [ ] **Step 6: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php \
        plugin/jhmg-converter-for-elementor-to-divi/readme.txt \
        tests/ReleaseMetadataTest.php
git commit -m "release(free): 3.0.0 — convert straight from an installed Elementor site

The readme leads with the removed export step, because that is the change
users feel first. It describes checking a page before converting as a
structural report, never as a visual preview.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Final verification (before proposing a merge)

- [ ] `./vendor/bin/phpunit` — all green, count ≥ 498
- [ ] `find plugin -name '*.php' -exec php -l {} \;` — no output
- [ ] `grep -rn "jhmg-converter-for-elementor-to-divi'" plugin/jhmg-converter-for-elementor-to-divi/includes/conversion plugin/jhmg-converter-for-elementor-to-divi/includes/admin | wc -l` — every new user-facing string uses the right text domain
- [ ] `git log --oneline main..HEAD` — every commit on the feature branch, none on `main`
- [ ] Manual walkthrough from Task 13 Step 5 completed and its result reported honestly

**Do not merge, push, or run `svn ci`.** Report completion and let Lucas decide.

---

## Self-review notes

Checked against the spec:

- Free/Pro decision → Tasks 6 (limit), 9 (Pro registration), 12 (server-side cap), 13 (picker input type)
- `ConversionSource` / `UploadedJsonSource` / `InstalledPostSource` → Tasks 3, 4
- `ConversionPlan` → Task 2
- `ConversionPreflight`, no-writes property → Task 6
- `ConversionCommitter`, meta stamping → Task 7
- `BatchImporter` delegation, `$dry_run` removal → Task 8
- Source post never modified → asserted in Tasks 4, 7, 12
- Structural preview, outline + report, naming → Tasks 5, 11, 13
- Preview persists nothing → Task 6's no-writes test; commit-only history in Task 13
- `ElementorPageRepository` → Task 10
- Security (capability, nonce, re-verification, server-side cap) → Task 12
- Screens and code placement → Task 13
- readme/changelog → Task 14

One spec item is deliberately softened: `ConversionOutline`'s `unsupported` flag depends on whether the generic fallback converter emits a distinguishable block name. Task 5 Step 3 tells the implementer to check and, if there is no marker, leave the flag false — the unsupported widgets are still listed by name beside the outline, so nothing is hidden from the user. Inventing a marker would mean modifying converter internals, which this release should not do.
