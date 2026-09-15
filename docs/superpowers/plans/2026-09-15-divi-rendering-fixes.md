# Divi Rendering Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Converted pages render correctly in Divi 5.7.4, and a schema test plus a render check keep it that way.

**Architecture:** A committed extract of Divi 5.7.4's module definitions (`fixtures/divi-schema/modules.json`) backs a PHPUnit helper that validates every block the converter emits; it is called wherever the converter already produces output (fixtures, add-on probes, both kit ZIPs, the demo pages). A probe page and a Playwright spec on the demo stack assert what a viewer sees. Each rendering bug is then fixed handler by handler: failing test, smallest change, proof by both layers.

**Tech Stack:** PHP 8.x, PHPUnit 13 (`vendor/bin/phpunit`), Playwright 1.60 (`npx playwright test -c demo/playwright.config.ts`), the demo Docker stack on port 8040 (`demo/wp`, `demo/verify.sh`, `demo/reset.sh`).

**Spec:** `docs/superpowers/specs/2026-09-15-divi-rendering-fixes-design.md`

## Global Constraints

- Work only in `/Users/Lucas/Documents/JHMG-Local/jhmg-elementor-to-divi5-demo-site` on branch `fix/divi-rendering-2026-09`. Run every command from that directory. The demo stack's bind mounts point at this worktree's `plugin/`.
- Baseline: `vendor/bin/phpunit` → 696 tests, OK, one pre-existing PHPUnit deprecation. `vendor/bin/phpunit -c demo/phpunit.xml` → 22 tests. Both must pass at the end of every task.
- Never edit anything under `references/`. Never run `wp plugin delete` / `wp theme delete` against the demo stack.
- Ground every Divi attribute path in `references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<module>/module.json` and the renderer in `references/Divi/includes/builder-5/server/Packages/ModuleLibrary/<Module>/<Module>Module.php`; cite the file in the code comment. Read Elementor and add-on setting names from `references/elementor.4.1.3.zip`, `references/essential-addons-for-elementor-lite.6.6.7.zip`, `references/header-footer-elementor.2.8.8.zip` (unpacked copies are under the scratchpad `elementor/`, `eael/`, `hfe/`).
- Elementor does not store a control's default in `_elementor_data`; an absent key means the control's default.
- One fix per commit. Messages in repo style (`fix(converter): …`, `test(schema): …`, `feat(demo): …`) ending with `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- Report every loss: warnings for things the user must act on, `logNotCarriedOver()` for things Divi cannot express. Never drop silently.
- Do not release. Versions and changelog are bumped in the last task only.

## File Map

| File | Change |
|---|---|
| `scripts/divi-module-schema.php` | **New**: extracts `fixtures/divi-schema/modules.json` and `plugin/…/includes/data/fa-icons.php` from `references/Divi` (Task 1) |
| `fixtures/divi-schema/modules.json` | **New**, generated (Task 1) |
| `plugin/jhmg-converter-for-elementor-to-divi/includes/data/fa-icons.php` | **New**, generated FontAwesome name → unicode/weights (Task 1) |
| `tests/DiviModuleSchemaFixtureTest.php` | **New**: guard that the committed extracts match `references/Divi` (Task 1) |
| `tests/support/DiviModuleSchema.php` | **New**: `assertBlocksValid()` (Task 2) |
| `tests/support/divi-schema-known-gaps.php` | **New**: expected failures, emptied by Tasks 4–15, deleted in Task 15 (Task 2) |
| `tests/bootstrap.php`, `tests/ConverterFixtureTest.php`, `tests/AddonSettingNamesTest.php`, `demo/tests/php/DocumentConversionTest.php` | Call the helper (Task 2) |
| `tests/KitPagesSchemaTest.php` | **New**: both kit ZIPs through the converter (Task 2) |
| `demo/content/probes/core-widgets.php`, `demo/lib/documents.php`, `demo/lib/seed-probes.php`, `demo/lib/seed.php`, `demo/lib/commit-conversions.php`, `demo/lib/checks.sh`, `demo/verify.sh`, `demo/playwright.config.ts`, `demo/tests/render.spec.ts`, `demo/tests/php/ProbeDocumentTest.php`, `demo/lib/elementor.php`, `demo/README.md` | Render check (Task 3) |
| `plugin/…/includes/converter/handlers/class-column-converter.php`, `includes/stylemapper/class-style-mapper.php`, `fixtures/divi/column-overlay.json` | Column background (Task 4) |
| `plugin/…/includes/converter/class-text-heading.php`, `handlers/class-heading-converter.php`, `handlers/class-elementskit-heading-converter.php` | Heading level span/p/div (Task 5) |
| `includes/stylemapper/class-style-mapper.php`, `class-globals-resolver.php`, `includes/conversion/class-conversion-preflight.php`, `includes/helpers/class-plugin.php`, Pro `includes/kit/class-kit-globals-parser.php`, `handlers/class-button-converter.php` | Button cascade (Task 6) |
| `handlers/class-counter-converter.php`, `class-style-mapper.php`, `includes/admin/class-not-carried-over-renderer.php` | Counter (Task 7) |
| `includes/helpers/class-attachment-resolver.php`, `handlers/class-image-carousel-converter.php`, `class-eael-filterable-gallery-converter.php`, `class-gallery-converter.php`, `fixtures/divi/image-gallery-native.json` | Galleries (Task 8) |
| `handlers/class-google-maps-converter.php`, `fixtures/divi/google-maps-native.json`, `docs/known-issues.md` | Map (Task 9) |
| `handlers/class-social-icons-converter.php`, `class-hfe-navigation-menu-converter.php` | Social icons, menu (Task 10) |
| `includes/helpers/class-font-awesome-icons.php`, `handlers/class-eael-info-box-converter.php`, `class-eael-flip-box-converter.php`, `class-icon-box-converter.php`, `class-icon-converter.php`, `fixtures/divi/icon.json`, `fixtures/divi/image-box.json` | Blurbs and icons (Task 11) |
| `handlers/class-eael-pricing-table-converter.php`, `demo/content/pages/memberships.php` | Pricing table (Task 12) |
| `handlers/class-eael-team-member-converter.php`, `class-eael-testimonial-converter.php` | Team member, testimonial (Task 13) |
| `handlers/class-eael-countdown-converter.php` | Countdown (Task 14) |
| `handlers/class-eael-progress-bar-converter.php`, `class-eael-cta-box-converter.php`, `class-elementskit-heading-converter.php`, `class-eael-post-grid-converter.php`, `demo/content/pages/about.php`, `home.php`, `events.php` | Dropped content (Task 15) |
| Pro `includes/exporters/class-divi-theme-builder-exporter.php`, `tests/ThemeBuilderDedupeTest.php`, `demo/lib/theme-builder.php` | One default template (Task 16) |
| `includes/admin/class-hfe-conflict-notice.php`, `includes/helpers/class-plugin.php`, Pro `includes/admin/class-kit-page.php` | HFE notice (Task 17) |
| `includes/conversion/class-installed-post-source.php`, `includes/admin/class-elementor-page-repository.php`, `includes/parsers/class-elementor-import-parser.php`, Pro `includes/admin/class-kit-page.php` | HFE templates (Task 18) |
| `docs/known-issues.md`, `docs/conversion-map.md`, `readme.txt`, plugin headers, `tests/ReleaseMetadataTest.php`, `demo/versions.env`, `demo/output/kit-screenshots/` | Docs, versions, proof (Task 19) |

---

### Task 1: Extract Divi 5.7.4's module schema and FontAwesome map from references/Divi

**Files:**
- Create: `scripts/divi-module-schema.php`
- Create: `fixtures/divi-schema/modules.json` (generated)
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/data/fa-icons.php` (generated)
- Test: `tests/DiviModuleSchemaFixtureTest.php`

**Interfaces:**
- Produces: `fixtures/divi-schema/modules.json` with shape `{"diviVersion": "5.7.4", "modules": {"divi/<name>": {"childrenName": string[], "customCssFields": string[], "attributes": {"<attr>": {"elementType": string, "innerContent"?: string[], "decoration"?: string[], "advanced"?: string[], "meta"?: string[]}}}}}`. In `innerContent`, the list holds the value's allowed sub-keys (`subName`s); `"*"` means a sub-group with no enumerable keys exists; an empty list means the value is a scalar.
- Produces: `includes/data/fa-icons.php` returning `array<string, array{unicode: string, solid?: string, line?: string}>` keyed by FontAwesome name (`facebook-f`, `clock`), the values being the font weight for that style.
- Produces: `edc_divi_schema_build(string $components_dir, string $divi_style_css): array` and `edc_fa_icons_build(string $icons_json): array` in the script, reused by the guard test.

- [ ] **Step 1: Write the guard test**

Create `tests/DiviModuleSchemaFixtureTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;

/**
 * The committed schema extract must match the Divi checked into references/.
 * Regenerate with: php scripts/divi-module-schema.php
 */
final class DiviModuleSchemaFixtureTest extends TestCase {
    private const ROOT = __DIR__ . '/..';

    protected function setUp(): void {
        if ( ! is_dir( self::ROOT . '/references/Divi/includes/builder-5' ) ) {
            $this->markTestSkipped( 'references/Divi is not present; the committed extract cannot be checked.' );
        }
        require_once self::ROOT . '/scripts/divi-module-schema.php';
    }

    public function test_committed_module_schema_matches_references_divi(): void {
        $expected = edc_divi_schema_build(
            self::ROOT . '/references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components',
            self::ROOT . '/references/Divi/style.css'
        );
        $committed = json_decode( (string) file_get_contents( self::ROOT . '/fixtures/divi-schema/modules.json' ), true );

        $this->assertSame( $expected, $committed, 'fixtures/divi-schema/modules.json is stale: run php scripts/divi-module-schema.php' );
    }

    public function test_committed_fa_icons_match_references_divi(): void {
        $expected  = edc_fa_icons_build( self::ROOT . '/references/Divi/includes/builder/feature/icon-manager/full_icons_list.json' );
        $committed = require self::ROOT . '/plugin/jhmg-converter-for-elementor-to-divi/includes/data/fa-icons.php';

        $this->assertSame( $expected, $committed, 'includes/data/fa-icons.php is stale: run php scripts/divi-module-schema.php' );
        $this->assertSame( [ 'unicode' => '&#xf39e;', 'solid' => '400' ], $committed['facebook-f'] );
        $this->assertSame( [ 'unicode' => '&#xf017;', 'solid' => '900', 'line' => '400' ], $committed['clock'] );
    }

    public function test_schema_records_the_paths_this_work_depends_on(): void {
        $schema = json_decode( (string) file_get_contents( self::ROOT . '/fixtures/divi-schema/modules.json' ), true );
        $m      = $schema['modules'];

        $this->assertSame( '5.7.4', $schema['diviVersion'] );
        $this->assertContains( 'background', $m['divi/column']['attributes']['module']['decoration'] );
        $this->assertContains( 'dateTime', $m['divi/countdown-timer']['attributes']['content']['advanced'] );
        $this->assertContains( 'enablePercentSign', $m['divi/number-counter']['attributes']['number']['advanced'] );
        $this->assertContains( 'galleryIds', $m['divi/gallery']['attributes']['image']['advanced'] );
        $this->assertSame( [ 'currency', 'per' ], $m['divi/pricing-table']['attributes']['currencyFrequency']['innerContent'] );
        $this->assertContains( 'text', $m['divi/button']['attributes']['button']['innerContent'] );
        $this->assertContains( '*', $m['divi/button']['attributes']['button']['innerContent'] );
        $this->assertSame( [ 'src' ], $m['divi/testimonial']['attributes']['portrait']['innerContent'] );
        $this->assertSame( 'headingLink', $m['divi/blurb']['attributes']['title']['elementType'] );
        $this->assertSame( [ 'divi/pricing-table' ], $m['divi/pricing-tables']['childrenName'] );
        $this->assertSame( [ 'divi/social-media-follow-network' ], $m['divi/social-media-follow']['childrenName'] );
    }
}
```

- [ ] **Step 2: Run it to see it fail**

Run: `vendor/bin/phpunit tests/DiviModuleSchemaFixtureTest.php`
Expected: errors — `scripts/divi-module-schema.php` does not exist.

- [ ] **Step 3: Write the generator**

Create `scripts/divi-module-schema.php`:

```php
<?php
/**
 * Extracts what the converter's tests need from Divi's generated module definitions.
 *
 *   php scripts/divi-module-schema.php          # rewrites the two generated files
 *
 * Sources (never edited):
 *   references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<module>/module.json
 *   references/Divi/includes/builder/feature/icon-manager/full_icons_list.json
 *
 * Outputs:
 *   fixtures/divi-schema/modules.json                                   (tests/support/DiviModuleSchema.php)
 *   plugin/jhmg-converter-for-elementor-to-divi/includes/data/fa-icons.php  (FontAwesomeIcons helper)
 */
declare( strict_types=1 );

/** @return array{diviVersion: string, modules: array<string, array>} */
function edc_divi_schema_build( string $components_dir, string $divi_style_css ): array {
    $version = '';
    if ( preg_match( '/^Version:\s*(\S+)/m', (string) file_get_contents( $divi_style_css ), $m ) ) {
        $version = $m[1];
    }

    $modules = [];
    foreach ( glob( rtrim( $components_dir, '/' ) . '/*/module.json' ) as $file ) {
        $json = json_decode( (string) file_get_contents( $file ), true );
        if ( ! is_array( $json ) || empty( $json['name'] ) ) {
            continue;
        }

        $attributes = [];
        foreach ( $json['attributes'] ?? [] as $attr_name => $spec ) {
            if ( ! is_array( $spec ) ) {
                continue;
            }
            $entry = [ 'elementType' => (string) ( $spec['elementType'] ?? '' ) ];
            foreach ( $spec['settings'] ?? [] as $group => $config ) {
                if ( $group === 'innerContent' ) {
                    $entry['innerContent'] = edc_divi_schema_sub_names( is_array( $config ) ? $config : [] );
                } elseif ( is_array( $config ) ) {
                    $entry[ $group ] = array_values( array_map( 'strval', array_keys( $config ) ) );
                }
            }
            $attributes[ $attr_name ] = $entry;
        }

        $children = $json['childrenName'] ?? [];
        $modules[ $json['name'] ] = [
            'childrenName'    => is_array( $children ) ? array_values( $children ) : [],
            'customCssFields' => array_values( array_map( 'strval', array_keys( $json['customCssFields'] ?? [] ) ) ),
            'attributes'      => $attributes,
        ];
    }
    ksort( $modules );

    return [ 'diviVersion' => $version, 'modules' => $modules ];
}

/**
 * The sub-keys an innerContent value may hold. Divi declares them as `subName`
 * on `group-items` / `into-multiple-groups` entries; a `group-item` with a
 * subName is a one-key object (testimonial portrait: src); a `group-item`
 * without one is a scalar. A component group with no subNames (the button link
 * group) cannot be enumerated here and is recorded as "*".
 *
 * @return string[]
 */
function edc_divi_schema_sub_names( array $config ): array {
    $names = [];
    $open  = false;

    $collect = static function ( array $item ) use ( &$names, &$open ): void {
        if ( isset( $item['subName'] ) && $item['subName'] !== '' ) {
            $names[] = (string) $item['subName'];
        } else {
            $open = true;
        }
    };

    switch ( $config['groupType'] ?? '' ) {
        case 'group-item':
            if ( isset( $config['item']['subName'] ) ) {
                $names[] = (string) $config['item']['subName'];
            }
            break;
        case 'group-items':
            foreach ( $config['items'] ?? [] as $item ) {
                $collect( is_array( $item ) ? $item : [] );
            }
            break;
        case 'into-multiple-groups':
            foreach ( $config['groups'] ?? [] as $group ) {
                $collect( is_array( $group['item'] ?? null ) ? $group['item'] : [] );
            }
            break;
        default:
            break;
    }

    $names = array_values( array_unique( $names ) );
    if ( $open ) {
        $names[] = '*';
    }
    return $names;
}

/** @return array<string, array{unicode: string, solid?: string, line?: string}> */
function edc_fa_icons_build( string $icons_json ): array {
    $list  = json_decode( (string) file_get_contents( $icons_json ), true );
    $icons = [];
    foreach ( is_array( $list ) ? $list : [] as $entry ) {
        $styles = $entry['styles'] ?? [];
        if ( ! is_array( $styles ) || ! in_array( 'fa', $styles, true ) ) {
            continue;
        }
        $name   = strtolower( (string) ( $entry['name'] ?? '' ) );
        $style  = in_array( 'line', $styles, true ) ? 'line' : 'solid';
        $weight = (string) ( $entry['font_weight'] ?? '400' );
        if ( $name === '' ) {
            continue;
        }
        $icons[ $name ]['unicode'] = (string) $entry['unicode'];
        $icons[ $name ][ $style ]  = $weight;
    }
    ksort( $icons );
    return $icons;
}

if ( PHP_SAPI === 'cli' && realpath( $argv[0] ?? '' ) === __FILE__ ) {
    $root   = dirname( __DIR__ );
    $schema = edc_divi_schema_build(
        $root . '/references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components',
        $root . '/references/Divi/style.css'
    );
    if ( ! is_dir( $root . '/fixtures/divi-schema' ) ) {
        mkdir( $root . '/fixtures/divi-schema', 0755, true );
    }
    file_put_contents(
        $root . '/fixtures/divi-schema/modules.json',
        json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
    );

    $icons = edc_fa_icons_build( $root . '/references/Divi/includes/builder/feature/icon-manager/full_icons_list.json' );
    $php   = "<?php\n// Generated by scripts/divi-module-schema.php from Divi {$schema['diviVersion']}'s\n"
        . "// includes/builder/feature/icon-manager/full_icons_list.json. Do not edit.\n"
        . "// FontAwesome name => unicode entity and the font weight per style.\n"
        . 'return ' . var_export( $icons, true ) . ";\n";
    $data_dir = $root . '/plugin/jhmg-converter-for-elementor-to-divi/includes/data';
    if ( ! is_dir( $data_dir ) ) {
        mkdir( $data_dir, 0755, true );
    }
    file_put_contents( $data_dir . '/fa-icons.php', $php );

    printf( "Divi %s: %d modules, %d FontAwesome icons.\n", $schema['diviVersion'], count( $schema['modules'] ), count( $icons ) );
}
```

- [ ] **Step 4: Generate and run the guard test**

Run: `php scripts/divi-module-schema.php && vendor/bin/phpunit tests/DiviModuleSchemaFixtureTest.php`
Expected: `Divi 5.7.4: 108 modules, N FontAwesome icons.` then 3 tests OK. If an assertion in `test_schema_records_the_paths_this_work_depends_on` fails, the extract is wrong, not the assertion: compare with the module.json by hand and fix `edc_divi_schema_build()`.

- [ ] **Step 5: Commit**

```bash
git add scripts/divi-module-schema.php fixtures/divi-schema/modules.json plugin/jhmg-converter-for-elementor-to-divi/includes/data/fa-icons.php tests/DiviModuleSchemaFixtureTest.php
git commit -m "test(schema): extract Divi 5.7.4's module attribute trees and FontAwesome map

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: Schema assertion helper, wired into every place the converter produces output

**Files:**
- Create: `tests/support/DiviModuleSchema.php`
- Create: `tests/support/divi-schema-known-gaps.php`
- Create: `tests/KitPagesSchemaTest.php`
- Modify: `tests/bootstrap.php` (end of file), `tests/ConverterFixtureTest.php`, `tests/AddonSettingNamesTest.php` (`convert()`), `demo/tests/php/DocumentConversionTest.php`

**Interfaces:**
- Produces: `DiviModuleSchema::assertBlocksValid( array $blocks, string $context ): void` — `$blocks` is `$result['divi']['elements']` (a list of blocks `{name, settings, elements}`); throws `PHPUnit\Framework\AssertionFailedError` listing every problem as `"<context>: <block name> <dotted path> — <reason>"`. Problems listed in `tests/support/divi-schema-known-gaps.php` are counted, not reported; a listed gap that no longer occurs is reported as stale so the file shrinks honestly.
- Produces: `DiviModuleSchema::problems( array $blocks ): string[]` for callers that want the list.

- [ ] **Step 1: Write the helper**

Create `tests/support/DiviModuleSchema.php`:

```php
<?php
/**
 * Checks converter output against Divi 5.7.4's module definitions
 * (fixtures/divi-schema/modules.json, extracted by scripts/divi-module-schema.php).
 *
 * Value-key allow-lists are taken from the server-side style declarations under
 * references/Divi/includes/builder-5/server/Packages/Module/Options/<Group>/ and
 * from the renderers named next to each entry.
 */
final class DiviModuleSchema {
    private const BREAKPOINTS = [ 'desktop', 'tablet', 'phone' ];
    private const STATES      = [ 'value', 'hover', 'sticky' ];

    /** Options/Font/Style/StyleDeclarations.php + Font/FontStyle.php */
    private const FONT_KEYS = [ 'family', 'size', 'weight', 'style', 'color', 'lineHeight', 'letterSpacing', 'textAlign', 'headingLevel' ];
    /** Options/TextShadow/Style/StyleDeclarations.php */
    private const TEXT_SHADOW_KEYS = [ 'style', 'horizontal', 'vertical', 'blur', 'color' ];

    /**
     * Groups whose value sits directly under {breakpoint}.{state}: allowed value keys,
     * or null when the value is a scalar or the keys are not checked.
     */
    private const RESPONSIVE_GROUPS = [
        // Options/Background/Style/StyleDeclarations.php
        'background' => [ 'color', 'image', 'gradient', 'mask', 'pattern', 'video' ],
        // Options/Spacing/Style/StyleDeclarations.php
        'spacing'    => [ 'margin', 'padding' ],
        // Options/Sizing/Style/StyleDeclarations.php
        'sizing'     => [ 'width', 'maxWidth', 'minWidth', 'height', 'minHeight', 'maxHeight', 'alignment', 'flex', 'flexBasis', 'flexGrow', 'flexShrink', 'alignSelf' ],
        // Options/Border/Style/StyleDeclarations.php
        'border'     => [ 'radius', 'styles' ],
        // Options/BoxShadow/Style/StyleDeclarations.php
        'boxShadow'  => [ 'style', 'horizontal', 'vertical', 'blur', 'spread', 'color', 'position' ],
        // Options/Layout/Style/StyleDeclarations.php
        'layout'     => [ 'display', 'flexDirection', 'flexWrap', 'justifyContent', 'alignItems', 'alignContent', 'columnGap', 'rowGap', 'gridColumnCount', 'gridColumnWidths', 'gridTemplateColumns', 'gridRowCount', 'gridRowHeights', 'gridTemplateRows', 'gridAutoFlow', 'gridJustifyItems', 'gridAlignItems', 'gridOffsetRules', 'collapseEmptyColumns', 'gap' ],
        // Options/Link/LinkUtils.php
        'link'       => [ 'url', 'target', 'rel' ],
        // Options/Button/Style/StyleDeclarations.php
        'button'     => [ 'enable', 'icon', 'alignment' ],
        // Options/Overflow/Style/StyleDeclarations.php
        'overflow'   => [ 'x', 'y' ],
        // Options/Position/Style/StyleDeclarations.php
        'position'   => [ 'mode', 'origin', 'offset' ],
        // Options/Filters/Style/StyleDeclarations.php
        'filters'    => [ 'hueRotate', 'saturate', 'brightness', 'contrast', 'invert', 'sepia', 'opacity', 'blur', 'blendMode' ],
        'zIndex'     => null,
        'disabledOn' => null,
        'attributes' => null,
        'animation'  => null,
        'transform'  => null,
        'transition' => null,
        'conditions' => null,
        'interactions' => null,
        'order'      => null,
        'scroll'     => null,
        'sticky'     => null,
        'fit'        => null,
        'image'      => null,
        'icon'       => null,
    ];

    /** Groups with sub-groups before the breakpoint. */
    private const SUB_GROUPED = [
        'font'        => [ 'font' => 'FONT', 'textShadow' => 'TEXT_SHADOW', 'textEffects' => null ],
        // Options/Text/Style/StyleDeclarations.php
        'text'        => [ 'text' => [ 'orientation', 'color' ], 'textShadow' => 'TEXT_SHADOW' ],
        'bodyFont'    => [ 'body' => 'FONT_GROUP', 'link' => 'FONT_GROUP', 'ul' => 'FONT_GROUP', 'ol' => 'FONT_GROUP', 'quote' => 'FONT_GROUP' ],
        'headingFont' => [ 'h1' => 'FONT_GROUP', 'h2' => 'FONT_GROUP', 'h3' => 'FONT_GROUP', 'h4' => 'FONT_GROUP', 'h5' => 'FONT_GROUP', 'h6' => 'FONT_GROUP' ],
    ];

    /**
     * innerContent value shapes the renderers read, where module.json alone is not
     * enough. Cited per entry.
     */
    private const INNER_CONTENT_OVERRIDES = [
        // TeamMemberModule.php:98 reads ['url']; the subName in module.json is also url.
        'divi/team-member image'      => [ 'url', 'alt', 'animation', 'title' ],
        // TestimonialModule.php:98 reads ['src'] ?? ['url'].
        'divi/testimonial portrait'   => [ 'src', 'url', 'alt', 'animation' ],
        // BlurbModule.php:171,607 read useIcon, src; icon is an icon object.
        'divi/blurb imageIcon'        => [ 'useIcon', 'icon', 'src', 'alt', 'title', 'animation' ],
        // IconModule.php:197 reads the icon object.
        'divi/icon icon'              => [ 'unicode', 'type', 'weight' ],
    ];

    private static ?array $schema = null;
    private static array $gaps    = [];

    public static function assertBlocksValid( array $blocks, string $context ): void {
        $problems = self::problems( $blocks );
        $known    = self::knownGaps();
        $report   = [];
        $matched  = [];

        foreach ( $problems as $problem ) {
            $gap = self::matchGap( $problem, $known );
            if ( $gap === null ) {
                $report[] = "{$context}: {$problem}";
            } else {
                $matched[ $gap ] = true;
            }
        }

        PHPUnit\Framework\Assert::assertSame( [], $report, "Converter output that Divi 5.7.4 will not render:\n" . implode( "\n", $report ) );
        self::$gaps = array_merge( self::$gaps, array_keys( $matched ) );
    }

    /** Gaps in the known list that no fixture, probe or page has hit this run. */
    public static function unusedKnownGaps(): array {
        return array_values( array_diff( array_keys( self::knownGaps() ), array_unique( self::$gaps ) ) );
    }

    /** @return string[] "<block name> <dotted path> — <reason>" */
    public static function problems( array $blocks, ?string $parent = null ): array {
        $problems = [];
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }
            $name   = (string) ( $block['name'] ?? '' );
            $module = self::schema()['modules'][ $name ] ?? null;
            if ( $module === null ) {
                $problems[] = "{$name} — not a Divi 5.7.4 module";
                continue;
            }
            if ( $parent !== null ) {
                $allowed = self::schema()['modules'][ $parent ]['childrenName'] ?? [];
                if ( ! empty( $allowed ) && ! in_array( $name, $allowed, true ) ) {
                    $problems[] = "{$name} — not an allowed child of {$parent} (" . implode( ', ', $allowed ) . ')';
                }
            }
            foreach ( $block['settings'] ?? [] as $attr => $groups ) {
                foreach ( self::attrProblems( $name, (string) $attr, $groups, $module ) as $p ) {
                    $problems[] = "{$name} {$p}";
                }
            }
            foreach ( self::problems( $block['elements'] ?? [], $name ) as $p ) {
                $problems[] = $p;
            }
        }
        return $problems;
    }

    private static function attrProblems( string $name, string $attr, mixed $groups, array $module ): array {
        if ( $attr === 'css' ) {
            $allowed = array_merge( $module['customCssFields'], [ 'freeForm' ] );
            $out     = [];
            foreach ( self::envelopeProblems( 'css', $groups ) as $p ) {
                $out[] = $p;
            }
            foreach ( self::values( $groups ) as $path => $value ) {
                foreach ( is_array( $value ) ? array_keys( $value ) : [] as $key ) {
                    if ( ! in_array( (string) $key, $allowed, true ) ) {
                        $out[] = "{$path}.{$key} — not a custom CSS field of this module";
                    }
                }
            }
            return $out;
        }

        $spec = $module['attributes'][ $attr ] ?? null;
        if ( $spec === null ) {
            return [ "{$attr} — not an attribute of this module (" . implode( ', ', array_keys( $module['attributes'] ) ) . ')' ];
        }
        if ( ! is_array( $groups ) ) {
            return [ "{$attr} — must be an object of groups" ];
        }

        $out = [];
        foreach ( $groups as $group => $subs ) {
            $group = (string) $group;
            if ( ! array_key_exists( $group, $spec ) || $group === 'elementType' ) {
                $out[] = "{$attr}.{$group} — attribute '{$attr}' declares no '{$group}' group";
                continue;
            }
            if ( $group === 'innerContent' ) {
                foreach ( self::innerContentProblems( $name, $attr, $spec, $subs ) as $p ) {
                    $out[] = $p;
                }
                continue;
            }
            if ( ! is_array( $subs ) ) {
                $out[] = "{$attr}.{$group} — must be an object";
                continue;
            }
            foreach ( $subs as $sub => $value ) {
                $sub = (string) $sub;
                if ( ! in_array( $sub, $spec[ $group ], true ) ) {
                    $out[] = "{$attr}.{$group}.{$sub} — not declared (" . implode( ', ', $spec[ $group ] ) . ')';
                    continue;
                }
                foreach ( self::groupValueProblems( "{$attr}.{$group}.{$sub}", $sub, $value ) as $p ) {
                    $out[] = $p;
                }
            }
        }
        return $out;
    }

    private static function innerContentProblems( string $name, string $attr, array $spec, mixed $data ): array {
        $out = self::envelopeProblems( "{$attr}.innerContent", $data );
        if ( $out !== [] ) {
            return $out;
        }
        $sub_names = self::INNER_CONTENT_OVERRIDES[ "{$name} {$attr}" ] ?? $spec['innerContent'];
        $open      = in_array( '*', $sub_names, true );
        $type      = $spec['elementType'];

        foreach ( self::values( $data ) as $path => $value ) {
            if ( $type === 'heading' || $type === 'content' ) {
                if ( ! is_string( $value ) ) {
                    $out[] = "{$path} — a '{$type}' element value is a string";
                }
                continue;
            }
            if ( $type === 'headingLink' || $type === 'button' ) {
                if ( ! is_array( $value ) || ! array_key_exists( 'text', $value ) ) {
                    $out[] = "{$path} — a '{$type}' element value is an object with 'text' (ModuleElements.php:1012-1024)";
                    continue;
                }
            }
            if ( $sub_names === [] ) {
                if ( is_array( $value ) ) {
                    $out[] = "{$path} — this innerContent is a scalar in module.json";
                }
                continue;
            }
            if ( ! is_array( $value ) ) {
                $out[] = "{$path} — this innerContent is an object with keys " . implode( ', ', $sub_names );
                continue;
            }
            if ( ! $open ) {
                foreach ( array_keys( $value ) as $key ) {
                    if ( ! in_array( (string) $key, $sub_names, true ) ) {
                        $out[] = "{$path}.{$key} — not one of " . implode( ', ', $sub_names );
                    }
                }
            }
            if ( $type === 'headingLink' || $type === 'button' ) {
                continue;
            }
            if ( isset( $value['icon'] ) && ! ( is_array( $value['icon'] ) && isset( $value['icon']['unicode'], $value['icon']['type'] ) ) ) {
                $out[] = "{$path}.icon — an icon is {unicode, type, weight} (IconModule.php:344-350)";
            }
        }
        return $out;
    }

    private static function groupValueProblems( string $path, string $group, mixed $value ): array {
        if ( isset( self::SUB_GROUPED[ $group ] ) ) {
            if ( ! is_array( $value ) ) {
                return [ "{$path} — must be an object of sub-groups" ];
            }
            $out = [];
            foreach ( $value as $sub => $inner ) {
                $shape = self::SUB_GROUPED[ $group ][ $sub ] ?? false;
                if ( $shape === false ) {
                    $out[] = "{$path}.{$sub} — not a sub-group of '{$group}' (" . implode( ', ', array_keys( self::SUB_GROUPED[ $group ] ) ) . ')';
                    continue;
                }
                if ( $shape === 'FONT_GROUP' ) {
                    foreach ( self::groupValueProblems( "{$path}.{$sub}", 'font', $inner ) as $p ) {
                        $out[] = $p;
                    }
                    continue;
                }
                $keys = $shape === 'FONT' ? self::FONT_KEYS : ( $shape === 'TEXT_SHADOW' ? self::TEXT_SHADOW_KEYS : $shape );
                foreach ( self::responsiveProblems( "{$path}.{$sub}", $inner, $keys ) as $p ) {
                    $out[] = $p;
                }
                if ( $shape === 'FONT' ) {
                    foreach ( self::values( $inner ) as $vpath => $font ) {
                        $level = is_array( $font ) ? ( $font['headingLevel'] ?? null ) : null;
                        if ( $level !== null && ! preg_match( '/^h[1-6]$/', (string) $level ) ) {
                            $out[] = "{$vpath}.headingLevel — '{$level}' is not styled by Divi; the heading selector covers h1-h6 only (heading/module.json title.selector)";
                        }
                    }
                }
            }
            return $out;
        }

        if ( array_key_exists( $group, self::RESPONSIVE_GROUPS ) ) {
            return self::responsiveProblems( $path, $value, self::RESPONSIVE_GROUPS[ $group ] );
        }

        // An `advanced.*` field or a group this helper has no key list for: envelope only.
        return self::envelopeProblems( $path, $value );
    }

    private static function responsiveProblems( string $path, mixed $value, ?array $keys ): array {
        $out = self::envelopeProblems( $path, $value );
        if ( $out !== [] || $keys === null ) {
            return $out;
        }
        foreach ( self::values( $value ) as $vpath => $v ) {
            if ( ! is_array( $v ) ) {
                $out[] = "{$vpath} — expected an object with keys " . implode( ', ', $keys );
                continue;
            }
            foreach ( array_keys( $v ) as $key ) {
                if ( ! in_array( (string) $key, $keys, true ) ) {
                    $out[] = "{$vpath}.{$key} — not a key Divi reads here (" . implode( ', ', $keys ) . ')';
                }
            }
        }
        return $out;
    }

    /** {desktop|tablet|phone}.{value|hover|sticky} */
    private static function envelopeProblems( string $path, mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [ "{$path} — expected {breakpoint: {state: …}}" ];
        }
        $out = [];
        foreach ( $value as $bp => $states ) {
            if ( ! in_array( (string) $bp, self::BREAKPOINTS, true ) ) {
                $out[] = "{$path}.{$bp} — not a breakpoint (desktop, tablet, phone)";
                continue;
            }
            if ( ! is_array( $states ) ) {
                $out[] = "{$path}.{$bp} — expected {value: …}";
                continue;
            }
            foreach ( array_keys( $states ) as $state ) {
                if ( ! in_array( (string) $state, self::STATES, true ) ) {
                    $out[] = "{$path}.{$bp}.{$state} — not a state (value, hover, sticky)";
                }
            }
        }
        return $out;
    }

    /** @return array<string, mixed> "path.desktop.value" => value, for a valid envelope */
    private static function values( mixed $envelope, string $path = '' ): array {
        $out = [];
        foreach ( is_array( $envelope ) ? $envelope : [] as $bp => $states ) {
            foreach ( is_array( $states ) ? $states : [] as $state => $value ) {
                $out[ ltrim( "{$path}.{$bp}.{$state}", '.' ) ] = $value;
            }
        }
        return $out;
    }

    private static function schema(): array {
        if ( self::$schema === null ) {
            self::$schema = json_decode( (string) file_get_contents( __DIR__ . '/../../fixtures/divi-schema/modules.json' ), true );
        }
        return self::$schema;
    }

    private static function knownGaps(): array {
        $file = __DIR__ . '/divi-schema-known-gaps.php';
        return file_exists( $file ) ? require $file : [];
    }

    /** A gap matches when the problem starts with its key ("divi/x attr.path"). */
    private static function matchGap( string $problem, array $known ): ?string {
        foreach ( array_keys( $known ) as $prefix ) {
            if ( str_starts_with( $problem, $prefix ) ) {
                return $prefix;
            }
        }
        return null;
    }
}
```

Note: `values()` is called with a path argument in `innerContentProblems` and `responsiveProblems` — pass the path there: `self::values( $data, "{$attr}.innerContent" )` and `self::values( $value, $path )`, `self::values( $inner, "{$path}.{$sub}" )`. Fix those three calls when writing the file.

- [ ] **Step 2: Wire the helper in and create an empty known-gaps file**

Append to `tests/bootstrap.php` (after the plugin requires):

```php
require_once __DIR__ . '/support/DiviModuleSchema.php';
```

Create `tests/support/divi-schema-known-gaps.php`:

```php
<?php
// Converter output the schema test knows is wrong and a later task fixes.
// Key: "<block name> <attribute path>" prefix of the reported problem. Value: the task.
// Deleted when empty.
return [];
```

In `tests/ConverterFixtureTest.php`, add to `test_converter_matches_expected_fixture` after `$result = $engine->convert( … )`:

```php
        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], "fixture {$fixture}" );
```

In `tests/AddonSettingNamesTest.php::convert()`, before the `return`:

```php
        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], "widget {$type}" );
```

In `demo/tests/php/DocumentConversionTest.php`, find the test that converts each document (it calls `ConversionPreflight` and `conversion_problems`) and add, after the item is built:

```php
        DiviModuleSchema::assertBlocksValid( $item['blocks']['elements'] ?? [], $name );
```

Create `tests/KitPagesSchemaTest.php`:

```php
<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor's free Kit Library kits (core widgets, sections and columns, Elementor
 * 3.7.2) through the converter, with the kit's own globals installed the way
 * ConversionPreflight reads them. Real-world shapes the hand-written fixtures miss.
 */
final class KitPagesSchemaTest extends TestCase {
    private const KITS = [ 'ceramic-studio', 'painting-company' ];

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    /** @return array<string, array{string, string}> */
    public static function pages(): array {
        $sets = [];
        foreach ( self::KITS as $kit ) {
            $zip = new ZipArchive();
            if ( $zip->open( __DIR__ . "/../references/kits/{$kit}.zip" ) !== true ) {
                continue;
            }
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $entry = $zip->getNameIndex( $i );
                if ( preg_match( '#^content/page/(\d+)\.json$#', $entry, $m ) ) {
                    $sets[ "{$kit}/{$m[1]}" ] = [ $kit, $entry ];
                }
            }
            $zip->close();
        }
        return $sets;
    }

    #[DataProvider('pages')]
    public function test_kit_page_converts_to_attributes_divi_renders( string $kit, string $entry ): void {
        $zip = new ZipArchive();
        $this->assertTrue( $zip->open( __DIR__ . "/../references/kits/{$kit}.zip" ) );
        $page     = json_decode( (string) $zip->getFromName( $entry ), true );
        $settings = json_decode( (string) $zip->getFromName( 'site-settings.json' ), true );
        $zip->close();

        // The kit's globals, installed as the active kit (see demo_test_seed_site()).
        update_option( 'elementor_active_kit', 900 );
        update_post_meta( 900, '_elementor_page_settings', $settings['settings'] ?? [] );
        add_filter( 'edc_kit_globals', static fn( $kit_globals ) => ConversionPreflight::installedKitGlobals() );

        $engine = new ConverterEngine();
        $engine->setGlobalColors( ConversionPreflight::elementorGlobalColors() );
        $result = $engine->convert( $page['content'] ?? [] );

        $this->assertSame( [], $result['unsupported'], 'core widgets only' );
        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], "{$kit} {$entry}" );
    }
}
```

- [ ] **Step 3: Run the suite and record every reported gap**

Run: `vendor/bin/phpunit 2>&1 | tail -80`
Expected: failures listing problems such as `divi/pricing-table module.advanced.title — not declared`, `divi/team-member module.advanced.position`, `divi/team-member image.innerContent.desktop.value.src`, `divi/testimonial company.innerContent.desktop.value.author`, `divi/testimonial module.advanced.portrait`, `divi/countdown-timer module.advanced.countdownDate`, `divi/blurb module.advanced.text`, `divi/blurb title.innerContent.desktop.value — a 'headingLink' element value is an object`, `divi/blurb imageIcon.innerContent.desktop.value.icon — an icon is {unicode, type, weight}`, `divi/gallery galleryGrid.innerContent`, `divi/heading title.decoration.font.font.desktop.value.headingLevel — 'span'`, `divi/icon icon.innerContent.desktop.value — this innerContent is an object`. Copy each distinct `"<block> <path>"` prefix into `tests/support/divi-schema-known-gaps.php` with the task that fixes it:

```php
return [
    'divi/pricing-table module.advanced'                 => 'Task 12',
    'divi/team-member module.advanced'                   => 'Task 13',
    'divi/team-member image.innerContent'                => 'Task 13',
    'divi/testimonial company.innerContent'              => 'Task 13',
    'divi/testimonial module.advanced.portrait'          => 'Task 13',
    'divi/countdown-timer module.advanced.countdownDate' => 'Task 14',
    'divi/blurb module.advanced.text'                    => 'Task 11',
    'divi/blurb title.innerContent'                      => 'Task 11',
    'divi/blurb imageIcon.innerContent'                  => 'Task 11',
    'divi/gallery galleryGrid.innerContent'              => 'Task 8',
    'divi/heading title.decoration.font.font.desktop.value.headingLevel' => 'Task 5',
    'divi/icon icon.innerContent'                        => 'Task 11',
];
```

Add every other prefix the run reports, each with the task that owns that handler (a problem no task owns is a new finding: add it to `docs/known-issues.md` and to the task that touches that handler). Problems that are genuine converter mistakes with no rendering symptom yet (for example an `advanced` field name Divi does not declare) are fixed immediately in this task if the fix is a one-line rename, otherwise listed.

Run: `vendor/bin/phpunit`
Expected: OK (696 + the new tests).

- [ ] **Step 4: Run the demo suite**

Run: `vendor/bin/phpunit -c demo/phpunit.xml`
Expected: OK, 22 tests. If it reports gaps not in the file, add them (same rule).

- [ ] **Step 5: Commit**

```bash
git add tests/support tests/bootstrap.php tests/ConverterFixtureTest.php tests/AddonSettingNamesTest.php tests/KitPagesSchemaTest.php demo/tests/php/DocumentConversionTest.php
git commit -m "test(schema): validate every emitted block against Divi 5.7.4's module definitions

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: Render probe page and the demo's render check

**Files:**
- Create: `demo/content/probes/core-widgets.php`, `demo/lib/documents.php`, `demo/lib/seed-probes.php`, `demo/tests/render.spec.ts`, `demo/tests/php/ProbeDocumentTest.php`
- Modify: `demo/lib/elementor.php` (add `section()`, `column()`), `demo/lib/seed.php` (use `insert_document()`), `demo/lib/commit-conversions.php` (`--probes`), `demo/lib/checks.sh` (`check_render`), `demo/verify.sh` (`ALL_CHECKS`), `demo/playwright.config.ts`, `demo/README.md`

**Interfaces:**
- Produces: `Ferncourt\Demo\insert_document( array $doc, string $name ): int` in `demo/lib/documents.php` (WordPress only).
- Produces: `Ferncourt\Demo\section( array $columns, array $settings = [] ): array` and `column( array $children, int $size, array $settings = [] ): array` in `demo/lib/elementor.php` (legacy Elementor section/column elements).
- Produces: `demo/output/converted.json` entries gain `"kind": "page" | "probe"`.
- Produces: `demo/tests/render.spec.ts` with `draft(slug)` and `settle(page)` helpers; later tasks add `test(...)` blocks to it.

- [ ] **Step 1: DSL helpers for legacy sections and columns**

Append to `demo/lib/elementor.php`, after `col()`:

```php
/** A legacy Elementor section (the pre-container layout the Kit Library kits use). */
function section( array $columns, array $settings = [] ): array {
    return [ 'elType' => 'section', 'settings' => $settings, 'elements' => array_values( $columns ) ];
}

/** A legacy Elementor column; $size is the percentage width. */
function column( array $children, int $size, array $settings = [] ): array {
    return [ 'elType' => 'column', 'settings' => array_replace( [ '_column_size' => $size ], $settings ), 'elements' => array_values( $children ) ];
}
```

- [ ] **Step 2: The probe document**

Create `demo/content/probes/core-widgets.php`:

```php
<?php
/**
 * Render probe: the core-widget cases the Ferncourt pages avoid, laid out the way
 * Elementor's Kit Library kits are (legacy sections and columns). Seeded only by
 * demo/verify.sh render; removed by the reset that follows. demo/tests/render.spec.ts
 * asserts what a viewer sees on the converted draft.
 */

use Ferncourt\Demo\Context;
use function Ferncourt\Demo\{band, button, column, heading, icon, item, link, section, slider, widget};

return static function ( Context $ctx ): array {
    $social = static fn ( string $id, string $class, string $url ): array => item( $id, [
        'social_icon' => icon( $class, 'fa-brands' ),
        'link'        => link( $url, true ),
    ] );

    return [
        'title'    => 'Render probe: core widgets',
        'slug'     => 'render-probe-core-widgets',
        'elements' => [
            // Ceramic Studio's hero: a 90vh section, the left column carries a cover image
            // and holds only a spacer, the right column the text.
            section( [
                column( [ widget( 'spacer', [ 'space' => slider( 50 ) ] ) ], 50, [
                    'background_background' => 'classic',
                    'background_image'      => $ctx->image( 'hero-lounge.jpg' ),
                    'background_position'   => 'center center',
                    'background_repeat'     => 'no-repeat',
                    'background_size'       => 'cover',
                ] ),
                column( [
                    heading( 'Probe hero heading', 'h1' ),
                    widget( 'heading', [
                        'title'                     => 'eramic',
                        'header_size'               => 'span',
                        'typography_typography'     => 'custom',
                        'typography_font_size'      => slider( 12, 'vw' ),
                        'typography_font_weight'    => '700',
                        'typography_letter_spacing' => slider( -2 ),
                        'title_color'               => '#C8643B',
                    ] ),
                    button( 'Probe button', $ctx->url( '/contact/' ) ),
                    widget( 'counter', [ 'starting_number' => 0, 'ending_number' => 58, 'suffix' => '%', 'title' => 'Percent probe' ] ),
                    widget( 'counter', [ 'starting_number' => 0, 'ending_number' => 240, 'suffix' => '+', 'title' => 'Plus probe' ] ),
                ], 50 ),
            ], [
                'layout'           => 'full_width',
                'height'           => 'min-height',
                'custom_height'    => slider( 90, 'vh' ),
                'column_position'  => 'stretch',
                'content_position' => 'middle',
            ] ),

            band( [
                widget( 'image-carousel', [
                    'carousel'       => [
                        $ctx->image( 'desks-open-plan.jpg' ),
                        $ctx->image( 'desks-window.jpg' ),
                        $ctx->image( 'desks-plants.jpg' ),
                        $ctx->image( 'office-private-1.jpg' ),
                    ],
                    'thumbnail_size' => 'full',
                    'slides_to_show' => '4',
                ] ),
                widget( 'image-gallery', [
                    'wp_gallery'      => [ $ctx->image( 'desks-open-plan.jpg' ), $ctx->image( 'desks-window.jpg' ), $ctx->image( 'desks-plants.jpg' ) ],
                    'gallery_columns' => 3,
                ] ),
                widget( 'social-icons', [
                    'social_icon_list' => [
                        $social( 'ps1', 'fab fa-instagram', 'https://www.instagram.com/' ),
                        $social( 'ps2', 'fab fa-linkedin', 'https://www.linkedin.com/' ),
                    ],
                ] ),
                widget( 'google_maps', [ 'address' => 'Downtown Portland, Oregon', 'zoom' => slider( 14 ), 'height' => slider( 360 ) ] ),
            ] ),
        ],
        'survive'  => [ 'Probe hero heading', 'eramic', 'Probe button', 'Percent probe', 'Plus probe', 'Downtown Portland, Oregon' ],
    ];
};
```

`$ctx->image()` returns `{url, id, alt, source, size}`; the carousel and gallery controls store `{id, url}` items, which that shape satisfies.

- [ ] **Step 3: Offline test for the probe**

Create `demo/tests/php/ProbeDocumentTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use function Ferncourt\Demo\{conversion_problems, load_document};

/** The render probe converts cleanly and every block is one Divi renders. */
final class ProbeDocumentTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
        demo_test_seed_site();
    }

    public function test_core_widgets_probe_converts_cleanly(): void {
        $doc  = load_document( dirname( __DIR__, 2 ) . '/content/probes/core-widgets.php', demo_test_context() );
        $item = ( new ConversionPreflight() )->runUnlimited( new DocumentSource( [ $doc ] ) )->items()[0];

        $this->assertSame( [], conversion_problems( $item, $doc['survive'], $doc['survive_exact'] ) );
        DiviModuleSchema::assertBlocksValid( $item['blocks']['elements'] ?? [], 'probes/core-widgets' );
    }
}
```

Run: `vendor/bin/phpunit -c demo/phpunit.xml`
Expected: the new test passes once the probe file exists (known gaps cover the span heading; a warning about the `+` suffix does not exist yet — Task 7 adds a not-carried entry, which `conversion_problems()` ignores).

- [ ] **Step 4: Document insertion shared by seed and probes**

Create `demo/lib/documents.php`:

```php
<?php
/**
 * Inserts a loaded document the way Elementor stores one saved in its editor.
 * Shared by seed.php (pages and HFE templates) and seed-probes.php.
 */

namespace Ferncourt\Demo;

/** @return int The new post ID. */
function insert_document( array $doc, string $name ): int {
    $is_template = $doc['template'] !== '';

    $post_id = wp_insert_post( [
        'post_type'   => $is_template ? 'elementor-hf' : 'page',
        'post_status' => 'publish',
        'post_title'  => $doc['title'],
        'post_name'   => $doc['slug'],
    ], true );
    if ( is_wp_error( $post_id ) ) {
        \WP_CLI::error( "{$name}: " . $post_id->get_error_message() );
    }

    update_post_meta( $post_id, DOCUMENT_META, $name );
    update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $post_id, '_elementor_template_type', $is_template ? 'wp-post' : 'wp-page' );
    update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
    // update_post_meta() unslashes, so slash the JSON first, as Elementor does.
    update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $doc['elements'] ) ) );

    if ( $is_template ) {
        // Header Footer Elementor display rules: the entire website, every user.
        update_post_meta( $post_id, 'ehf_template_type', 'type_' . $doc['template'] );
        update_post_meta( $post_id, 'ehf_target_include_locations', [ 'rule' => [ 'basic-global' ], 'specific' => [] ] );
        update_post_meta( $post_id, 'ehf_target_exclude_locations', [] );
        update_post_meta( $post_id, 'ehf_target_user_roles', [ 'all' ] );
        return (int) $post_id;
    }

    update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
    update_post_meta( $post_id, '_elementor_page_settings', [ 'hide_title' => 'yes' ] );

    return (int) $post_id;
}
```

In `demo/lib/seed.php`, add `require_once __DIR__ . '/documents.php';` next to the other requires, and replace the body of the `foreach ( document_names() as $name )` loop (from `$is_template = …` through `update_post_meta( $post_id, '_elementor_page_settings', … )`) with:

```php
    $doc     = load_document( CONTENT_DIR . "/{$name}.php", $ctx );
    $post_id = insert_document( $doc, $name );
    if ( $doc['template'] !== '' ) {
        continue;
    }
    $pages[ $doc['slug'] ] = $post_id;
```

keeping the `front_page` block after it.

Create `demo/lib/seed-probes.php`:

```php
<?php
/**
 * Seeds every document under demo/content/probes as a published page, for the
 * render check. Skips a probe that already exists. Run with Hello Elementor or Divi.
 *
 * Run: demo/wp --user=admin eval-file /demo/lib/seed-probes.php
 */

namespace Ferncourt\Demo;

use WP_CLI;

require_once __DIR__ . '/elementor.php';
require_once __DIR__ . '/site-context.php';
require_once __DIR__ . '/documents.php';

$ctx = site_context();
foreach ( glob( CONTENT_DIR . '/probes/*.php' ) as $file ) {
    $name = 'probes/' . basename( $file, '.php' );
    if ( document_post_id( $name ) !== 0 ) {
        WP_CLI::log( "{$name}: already seeded" );
        continue;
    }
    $post_id = insert_document( load_document( $file, $ctx ), $name );
    WP_CLI::log( "{$name}: page {$post_id}" );
}
WP_CLI::success( 'Probes seeded.' );
```

- [ ] **Step 5: Commit probes alongside the pages**

In `demo/lib/commit-conversions.php`, replace the block that builds `$slugs` / `$post_ids` with:

```php
$with_probes = in_array( '--probes', $args ?? [], true );
$documents   = [];
foreach ( document_names() as $name ) {
    if ( str_starts_with( $name, 'pages/' ) ) {
        $documents[ $name ] = 'page';
    }
}
if ( $with_probes ) {
    foreach ( glob( CONTENT_DIR . '/probes/*.php' ) as $file ) {
        $documents[ 'probes/' . basename( $file, '.php' ) ] = 'probe';
    }
}

$slugs = $kinds = $post_ids = [];
foreach ( $documents as $name => $kind ) {
    $post_id = document_post_id( $name );
    if ( $post_id === 0 ) {
        WP_CLI::error( "{$name}: not seeded" . ( $kind === 'probe' ? ' (run demo/lib/seed-probes.php)' : '' ) );
    }
    $slugs[]    = substr( $name, strpos( $name, '/' ) + 1 );
    $kinds[]    = $kind;
    $post_ids[] = $post_id;
}
```

and in the results loop record the kind: `$converted[] = [ 'slug' => $slugs[ $index ], 'kind' => $kinds[ $index ], 'source_id' => $post_ids[ $index ], 'draft_id' => $result['post_id'] ];`. `$args` is WP-CLI's positional-argument array for `eval-file`; declare `$args = $args ?? [];` at the top (WP-CLI passes extra arguments to eval-file scripts as `$args`).

- [ ] **Step 6: The verify stage and the Playwright config**

In `demo/lib/checks.sh`, after `check_converted`, add:

```bash
# Check 7: what a viewer sees on the converted drafts. Seeds the probe page, converts it
# with the seven pages under Divi, and asserts sizes, colours and text in the DOM.
check_render() {
    wp --user="$ADMIN_USER" eval-file /demo/lib/seed-probes.php
    wp theme activate Divi
    wp --user="$ADMIN_USER" eval-file /demo/lib/commit-conversions.php --probes
    (cd "$DEMO_DIR/.." && PW_STAGE=render npx playwright test -c demo/playwright.config.ts render)
    echo "ok  render assertions"
}
```

In `demo/verify.sh`, change `ALL_CHECKS=(versions pages conversions converted reset)` to `ALL_CHECKS=(versions pages conversions converted render reset)`.

In `demo/playwright.config.ts`, replace the `testIgnore` line with:

```ts
  // Screenshots run only under PW_STAGE=originals|converted; render assertions only under PW_STAGE=render.
  testIgnore: [
    ...(process.env.PW_STAGE === 'originals' || process.env.PW_STAGE === 'converted' ? [] : ['**/screenshots.spec.ts']),
    ...(process.env.PW_STAGE === 'render' ? [] : ['**/render.spec.ts']),
  ],
```

- [ ] **Step 7: The spec skeleton**

Create `demo/tests/render.spec.ts`:

```ts
import { expect, test, type Locator, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { login } from './support';

// Build check 7 (demo/verify.sh render). Each test names the Elementor widget it stands
// for and asserts what a viewer of the Divi draft sees, not what the converter wrote.
const OUTPUT = join(process.cwd(), 'demo', 'output');
const converted: { slug: string; kind: string; draft_id: number }[] = JSON.parse(
  readFileSync(join(OUTPUT, 'converted.json'), 'utf8'),
);

function draft(slug: string): string {
  const entry = converted.find((c) => c.slug === slug);
  if (!entry) throw new Error(`${slug} is not in converted.json; run demo/verify.sh render`);
  return `/?page_id=${entry.draft_id}&preview=true`;
}

/** Scroll through the page so lazy images load and counters, countdowns and animations run. */
async function settle(page: Page): Promise<void> {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 150));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(2500);
}

async function css(locator: Locator, property: string): Promise<string> {
  return locator.evaluate((el, prop) => getComputedStyle(el).getPropertyValue(prop), property);
}

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
  await login(page);
});

test.describe('probe: core widgets', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('core-widgets'));
    await settle(page);
  });

  test('heading (h1): renders as a styled heading', async ({ page }) => {
    await expect(page.locator('.et_pb_heading h1', { hasText: 'Probe hero heading' })).toBeVisible();
  });
});
```

Later tasks append `test(...)` blocks inside this describe and add describes for the Ferncourt pages.

- [ ] **Step 8: Run the render check end to end**

Run: `demo/verify.sh render && demo/verify.sh reset`
Expected: the probe is seeded, eight drafts committed, `1 passed`, then the reset check passes (it counts `_edc_import_source` posts after reset: the snapshot predates the probe, so the probe page is gone too).

- [ ] **Step 9: README and commit**

In `demo/README.md` under "Checks", change the sentence to `demo/verify.sh [versions|pages|conversions|converted|render|reset]` — no argument runs all six, and add a paragraph:

```
`render` seeds the probe page in `demo/content/probes/`, converts it and the seven pages
under Divi, and runs `demo/tests/render.spec.ts`: sizes, colours and text a viewer sees on
each draft, one test per Elementor widget. Add a probe file to cover a widget the site does
not use; add a test for every rendering bug you fix.
```

```bash
git add demo/content/probes demo/lib/documents.php demo/lib/seed-probes.php demo/lib/seed.php demo/lib/commit-conversions.php demo/lib/checks.sh demo/verify.sh demo/lib/elementor.php demo/playwright.config.ts demo/tests/render.spec.ts demo/tests/php/ProbeDocumentTest.php demo/README.md
git commit -m "feat(demo): render check with a core-widget probe page

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: Column background stays on the column; columns stretch to the row

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-column-converter.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/stylemapper/class-style-mapper.php` (`mapContentPosition`)
- Modify: `fixtures/divi/column-overlay.json` (regenerated), `demo/tests/render.spec.ts`
- Test: `tests/ColumnBackgroundTest.php`

**Interfaces:**
- Consumes: `ConverterEngine::convert( array $elements ): array` returning `['divi' => ['elements' => …], 'report' => …]`.
- Produces: the section's `column_position` maps to `module.decoration.layout.desktop.value.alignItems` on the row (`stretch` stays `stretch`; `top|middle|bottom` → `flex-start|center|flex-end`).

- [ ] **Step 1: Failing test**

Create `tests/ColumnBackgroundTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Ceramic Studio's hero (docs/known-issues.md): a 90vh section whose left column
 * carries a cover image and holds only a spacer rendered as a 12 px strip because
 * the background was moved onto an empty divi/group. Divi 5 rows are flex with
 * align-items: stretch, so the column itself fills the row; the background belongs
 * on it (column/module.json declares module.decoration.background).
 */
final class ColumnBackgroundTest extends TestCase {
    private function hero(): array {
        return [ [
            'id' => 'sec', 'elType' => 'section',
            'settings' => [ 'layout' => 'full_width', 'height' => 'min-height', 'custom_height' => [ 'unit' => 'vh', 'size' => '90' ], 'column_position' => 'stretch', 'content_position' => 'middle' ],
            'elements' => [
                [ 'id' => 'left', 'elType' => 'column',
                  'settings' => [ '_column_size' => 50, 'background_background' => 'classic', 'background_image' => [ 'url' => 'https://x.test/hero.jpg', 'id' => 20 ], 'background_position' => 'center center', 'background_repeat' => 'no-repeat', 'background_size' => 'cover' ],
                  'elements' => [ [ 'id' => 'sp', 'elType' => 'widget', 'widgetType' => 'spacer', 'settings' => [ 'space' => [ 'unit' => 'px', 'size' => 50 ] ], 'elements' => [] ] ] ],
                [ 'id' => 'right', 'elType' => 'column', 'settings' => [ '_column_size' => 50 ],
                  'elements' => [ [ 'id' => 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'Hi' ], 'elements' => [] ] ] ],
            ],
        ] ];
    }

    public function test_column_keeps_its_cover_background_and_no_group_is_added(): void {
        $section = ( new ConverterEngine() )->convert( $this->hero() )['divi']['elements'][0];
        $row     = $section['elements'][0];
        $left    = $row['elements'][0];

        $this->assertSame( 'divi/column', $left['name'] );
        $this->assertSame( 'https://x.test/hero.jpg', $left['settings']['module']['decoration']['background']['desktop']['value']['image']['url'] );
        $this->assertSame( 'cover', $left['settings']['module']['decoration']['background']['desktop']['value']['image']['size'] );
        $this->assertSame( [ 'divi/divider' ], array_column( $left['elements'], 'name' ), 'the spacer is the column\'s only child; no divi/group' );
        DiviModuleSchema::assertBlocksValid( [ $section ], 'hero' );
    }

    public function test_row_gets_min_height_and_stretches_its_columns(): void {
        $row = ( new ConverterEngine() )->convert( $this->hero() )['divi']['elements'][0]['elements'][0];

        $this->assertSame( '90vh', $row['settings']['module']['decoration']['sizing']['desktop']['value']['minHeight'] );
        $this->assertSame( 'stretch', $row['settings']['module']['decoration']['layout']['desktop']['value']['alignItems'] );
    }

    public function test_column_overlay_colour_stays_on_the_column(): void {
        $hero = $this->hero();
        $hero[0]['elements'][0]['settings']['background_overlay_background'] = 'classic';
        $hero[0]['elements'][0]['settings']['background_overlay_color']      = '#000000';
        $hero[0]['elements'][0]['settings']['background_overlay_opacity']    = [ 'size' => 0.5, 'unit' => 'px' ];

        $left = ( new ConverterEngine() )->convert( $hero )['divi']['elements'][0]['elements'][0]['elements'][0];

        $this->assertArrayHasKey( 'gradient', $left['settings']['module']['decoration']['background']['desktop']['value'], 'overlay is a gradient layer over the image' );
        $this->assertSame( [ 'divi/divider' ], array_column( $left['elements'], 'name' ) );
    }
}
```

Run: `vendor/bin/phpunit tests/ColumnBackgroundTest.php`
Expected: FAIL — the column's child is `divi/group`, and `alignItems` is `center` (from `content_position`), not `stretch`.

- [ ] **Step 2: Remove the group wrapper**

In `class-column-converter.php`, delete the `if ( $this->hasBackgroundStyling( $settings ) && ! empty( $children ) ) { … }` block and the `hasBackgroundStyling()`, `wrapInGroup()` and `stripDecorationFromAttrs()` methods, and replace the comment above the deleted block with:

```php
        // Background, overlay and padding stay on the column. Divi 5 rows are
        // display:flex with the default align-items: stretch (.et_flex_row in
        // Divi's module CSS), so the column already fills the row's min-height and
        // a cover image has something to cover. Wrapping the children in a
        // divi/group carrying the background (the old "widget-wrap" mirror) gave
        // the image a box only as tall as the group's content — 12 px when the
        // column held a spacer. column/module.json declares
        // module.decoration.background and .spacing on the column itself.
```

- [ ] **Step 3: Map `column_position` to the row**

In `StyleMapper::mapContentPosition()`, the section branch currently writes `content_position` to `alignItems`. Elementor's *Vertical Align* (`content_position`) centres the widgets inside each column, while *Column Position* (`column_position`) is what sets `align-items` on the row (`elementor/includes/elements/section.php`: `column_position` → `.elementor-column` `align-items`, `content_position` → `.elementor-widget-wrap` `align-content`/`align-items`). Replace the section branch with:

```php
        // section / container / row — desktop-only (responsive variants rarely set).
        // column_position is Elementor's "Column Position" (align-items on the
        // row: stretch|top|middle|bottom, default stretch); content_position is
        // "Vertical Align" for the widgets inside each column. When only
        // content_position is set the old behaviour is kept: the row centres
        // its columns, which is the closest single-property approximation.
        $handled[] = 'column_position';
        $handled[] = 'content_position';
        $column_pos  = $settings['column_position'] ?? '';
        $content_pos = $settings['content_position'] ?? '';
        $pos         = is_string( $column_pos ) && $column_pos !== '' ? $column_pos : $content_pos;
        if ( is_string( $pos ) && $pos !== '' ) {
            self::transformPath(
                $attrs,
                'module.decoration.layout.desktop.value.alignItems',
                $this->normalizeFlexAlignment( $pos )
            );
        }
```

Run: `vendor/bin/phpunit tests/ColumnBackgroundTest.php`
Expected: PASS.

- [ ] **Step 4: Regenerate the overlay fixture and run everything**

Run: `vendor/bin/phpunit tests/ConverterFixtureTest.php`
Expected: `column-overlay` fails (the expected JSON still has the group). Regenerate it:

```bash
php -r 'require "tests/bootstrap.php"; $e = new ElementorDivi5Converter\Converter\ConverterEngine(); $r = $e->convert( json_decode( file_get_contents( "fixtures/elementor/column-overlay.json" ), true ) ); file_put_contents( "fixtures/divi/column-overlay.json", json_encode( [ "divi" => $r["divi"], "unsupported" => $r["unsupported"] ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );'
git diff --stat fixtures/divi/column-overlay.json
```

Read the diff: the only changes are the group's removal and its background/spacing moving onto the column. Then `vendor/bin/phpunit` → OK, and `vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 5: Render assertion**

Append inside the probe describe in `demo/tests/render.spec.ts`:

```ts
  test('column with a cover background image: fills the row, not a strip', async ({ page }) => {
    const column = page.locator('.et_pb_column').filter({ has: page.locator('.et_pb_divider') }).first();
    const box = await column.boundingBox();
    expect(box?.height ?? 0).toBeGreaterThan(400);
    expect(await css(column, 'background-image')).toContain('hero-lounge');
    expect(await css(column, 'background-size')).toBe('cover');
  });
```

Run: `demo/verify.sh render && demo/verify.sh reset`
Expected: both probe tests pass. Then `demo/reset.sh` is not needed (the reset check already restored the snapshot).

- [ ] **Step 6: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-column-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/stylemapper/class-style-mapper.php fixtures/divi/column-overlay.json tests/ColumnBackgroundTest.php demo/tests/render.spec.ts
git commit -m "fix(converter): keep a column's background on the column so it fills the row

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5: Headings with level span, p or div become styled text

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/class-text-heading.php`
- Modify: `handlers/class-heading-converter.php`, `handlers/class-elementskit-heading-converter.php`, `tests/support/divi-schema-known-gaps.php` (remove the heading entry), `demo/tests/render.spec.ts`
- Test: `tests/HeadingLevelTest.php`

**Interfaces:**
- Produces: `ElementorDivi5Converter\Converter\TextHeading::isHeadingTag( string $tag ): bool` and `TextHeading::block( string $id, string $tag, string $text, array $attrs ): array` — `$attrs` is the heading module's settings (with `title.innerContent` and `title.decoration`); returns a `divi/text` block whose `content.innerContent` wraps `$text` in `<$tag>` and whose `content.decoration.bodyFont.body` carries what `title.decoration.font` held.

- [ ] **Step 1: Failing test**

Create `tests/HeadingLevelTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor's header_size accepts span, p and div beside h1-h6. divi/heading
 * renders the level as the tag but styles only h1-h6 (heading/module.json title
 * selector), so Ceramic Studio's 12vw display words rendered as default text.
 * Approved mapping: a divi/text keeping the tag, typography on the body font.
 */
final class HeadingLevelTest extends TestCase {
    private function convert( array $settings ): array {
        return ( new ConverterEngine() )->convert( [ [
            'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => $settings, 'elements' => [],
        ] ] )['divi']['elements'][0];
    }

    public function test_span_heading_becomes_text_with_the_typography_on_the_body_font(): void {
        $block = $this->convert( [
            'title' => 'eramic', 'header_size' => 'span', 'align' => 'left',
            'typography_typography' => 'custom', 'typography_font_size' => [ 'unit' => 'vw', 'size' => 12 ],
            'typography_font_weight' => '700', 'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -2 ],
            'title_color' => '#C8643B', '_margin' => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '-40', 'left' => '0', 'isLinked' => '' ],
        ] );

        $this->assertSame( 'divi/text', $block['name'] );
        $this->assertSame( '<span>eramic</span>', $block['settings']['content']['innerContent']['desktop']['value'] );
        $font = $block['settings']['content']['decoration']['bodyFont']['body']['font']['desktop']['value'];
        $this->assertSame( '12vw', $font['size'] );
        $this->assertSame( '700', $font['weight'] );
        $this->assertSame( '-2px', $font['letterSpacing'] );
        $this->assertSame( '#C8643B', $font['color'] );
        $this->assertSame( 'left', $font['textAlign'] );
        $this->assertArrayNotHasKey( 'title', $block['settings'] );
        $this->assertSame( '-40px', $block['settings']['module']['decoration']['spacing']['desktop']['value']['margin']['bottom'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'span heading' );
    }

    public function test_p_and_div_keep_their_tag(): void {
        $this->assertSame( '<p>Hi</p>', $this->convert( [ 'title' => 'Hi', 'header_size' => 'p' ] )['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( '<div>Hi</div>', $this->convert( [ 'title' => 'Hi', 'header_size' => 'div' ] )['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_real_heading_levels_are_unchanged(): void {
        $block = $this->convert( [ 'title' => 'Hi', 'header_size' => 'h3' ] );
        $this->assertSame( 'divi/heading', $block['name'] );
        $this->assertSame( 'h3', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
    }

    public function test_report_counts_a_span_heading_as_text(): void {
        $report = ( new ConverterEngine() )->convert( [ [
            'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'x', 'header_size' => 'span' ], 'elements' => [],
        ] ] )['report'];
        $this->assertSame( 1, $report['converted']['text'] );
        $this->assertArrayNotHasKey( 'heading', $report['converted'] );
    }
}
```

Run: `vendor/bin/phpunit tests/HeadingLevelTest.php`
Expected: FAIL — block name is `divi/heading`.

- [ ] **Step 2: The helper**

Create `includes/converter/class-text-heading.php`:

```php
<?php

namespace ElementorDivi5Converter\Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor headings whose HTML tag is span, p or div.
 *
 * divi/heading renders headingLevel as the tag (server ModuleElements.php:1009)
 * but styles only h1-h6 (heading/module.json, title.selector), so a span
 * heading lost its size, weight and colour. Divi's text module styles
 * whatever it wraps through content.decoration.bodyFont.body (text/module.json),
 * so the heading becomes a text module that keeps the original tag: the page's
 * heading outline is unchanged and the typography renders.
 */
final class TextHeading {
    public static function isHeadingTag( string $tag ): bool {
        return (bool) preg_match( '/^h[1-6]$/i', $tag );
    }

    /**
     * @param array $attrs A divi/heading settings array: `title.decoration.font`
     *                     (and textShadow) plus any `module.*` decoration.
     */
    public static function block( string $id, string $tag, string $text, array $attrs ): array {
        $tag = in_array( strtolower( $tag ), [ 'span', 'p', 'div' ], true ) ? strtolower( $tag ) : 'span';

        $font = $attrs['title']['decoration']['font'] ?? [];
        unset( $attrs['title'] );

        $body = [];
        foreach ( [ 'font', 'textShadow' ] as $group ) {
            if ( ! empty( $font[ $group ] ) ) {
                $body[ $group ] = $font[ $group ];
            }
        }
        // headingLevel is a heading-only key; the body font has no use for it.
        foreach ( $body['font'] ?? [] as $bp => $states ) {
            foreach ( $states as $state => $value ) {
                unset( $body['font'][ $bp ][ $state ]['headingLevel'] );
            }
        }

        $attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => "<{$tag}>{$text}</{$tag}>" ] ] ];
        if ( ! empty( $body ) ) {
            $attrs['content']['decoration'] = [ 'bodyFont' => [ 'body' => $body ] ];
        }

        return [ 'id' => $id, 'name' => 'divi/text', 'settings' => $attrs, 'elements' => [] ];
    }
}
```

- [ ] **Step 3: Use it in both heading converters**

In `class-heading-converter.php`, add `use ElementorDivi5Converter\Converter\TextHeading;` and replace the block from `$this->engine->logConverted( 'heading' );` to the end of `convert()` with:

```php
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'title', 'tag', 'header_size', 'title_tag', 'size', 'link' ],
            $style['handled_keys']
        ) );

        if ( ! TextHeading::isHeadingTag( (string) $tag ) ) {
            $this->engine->logConverted( 'text' );
            return TextHeading::block( $id, (string) $tag, esc_html( $text ), $attrs );
        }

        $this->engine->logConverted( 'heading' );

        return [
            'id'       => $id,
            'name'     => 'divi/heading',
            'settings' => $attrs,
            'elements' => [],
        ];
```

`esc_html` is stubbed in tests and real in WordPress; the heading path keeps the decoded text as before.

In `class-elementskit-heading-converter.php`, add the same `use`, and just before `$heading_block = [` insert:

```php
        if ( ! TextHeading::isHeadingTag( $tag ) ) {
            $this->engine->logConverted( 'text' );
            $heading_block = TextHeading::block( $id, $tag, esc_html( $title_text ), $attrs );
        } else {
            $this->engine->logConverted( 'heading' );
            $heading_block = [ 'id' => $id, 'name' => 'divi/heading', 'settings' => $attrs, 'elements' => [] ];
        }
```

and delete the original `$this->engine->logConverted( 'heading' );` line and the original `$heading_block = [ … ];` literal.

- [ ] **Step 4: Tests, known gaps, render**

Remove the `divi/heading title.decoration.font.font.desktop.value.headingLevel` line from `tests/support/divi-schema-known-gaps.php`.

Run: `vendor/bin/phpunit && vendor/bin/phpunit -c demo/phpunit.xml`
Expected: OK. (`KitPagesSchemaTest` now passes the Ceramic pages' span headings.)

Append to the probe describe in `render.spec.ts`:

```ts
  test('heading (header_size span): keeps its 12vw typography and colour', async ({ page }) => {
    const word = page.locator('.et_pb_text span', { hasText: 'eramic' }).first();
    await expect(word).toBeVisible();
    expect(parseFloat(await css(word, 'font-size'))).toBeGreaterThan(100); // 12vw at 1440px = 172.8px
    expect(await css(word, 'color')).toBe('rgb(200, 100, 59)');
    await expect(page.locator('.et_pb_heading', { hasText: 'eramic' })).toHaveCount(0);
  });
```

Run: `demo/verify.sh render && demo/verify.sh reset` → passes. If the computed size is inherited from Divi's `p`/body rule instead (font-size below 100), the text module's body font is not reaching the span: check the generated CSS selector in the page source (`.et_pb_text_N` rule) and adjust `TextHeading::block()` to also set the same font on `bodyFont.link` … no — first read Divi's text module styles (`server/Packages/ModuleLibrary/Text/TextModule.php`, `module_styles`) to see which selector `bodyFont.body` targets, and fix the path accordingly.

- [ ] **Step 5: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes/converter/class-text-heading.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-heading-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-elementskit-heading-converter.php tests/HeadingLevelTest.php tests/support/divi-schema-known-gaps.php demo/tests/render.spec.ts
git commit -m "fix(converter): headings with level span, p or div become styled text

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6: Buttons get Elementor's default look and the kit's button theme style

**Files:**
- Modify: `includes/stylemapper/class-style-mapper.php`, `includes/stylemapper/class-globals-resolver.php`, `includes/conversion/class-conversion-preflight.php`, `includes/helpers/class-plugin.php`, `handlers/class-button-converter.php`, Pro `includes/kit/class-kit-globals-parser.php`, `demo/tests/render.spec.ts`
- Test: `tests/ButtonDefaultsTest.php`, `tests/StyleMapperTest.php` (adjust if a whole-array assertion breaks)

**Interfaces:**
- Produces: `GlobalsResolver::resolveButtons(): array` — the kit's `buttons` entry (`background_color`, `text_color`, `typography` (a preset like resolveTypography's), `border_radius` (`{topLeft,…}`), `padding` (`{top,right,bottom,left}`), `border` (`{style,width,color}`)), empty array when no kit provides one.
- Produces: `ConversionPreflight::elementorKitButtons(): array` (same shape, from the active kit) and `installedKitGlobals()` gains `'buttons'`.
- Produces: `StyleMapper::map( string $widget_type, array $settings, array $options = [] )`; `[ 'elementor_defaults' => true ]` applies the button cascade. `ButtonConverter` passes it.

- [ ] **Step 1: Failing tests**

Create `tests/ButtonDefaultsTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionPreflight;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\StyleMapper\StyleMapper;

/**
 * Elementor's button widget defaults to the global accent background
 * (includes/widgets/traits/button-trait.php: Group_Control_Background default
 * Global_Colors::COLOR_ACCENT), white 15px text with 12px/24px padding and a 3px
 * radius (assets/css/frontend.css .elementor-button), and the kit's Theme Style
 * → Buttons (core/kits/documents/tabs/theme-style-buttons.php) can override each.
 * Divi's default button is a transparent outline in the theme accent blue, so a
 * converted button that set nothing explicitly looked nothing like the original.
 */
final class ButtonDefaultsTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function kit( array $extra = [] ): void {
        add_filter( 'edc_kit_globals', static fn() => array_merge( [
            'colors'     => [ 'accent' => '#C8643B' ],
            'typography' => [ 'accent' => [ 'family' => 'Inter', 'weight' => '600' ] ],
        ], $extra ) );
    }

    private function button( array $settings ): array {
        return ( new StyleMapper() )->map( 'button', $settings, [ 'elementor_defaults' => true ] )['divi_attrs']['button']['decoration'];
    }

    public function test_a_bare_button_gets_elementors_defaults_with_the_kit_accent(): void {
        $this->kit();
        $d = $this->button( [] );

        $this->assertSame( '#C8643B', $d['background']['desktop']['value']['color'] );
        $this->assertSame( '#ffffff', $d['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '15px', $d['font']['font']['desktop']['value']['size'] );
        $this->assertSame( 'Inter', $d['font']['font']['desktop']['value']['family'] );
        $this->assertSame( '600', $d['font']['font']['desktop']['value']['weight'] );
        $this->assertSame( [ 'top' => '12px', 'right' => '24px', 'bottom' => '12px', 'left' => '24px' ], $d['spacing']['desktop']['value']['padding'] );
        $this->assertSame( '3px', $d['border']['desktop']['value']['radius']['topLeft'] );
    }

    public function test_without_any_kit_the_background_is_elementors_grey(): void {
        $this->assertSame( '#69727d', $this->button( [] )['background']['desktop']['value']['color'] );
    }

    public function test_kit_button_theme_style_overrides_the_built_in_defaults(): void {
        $this->kit( [ 'buttons' => [
            'background_color' => '#111111', 'text_color' => '#eeeeee',
            'typography' => [ 'size' => '18px', 'weight' => '500' ],
            'border_radius' => [ 'topLeft' => '30px', 'topRight' => '30px', 'bottomRight' => '30px', 'bottomLeft' => '30px' ],
            'padding' => [ 'top' => '20px', 'right' => '40px', 'bottom' => '20px', 'left' => '40px' ],
        ] ] );
        $d = $this->button( [] );

        $this->assertSame( '#111111', $d['background']['desktop']['value']['color'] );
        $this->assertSame( '#eeeeee', $d['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '18px', $d['font']['font']['desktop']['value']['size'] );
        $this->assertSame( '500', $d['font']['font']['desktop']['value']['weight'] );
        $this->assertSame( '30px', $d['border']['desktop']['value']['radius']['topLeft'] );
        $this->assertSame( '40px', $d['spacing']['desktop']['value']['padding']['right'] );
    }

    public function test_widget_values_win_over_kit_and_defaults(): void {
        $this->kit( [ 'buttons' => [ 'background_color' => '#111111', 'text_color' => '#eeeeee' ] ] );
        $d = $this->button( [
            'button_text_color' => '#F4F0EE',
            'border_radius' => [ 'unit' => 'px', 'top' => '50', 'right' => '50', 'bottom' => '50', 'left' => '50', 'isLinked' => '1' ],
            'text_padding' => [ 'unit' => 'px', 'top' => '20', 'right' => '35', 'bottom' => '20', 'left' => '35', 'isLinked' => '' ],
            'typography_typography' => 'custom', 'typography_font_size' => [ 'unit' => 'px', 'size' => 13 ],
        ] );

        $this->assertSame( '#F4F0EE', $d['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '13px', $d['font']['font']['desktop']['value']['size'] );
        $this->assertSame( '50px', $d['border']['desktop']['value']['radius']['topLeft'] );
        $this->assertSame( '35px', $d['spacing']['desktop']['value']['padding']['right'] );
        $this->assertSame( '#111111', $d['background']['desktop']['value']['color'], 'kit background still fills the gap' );
    }

    public function test_defaults_are_not_applied_without_the_option(): void {
        $this->kit();
        $this->assertArrayNotHasKey( 'background', ( new StyleMapper() )->map( 'button', [] )['divi_attrs']['button']['decoration'] ?? [] );
    }

    public function test_button_converter_applies_the_cascade(): void {
        $this->kit();
        $block = ( new ConverterEngine() )->convert( [ [ 'id' => 'b', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => [ 'text' => 'Go' ], 'elements' => [] ] ] )['divi']['elements'][0];
        $this->assertSame( '#C8643B', $block['settings']['button']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( '#ffffff', $block['settings']['button']['decoration']['font']['font']['desktop']['value']['color'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'button' );
    }

    public function test_installed_kit_button_theme_style_is_read(): void {
        update_option( 'elementor_active_kit', 900 );
        update_post_meta( 900, '_elementor_page_settings', [
            'button_background_color' => '#222222',
            'button_text_color'       => '#fafafa',
            'button_typography_font_size' => [ 'unit' => 'px', 'size' => 17 ],
            'button_typography_font_weight' => '700',
            'button_border_radius' => [ 'unit' => 'px', 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'isLinked' => '1' ],
            'button_padding' => [ 'unit' => 'px', 'top' => '10', 'right' => '30', 'bottom' => '10', 'left' => '30', 'isLinked' => '' ],
            'button_border_border' => 'solid', 'button_border_width' => [ 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => '1' ], 'button_border_color' => '#000000',
        ] );

        $this->assertSame( [
            'background_color' => '#222222',
            'text_color'       => '#fafafa',
            'typography'       => [ 'size' => '17px', 'weight' => '700' ],
            'border_radius'    => [ 'topLeft' => '4px', 'topRight' => '4px', 'bottomRight' => '4px', 'bottomLeft' => '4px' ],
            'padding'          => [ 'top' => '10px', 'right' => '30px', 'bottom' => '10px', 'left' => '30px' ],
            'border'           => [ 'style' => 'solid', 'width' => '2px', 'color' => '#000000' ],
        ], ConversionPreflight::elementorKitButtons() );
        $this->assertArrayHasKey( 'buttons', ConversionPreflight::installedKitGlobals() );
    }
}
```

Run: `vendor/bin/phpunit tests/ButtonDefaultsTest.php`
Expected: FAIL (map() ignores the third argument; `elementorKitButtons()` undefined).

- [ ] **Step 2: The kit side**

In `class-globals-resolver.php`, add:

```php
    /**
     * The kit's Theme Style → Buttons settings, in the shape
     * ConversionPreflight::elementorKitButtons() produces. Empty when no kit
     * provides one.
     */
    public static function resolveButtons(): array {
        $kit     = self::kitGlobals();
        $buttons = $kit['buttons'] ?? null;

        return is_array( $buttons ) ? $buttons : [];
    }
```

In `class-conversion-preflight.php`, add next to `elementorGlobalTypography()`:

```php
    /**
     * The installed Kit's Theme Style → Buttons (Elementor
     * core/kits/documents/tabs/theme-style-buttons.php: button_typography_*,
     * button_text_color, button_background_color, button_border_*,
     * button_border_radius, button_padding). Mirrors
     * Pro\Kit\KitGlobalsParser::buttonsFrom(); keep the two in step.
     *
     * @return array{background_color?: string, text_color?: string, typography?: array<string,string>, border_radius?: array<string,string>, padding?: array<string,string>, border?: array<string,string>}
     */
    public static function elementorKitButtons(): array {
        $kit_settings = self::activeKitSettings();
        return $kit_settings === null ? [] : self::buttonsFromKitSettings( $kit_settings );
    }

    /** Shared with Pro's kit parser through the same settings array shape. */
    public static function buttonsFromKitSettings( array $settings ): array {
        $buttons = [];

        foreach ( [ 'button_background_color' => 'background_color', 'button_text_color' => 'text_color' ] as $key => $out ) {
            $value = $settings[ $key ] ?? '';
            if ( is_string( $value ) && $value !== '' ) {
                $buttons[ $out ] = $value;
            }
        }

        $typography = [];
        if ( ! empty( $settings['button_typography_font_family'] ) ) {
            $typography['family'] = (string) $settings['button_typography_font_family'];
        }
        if ( ! empty( $settings['button_typography_font_weight'] ) ) {
            $typography['weight'] = (string) $settings['button_typography_font_weight'];
        }
        foreach ( [ 'button_typography_font_size' => [ 'size', 'px' ], 'button_typography_line_height' => [ 'lineHeight', 'em' ], 'button_typography_letter_spacing' => [ 'letterSpacing', 'px' ] ] as $key => [ $prop, $unit ] ) {
            $value = self::sizeWithUnit( $settings[ $key ] ?? null, $unit );
            if ( $value !== '' ) {
                $typography[ $prop ] = $value;
            }
        }
        if ( ! empty( $typography ) ) {
            $buttons['typography'] = $typography;
        }

        $radius = self::dimensions( $settings['button_border_radius'] ?? null );
        if ( $radius !== null ) {
            $buttons['border_radius'] = [ 'topLeft' => $radius['top'], 'topRight' => $radius['right'], 'bottomRight' => $radius['bottom'], 'bottomLeft' => $radius['left'] ];
        }
        $padding = self::dimensions( $settings['button_padding'] ?? null );
        if ( $padding !== null ) {
            $buttons['padding'] = $padding;
        }

        $border_style = $settings['button_border_border'] ?? '';
        if ( is_string( $border_style ) && $border_style !== '' && $border_style !== 'none' ) {
            $border = [ 'style' => $border_style ];
            $width  = self::dimensions( $settings['button_border_width'] ?? null );
            if ( $width !== null ) {
                $border['width'] = $width['top'];
            }
            $color = $settings['button_border_color'] ?? '';
            if ( is_string( $color ) && $color !== '' ) {
                $border['color'] = $color;
            }
            $buttons['border'] = $border;
        }

        return $buttons;
    }

    /** Elementor DIMENSIONS control {top,right,bottom,left,unit} → four CSS lengths, or null. */
    private static function dimensions( mixed $raw ): ?array {
        if ( ! is_array( $raw ) ) {
            return null;
        }
        $unit = is_string( $raw['unit'] ?? '' ) && $raw['unit'] !== '' ? $raw['unit'] : 'px';
        $out  = [];
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            $v = $raw[ $side ] ?? '';
            if ( $v === '' || $v === null ) {
                return null;
            }
            $out[ $side ] = (string) $v . $unit;
        }
        return $out;
    }
```

and change `installedKitGlobals()` to return `[ 'colors' => …, 'typography' => …, 'buttons' => self::elementorKitButtons() ]`.

In `class-plugin.php::fill_kit_globals_from_installed_kit()`, change the early return to `if ( empty( $installed['colors'] ) && empty( $installed['typography'] ) && empty( $installed['buttons'] ) )` and the merge to also carry `'buttons' => ( $kit['buttons'] ?? [] ) + $installed['buttons']`.

In Pro's `class-kit-globals-parser.php`, after `$typography` is built, add `$buttons = \ElementorDivi5Converter\Conversion\ConversionPreflight::buttonsFromKitSettings( $settings );` and include `'buttons' => $buttons` in the returned array (read the method's return statement to find the key list; the free plugin is loaded before Pro, so the class is available).

- [ ] **Step 3: The cascade in StyleMapper**

Change the signature to `public function map( string $widget_type, array $settings, array $options = [] ): array` and, inside the `if ( $widget_type === 'button' )` block after `mapButtonBorder`, add:

```php
            if ( ! empty( $options['elementor_defaults'] ) ) {
                $this->applyButtonDefaults( $divi_attrs );
            }
```

Add the method:

```php
    /**
     * Elementor's own button look, for properties the widget left unset.
     *
     * Cascade, most specific first: the widget's controls (already mapped),
     * the kit's Theme Style → Buttons (GlobalsResolver::resolveButtons()),
     * then Elementor's built-ins: the global accent colour as background
     * (button-trait.php, Group_Control_Background default COLOR_ACCENT) and
     * the accent typography, over .elementor-button's base rule
     * (assets/css/frontend.css: #69727d, #fff, 15px, 12px 24px, 3px).
     * Divi's default is a transparent outline in its accent blue, so without
     * this a button that set nothing looked nothing like the original.
     */
    private function applyButtonDefaults( array &$attrs ): void {
        $kit = GlobalsResolver::resolveButtons();

        $background = $kit['background_color'] ?? GlobalsResolver::resolveColor( 'accent' ) ?? '#69727d';
        $this->setIfUnset( $attrs, 'button.decoration.background.desktop.value.color', $background );
        $this->setIfUnset( $attrs, 'button.decoration.font.font.desktop.value.color', $kit['text_color'] ?? '#ffffff' );

        $typography = $kit['typography'] ?? GlobalsResolver::resolveTypography( 'accent' ) ?? [];
        foreach ( [ 'family', 'weight', 'size', 'lineHeight', 'letterSpacing' ] as $prop ) {
            if ( isset( $typography[ $prop ] ) && $typography[ $prop ] !== '' ) {
                $this->setIfUnset( $attrs, "button.decoration.font.font.desktop.value.{$prop}", (string) $typography[ $prop ] );
            }
        }
        $this->setIfUnset( $attrs, 'button.decoration.font.font.desktop.value.size', '15px' );

        $this->setIfUnset( $attrs, 'button.decoration.spacing.desktop.value.padding', $kit['padding'] ?? [ 'top' => '12px', 'right' => '24px', 'bottom' => '12px', 'left' => '24px' ] );
        $this->setIfUnset( $attrs, 'button.decoration.border.desktop.value.radius', $kit['border_radius'] ?? [ 'topLeft' => '3px', 'topRight' => '3px', 'bottomRight' => '3px', 'bottomLeft' => '3px' ] );

        if ( ! empty( $kit['border'] ) ) {
            $this->setIfUnset( $attrs, 'button.decoration.border.desktop.value.styles.all.style', $kit['border']['style'] );
            if ( isset( $kit['border']['width'] ) ) {
                $this->setIfUnset( $attrs, 'button.decoration.border.desktop.value.styles.all.width', $kit['border']['width'] );
            }
            if ( isset( $kit['border']['color'] ) ) {
                $this->setIfUnset( $attrs, 'button.decoration.border.desktop.value.styles.all.color', $kit['border']['color'] );
            }
        }
    }

    private function setIfUnset( array &$attrs, string $dot_path, mixed $value ): void {
        $current = $attrs;
        foreach ( explode( '.', $dot_path ) as $key ) {
            if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
                self::transformPath( $attrs, $dot_path, $value );
                return;
            }
            $current = $current[ $key ];
        }
    }
```

In `class-button-converter.php`, change `( new StyleMapper() )->map( 'button', $settings )` to `( new StyleMapper() )->map( 'button', $settings, [ 'elementor_defaults' => true ] )`.

Run: `vendor/bin/phpunit`
Expected: `ButtonDefaultsTest` passes. If `tests/StyleMapperTest.php` or a fixture test now fails because a button gained default keys: `StyleMapperTest` calls `map('button', …)` without the option, so it should be untouched; `fixtures/divi/button.json` and `real-elementor.json` (the button converter path) gain the defaults — regenerate those two the way Task 4 regenerated `column-overlay.json`, read the diff (only `button.decoration.*` additions), and keep going.

- [ ] **Step 4: Render assertions**

Append to the probe describe:

```ts
  test('button with only a global background: accent background, white text', async ({ page }) => {
    const button = page.locator('a.et_pb_button', { hasText: 'Probe button' });
    await expect(button).toBeVisible();
    expect(await css(button, 'background-color')).toBe('rgb(200, 100, 59)');
    expect(await css(button, 'color')).toBe('rgb(255, 255, 255)');
    expect(await css(button, 'border-top-left-radius')).toBe('3px');
  });
```

and a new describe for the header:

```ts
test.describe('Theme Builder header (HFE header)', () => {
  test('button: background colour and white text', async ({ page }) => {
    await page.goto(draft('home'));
    const button = page.locator('header a.et_pb_button, .et-l--header a.et_pb_button', { hasText: 'Book a tour' }).first();
    await expect(button).toBeVisible();
    expect(await css(button, 'background-color')).toBe('rgb(200, 100, 59)');
    expect(await css(button, 'color')).toBe('rgb(255, 255, 255)');
  });
});
```

Run: `demo/verify.sh render && demo/verify.sh reset` → passes. The Theme Builder header was built at seed time with Pro 1.2.0's exporter from the same converter code (bind-mounted), so it reflects the fix only after `demo/lib/theme-builder.php` re-runs; if the header assertion fails on the old layout, run `demo/wp theme activate Divi && demo/wp --user=admin eval-file /demo/lib/theme-builder.php`, then `demo/snapshot.sh`, and re-run the render check.

- [ ] **Step 5: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes plugin/jhmg-converter-for-elementor-to-divi-pro/includes/kit/class-kit-globals-parser.php tests/ButtonDefaultsTest.php fixtures/divi demo/tests/render.spec.ts
git commit -m "fix(converter): buttons keep Elementor's default look and the kit's button theme style

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7: Counter shows the plain number, a percent sign only for %, and its title colour

**Files:**
- Modify: `handlers/class-counter-converter.php`, `includes/stylemapper/class-style-mapper.php`, `includes/admin/class-not-carried-over-renderer.php`, `includes/converter/class-converter-engine.php` (docblock of `logNotCarriedOver` kinds), `demo/tests/render.spec.ts`, `demo/content/pages/about.php` (comment only)
- Test: `tests/CounterConversionTest.php`

**Interfaces:**
- Produces: not-carried-over kind `counter_affix`, labelled in `NotCarriedOverRenderer::groups()`.

- [ ] **Step 1: Failing test**

Create `tests/CounterConversionTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * divi/number-counter animates number.innerContent as a number and appends its own
 * percent sign while number.advanced.enablePercentSign is on, which is the default
 * (number-counter/module.json). "240+" therefore showed as "233%" mid-animation and
 * "58%" as "58%%". Divi has no other prefix or suffix; those are reported.
 */
final class CounterConversionTest extends TestCase {
    private function convert( array $settings ): array {
        return ( new ConverterEngine() )->convert( [ [ 'id' => 'c1', 'elType' => 'widget', 'widgetType' => 'counter', 'settings' => $settings, 'elements' => [] ] ] );
    }

    public function test_percent_suffix_uses_divis_percent_sign(): void {
        $block = $this->convert( [ 'starting_number' => 0, 'ending_number' => 58, 'suffix' => '%', 'title' => 'Freelancers' ] )['divi']['elements'][0];
        $this->assertSame( '58', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['number']['advanced']['enablePercentSign']['desktop']['value'] );
        $this->assertSame( 'Freelancers', $block['settings']['title']['innerContent']['desktop']['value'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'counter' );
    }

    public function test_other_affixes_are_dropped_and_reported(): void {
        $result = $this->convert( [ 'ending_number' => '240', 'prefix' => '$', 'suffix' => '+', 'title' => 'Members' ] );
        $block  = $result['divi']['elements'][0];
        $this->assertSame( '240', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'off', $block['settings']['number']['advanced']['enablePercentSign']['desktop']['value'] );
        $this->assertSame( [ [ 'kind' => 'counter_affix', 'element_id' => 'c1', 'detail' => "prefix '$', suffix '+'" ] ], $result['report']['not_carried_over'] );
        $this->assertSame( [], $result['report']['warnings'] );
    }

    public function test_a_number_without_suffix_shows_no_percent_sign(): void {
        $block = $this->convert( [ 'ending_number' => 1200 ] )['divi']['elements'][0];
        $this->assertSame( '1200', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'off', $block['settings']['number']['advanced']['enablePercentSign']['desktop']['value'] );
    }

    public function test_title_colour_and_typography_reach_the_title_font(): void {
        $block = $this->convert( [ 'ending_number' => 5, 'title' => 'x', 'title_color' => '#1F2421', 'title_typography_typography' => 'custom', 'title_typography_font_size' => [ 'unit' => 'px', 'size' => 14 ], 'number_color' => '#2F4F3A' ] )['divi']['elements'][0];
        $this->assertSame( '#1F2421', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['color'] );
        $this->assertSame( '14px', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['size'] );
        $this->assertSame( '#2F4F3A', $block['settings']['number']['decoration']['font']['font']['desktop']['value']['color'] );
    }
}
```

Run: `vendor/bin/phpunit tests/CounterConversionTest.php` → FAIL (value `58%`, no `enablePercentSign`).

- [ ] **Step 2: Fix the converter**

In `class-counter-converter.php`, replace from `$display_number = $prefix . $number . $suffix;` through the `if ( $title !== '' )` block with:

```php
        // number.innerContent must be the bare number: Divi animates it and adds
        // its own sign while number.advanced.enablePercentSign is on, the
        // default (number-counter/module.json). Only a '%' suffix maps; any other
        // prefix or suffix has no home in the module and is reported.
        $numeric = preg_replace( '/[^0-9.\-]/', '', $number );
        $numeric = $numeric === '' || $numeric === '-' ? '0' : $numeric;

        $style          = ( new StyleMapper() )->map( 'counter', $settings );
        $block_settings = $style['divi_attrs'];

        $block_settings['number']['innerContent']['desktop']['value']        = $numeric;
        $block_settings['number']['advanced']['enablePercentSign']['desktop']['value'] = trim( $suffix ) === '%' ? 'on' : 'off';

        $dropped = [];
        if ( trim( $prefix ) !== '' ) {
            $dropped[] = "prefix '" . trim( $prefix ) . "'";
        }
        if ( trim( $suffix ) !== '' && trim( $suffix ) !== '%' ) {
            $dropped[] = "suffix '" . trim( $suffix ) . "'";
        }
        if ( $dropped !== [] ) {
            $this->engine->logNotCarriedOver( 'counter_affix', (string) $id, implode( ', ', $dropped ) );
        }

        if ( $title !== '' ) {
            $block_settings['title']['innerContent']['desktop']['value'] = $title;
        }
```

and delete the earlier `$style = …; $block_settings = …; $block_settings['number']['innerContent']… = $display_number;` lines.

In `class-style-mapper.php`, add the counter's title as a secondary font: `WIDGET_SECONDARY_FONT_PATH['counter'] = 'title.decoration.font.font'`, `WIDGET_SECONDARY_TYPOGRAPHY_PREFIX['counter'] = 'title_typography_'`, `WIDGET_SECONDARY_COLOR_KEY['counter'] = 'title_color'`.

In `class-not-carried-over-renderer.php::groups()`, add `'counter_affix' => __( 'Counter prefixes and suffixes — dropped; Divi\'s number counter offers only a percent sign', 'jhmg-converter-for-elementor-to-divi' ),`. In `ConverterEngine::logNotCarriedOver()`'s docblock, extend the kinds list with `'counter_affix'`.

Run: `vendor/bin/phpunit` → OK. Run `vendor/bin/phpunit -c demo/phpunit.xml` → OK (the About counters use `%`).

- [ ] **Step 3: Render assertion and the demo comment**

Append to the probe describe:

```ts
  test('counter: bare number, percent sign only for %', async ({ page }) => {
    await page.waitForTimeout(3000); // the count-up animation
    const counters = page.locator('.et_pb_number_counter .percent-value');
    await expect(counters).toHaveCount(2);
    await expect(counters.nth(0)).toHaveText('58');
    await expect(page.locator('.et_pb_number_counter .percent p').nth(0)).toHaveText('58%');
    await expect(page.locator('.et_pb_number_counter .percent p').nth(1)).toHaveText('240');
  });
```

(If the DOM differs, inspect `.et_pb_number_counter` on the draft and adjust the selectors to the `.percent` element Divi renders.)

In `demo/content/pages/about.php`, delete the comment `// Elementor defaults the counter title to the kit's secondary color, our cream background.`? No — that line documents Elementor's default and stays. Leave the file unchanged; the counter fix is exercised by the probe.

Run: `demo/verify.sh render && demo/verify.sh reset` → passes.

- [ ] **Step 4: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes tests/CounterConversionTest.php demo/tests/render.spec.ts
git commit -m "fix(converter): counters show the number, use Divi's percent sign only for %

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 8: Carousels and galleries use attachment IDs

**Files:**
- Create: `includes/helpers/class-attachment-resolver.php`
- Modify: `handlers/class-image-carousel-converter.php`, `handlers/class-eael-filterable-gallery-converter.php`, `handlers/class-gallery-converter.php`, `class-not-carried-over-renderer.php`, `fixtures/divi/image-gallery-native.json` (regenerated), `tests/support/divi-schema-known-gaps.php`, `demo/tests/render.spec.ts`
- Test: `tests/GalleryConversionTest.php`

**Interfaces:**
- Produces: `ElementorDivi5Converter\Helpers\AttachmentResolver::idFor( int $id, string $url ): int` — the ID when it is an image attachment on this site (or when WordPress is not loaded and `$id > 0`), else the attachment whose URL matches, else 0.
- Produces: `AttachmentResolver::ids( array $items ): int[]` for a list of `{id, url}` items, skipping zeros.
- Produces: not-carried-over kind `gallery_extras`.

- [ ] **Step 1: Failing test**

Create `tests/GalleryConversionTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Helpers\AttachmentResolver;

/**
 * divi/gallery lists the attachments in image.advanced.galleryIds
 * (GalleryModule.php:754, get_posts include/post__in); with none it lists the
 * whole media library. Divi paginates at module.advanced.postsNumber, default 4.
 */
final class GalleryConversionTest extends TestCase {
    private function convert( string $type, array $settings ): array {
        return ( new ConverterEngine() )->convert( [ [ 'id' => 'g1', 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ] ] );
    }

    private function images( int $n ): array {
        $out = [];
        for ( $i = 1; $i <= $n; $i++ ) {
            $out[] = [ 'id' => 100 + $i, 'url' => "https://x.test/img-{$i}.jpg" ];
        }
        return $out;
    }

    public function test_image_carousel_with_several_slides_becomes_a_gallery_grid(): void {
        $block = $this->convert( 'image-carousel', [ 'carousel' => $this->images( 4 ), 'slides_to_show' => '4', 'thumbnail_size' => 'full' ] )['divi']['elements'][0];
        $this->assertSame( 'divi/gallery', $block['name'] );
        $this->assertSame( [ 101, 102, 103, 104 ], $block['settings']['image']['advanced']['galleryIds']['desktop']['value'] );
        $this->assertSame( '4', $block['settings']['module']['advanced']['postsNumber']['desktop']['value'] );
        $this->assertSame( '4', $block['settings']['galleryGrid']['decoration']['layout']['desktop']['value']['gridColumnCount'] );
        $this->assertSame( 'off', $block['settings']['module']['advanced']['showTitleAndCaption']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'fullwidth', $block['settings']['module']['advanced'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'carousel' );
    }

    public function test_single_slide_carousel_becomes_divis_slider_layout_with_autoplay(): void {
        $block = $this->convert( 'image-carousel', [ 'carousel' => $this->images( 3 ), 'slides_to_show' => '1', 'autoplay' => 'yes', 'autoplay_speed' => 4000 ] )['divi']['elements'][0];
        $this->assertSame( 'on', $block['settings']['module']['advanced']['fullwidth']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['module']['advanced']['auto']['desktop']['value'] );
        $this->assertSame( '4000', $block['settings']['module']['advanced']['autoSpeed']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'galleryGrid', $block['settings'] );
    }

    public function test_carousel_without_attachments_falls_back_to_inline_images_and_warns(): void {
        $result = $this->convert( 'image-carousel', [ 'carousel' => [ [ 'url' => 'https://x.test/a.jpg' ], [ 'url' => 'https://x.test/b.jpg' ] ] ] );
        $block  = $result['divi']['elements'][0];
        $this->assertSame( 'divi/text', $block['name'] );
        $this->assertStringContainsString( 'src="https://x.test/a.jpg"', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertStringNotContainsString( 'max-height:60px', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertNotEmpty( $result['report']['warnings'] );
    }

    public function test_filterable_gallery_uses_ids_and_reports_filters_names_and_captions(): void {
        $result = $this->convert( 'eael-filterable-gallery', [
            'columns' => '3',
            'eael_fg_controls' => [ [ 'eael_fg_control' => 'Rooms' ], [ 'eael_fg_control' => 'Desks' ] ],
            'eael_fg_gallery_items' => [
                [ 'eael_fg_gallery_item_name' => 'Lounge', 'eael_fg_gallery_item_content' => 'Sofas', 'fg_item_cat' => 'Rooms', 'eael_fg_gallery_img' => [ 'id' => 201, 'url' => 'https://x.test/1.jpg' ] ],
                [ 'eael_fg_gallery_item_name' => 'Desk', 'fg_item_cat' => 'Desks', 'eael_fg_gallery_img' => [ 'id' => 202, 'url' => 'https://x.test/2.jpg' ] ],
            ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( [ 201, 202 ], $block['settings']['image']['advanced']['galleryIds']['desktop']['value'] );
        $this->assertSame( '2', $block['settings']['module']['advanced']['postsNumber']['desktop']['value'] );
        $this->assertSame( '3', $block['settings']['galleryGrid']['decoration']['layout']['desktop']['value']['gridColumnCount'] );
        $this->assertArrayNotHasKey( 'innerContent', $block['settings']['galleryGrid'] );
        $kinds = array_column( $result['report']['not_carried_over'], 'detail', 'kind' );
        $this->assertStringContainsString( 'Rooms, Desks', $kinds['gallery_extras'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'filterable gallery' );
    }

    public function test_core_gallery_lists_every_image(): void {
        $block = $this->convert( 'image-gallery', [ 'wp_gallery' => $this->images( 6 ), 'gallery_columns' => 3 ] )['divi']['elements'][0];
        $this->assertSame( '6', $block['settings']['module']['advanced']['postsNumber']['desktop']['value'] );
        $this->assertSame( '3', $block['settings']['galleryGrid']['decoration']['layout']['desktop']['value']['gridColumnCount'] );
    }

    public function test_resolver_keeps_ids_outside_wordpress(): void {
        $this->assertSame( 7, AttachmentResolver::idFor( 7, 'https://x.test/a.jpg' ) );
        $this->assertSame( 0, AttachmentResolver::idFor( 0, 'https://x.test/a.jpg' ) );
        $this->assertSame( [ 1, 3 ], AttachmentResolver::ids( [ [ 'id' => 1 ], [ 'url' => 'u' ], [ 'id' => '3', 'url' => 'v' ] ] ) );
    }
}
```

Run: `vendor/bin/phpunit tests/GalleryConversionTest.php` → FAIL.

- [ ] **Step 2: The resolver**

Create `includes/helpers/class-attachment-resolver.php`:

```php
<?php

namespace ElementorDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor stores gallery and carousel images as {id, url}. Divi's gallery
 * needs attachment IDs (gallery/module.json image.advanced.galleryIds). On the
 * site the page came from, the ID is right; on a site that imported a kit or
 * a JSON export, Elementor remaps IDs on import, and a URL that still points
 * at this site's uploads can be looked up. Anything else is not in this
 * media library and cannot be listed.
 */
final class AttachmentResolver {
    public static function idFor( int $id, string $url ): int {
        if ( ! function_exists( 'wp_attachment_is_image' ) ) {
            // Outside WordPress (unit tests) there is nothing to check against.
            return $id > 0 ? $id : 0;
        }
        if ( $id > 0 && wp_attachment_is_image( $id ) ) {
            return $id;
        }
        if ( $url !== '' && function_exists( 'attachment_url_to_postid' ) ) {
            $found = (int) attachment_url_to_postid( self::withoutSizeSuffix( $url ) );
            if ( $found > 0 ) {
                return $found;
            }
        }
        return 0;
    }

    /** @param array<int, array{id?: int|string, url?: string}> $items */
    public static function ids( array $items ): array {
        $ids = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $id = self::idFor( (int) ( $item['id'] ?? 0 ), is_string( $item['url'] ?? '' ) ? (string) ( $item['url'] ?? '' ) : '' );
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /** ".../photo-300x200.jpg" → ".../photo.jpg": attachment_url_to_postid() knows only the original. */
    private static function withoutSizeSuffix( string $url ): string {
        return (string) preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $url );
    }
}
```

- [ ] **Step 3: The three converters**

Rewrite `class-image-carousel-converter.php`'s `convert()`:

```php
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_gallery_' );
        $settings = $element['settings'] ?? [];
        $slides   = is_array( $settings['carousel'] ?? null ) ? $settings['carousel'] : [];
        $ids      = AttachmentResolver::ids( $slides );

        $handled = [
            'carousel', 'image_size', 'image_fit', 'thumbnail_size', 'thumbnail_custom_dimension',
            'slides_to_show', 'slides_to_show_tablet', 'slides_to_show_mobile',
            'slides_to_scroll', 'autoplay', 'autoplay_speed', 'infinite', 'pause_on_hover',
            'effect', 'speed', 'navigation', 'pagination',
            'image_spacing', 'image_spacing_tablet', 'image_spacing_mobile',
            'arrows_position', 'arrows_size', 'gallery_vertical_align', 'carousel_name',
        ];

        if ( $ids === [] ) {
            // Nothing in this media library: keep the images inline so they are
            // at least visible and editable, and say why they are not a gallery.
            $this->engine->logWarning( "Image carousel {$id}: none of its images are in this site's media library, so it was placed as a row of inline images instead of a Divi gallery." );
            $this->engine->logConverted( 'text' );
            $this->logUnmappedSettings( $id, $settings, $handled );
            return [
                'id'       => $id,
                'name'     => 'divi/text',
                'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $this->buildInlineHtml( $slides ) ] ] ] ],
                'elements' => [],
            ];
        }

        // divi/gallery (gallery/module.json): image.advanced.galleryIds lists the
        // attachments; module.advanced.postsNumber is the page size (default 4);
        // module.advanced.fullwidth = on is Divi's one-image-at-a-time slider.
        // Elementor shows slides_to_show images side by side (default 3), which
        // a grid of that many columns matches better than a slider.
        $slides_to_show = (int) ( $settings['slides_to_show'] ?? 3 );
        $slides_to_show = $slides_to_show > 0 ? $slides_to_show : 3;

        $block_settings = [
            'image'  => [ 'advanced' => [ 'galleryIds' => [ 'desktop' => [ 'value' => $ids ] ] ] ],
            'module' => [ 'advanced' => [
                'postsNumber'         => [ 'desktop' => [ 'value' => (string) count( $ids ) ] ],
                'showTitleAndCaption' => [ 'desktop' => [ 'value' => 'off' ] ],
            ] ],
        ];

        if ( $slides_to_show === 1 ) {
            $block_settings['module']['advanced']['fullwidth'] = [ 'desktop' => [ 'value' => 'on' ] ];
            if ( ( $settings['autoplay'] ?? '' ) === 'yes' ) {
                $block_settings['module']['advanced']['auto'] = [ 'desktop' => [ 'value' => 'on' ] ];
                $speed = (int) ( $settings['autoplay_speed'] ?? 0 );
                if ( $speed > 0 ) {
                    $block_settings['module']['advanced']['autoSpeed'] = [ 'desktop' => [ 'value' => (string) $speed ] ];
                }
            }
        } else {
            $block_settings['galleryGrid'] = [ 'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => [ 'display' => 'grid', 'gridColumnCount' => (string) $slides_to_show ] ] ] ] ];
        }

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, $handled );

        return [ 'id' => $id, 'name' => 'divi/gallery', 'settings' => $block_settings, 'elements' => [] ];
    }
```

rename `buildCarouselHtml` to `buildInlineHtml` and drop the `style="max-height:60px;width:auto;object-fit:contain;"` attribute (keep `style="max-width:100%;height:auto;"`). Add `use ElementorDivi5Converter\Helpers\AttachmentResolver;`.

Rewrite `class-eael-filterable-gallery-converter.php`'s `convert()`:

```php
    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_gallery_' );
        $settings = $element['settings'] ?? [];

        $items  = is_array( $settings['eael_fg_gallery_items'] ?? null ) ? $settings['eael_fg_gallery_items'] : [];
        $images = [];
        $names  = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $img = $item['eael_fg_gallery_img'] ?? $item['fg_gallery_img'] ?? [];
            if ( is_array( $img ) ) {
                $images[] = $img;
            }
            foreach ( [ 'eael_fg_gallery_item_name', 'eael_fg_gallery_item_content' ] as $key ) {
                $text = $item[ $key ] ?? '';
                if ( is_string( $text ) && trim( wp_strip_all_tags( $text ) ) !== '' ) {
                    $names[] = trim( wp_strip_all_tags( $text ) );
                }
            }
        }
        $ids = AttachmentResolver::ids( $images );

        // EAEL 6.6.7 Filterable_Gallery.php: `columns` (1-6, default 3), the
        // `eael_fg_controls` repeater of filter labels, and per-item name,
        // content and category. Divi's gallery has no filter bar and takes its
        // captions from the media library, so those are reported, not dropped.
        $columns = (string) ( (int) ( $settings['columns'] ?? 3 ) ?: 3 );
        $filters = [];
        foreach ( is_array( $settings['eael_fg_controls'] ?? null ) ? $settings['eael_fg_controls'] : [] as $control ) {
            $label = is_array( $control ) ? ( $control['eael_fg_control'] ?? '' ) : '';
            if ( is_string( $label ) && $label !== '' ) {
                $filters[] = $label;
            }
        }
        if ( $filters !== [] ) {
            $this->engine->logNotCarriedOver( 'gallery_extras', (string) $id, 'filter buttons: ' . implode( ', ', $filters ) );
        }
        if ( $names !== [] ) {
            $this->engine->logNotCarriedOver( 'gallery_extras', (string) $id, count( $names ) . ' item names and captions (Divi shows the media library title and caption)' );
        }

        $block_settings = [];
        if ( $ids !== [] ) {
            $block_settings = [
                'image'       => [ 'advanced' => [ 'galleryIds' => [ 'desktop' => [ 'value' => $ids ] ] ] ],
                'module'      => [ 'advanced' => [
                    'postsNumber'         => [ 'desktop' => [ 'value' => (string) count( $ids ) ] ],
                    'showTitleAndCaption' => [ 'desktop' => [ 'value' => 'off' ] ],
                ] ],
                'galleryGrid' => [ 'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => [ 'display' => 'grid', 'gridColumnCount' => $columns ] ] ] ] ],
            ];
        } else {
            $this->engine->logWarning( "Filterable gallery {$id}: none of its images are in this site's media library; the Divi gallery would list every attachment, so it was left empty." );
        }

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_fg_gallery_items', 'eael_fg_controls', 'columns', 'columns_tablet', 'columns_mobile', 'eael_fg_show_popup',
            'eael_section_fg_full_image_action', 'load_more_icon_new',
            'fg_all_label_icon', 'show_load_more',
        ] );

        return [ 'id' => $id, 'name' => 'divi/gallery', 'settings' => $block_settings, 'elements' => [] ];
    }
```

`wp_strip_all_tags` is stubbed in the demo bootstrap but not in `tests/bootstrap.php`: add the same stub there (copy from `demo/tests/php/bootstrap.php`).

In `class-gallery-converter.php`, replace `$block_settings = []; if ( ! empty( $ids ) ) { … }` with:

```php
        $block_settings = [];
        if ( ! empty( $ids ) ) {
            $columns = (int) ( $settings['gallery_columns'] ?? 4 );
            $block_settings['image']['advanced']['galleryIds']['desktop']['value'] = $ids;
            // Divi paginates at postsNumber (default 4); list everything, as Elementor does.
            $block_settings['module']['advanced']['postsNumber']['desktop']['value'] = (string) count( $ids );
            if ( $columns > 0 ) {
                $block_settings['galleryGrid']['decoration']['layout']['desktop']['value'] = [ 'display' => 'grid', 'gridColumnCount' => (string) $columns ];
            }
        }
```

and use `AttachmentResolver::ids( is_array( $raw_images ) ? $raw_images : [] )` for `$ids` (delete the manual loop over `$raw_images`, keeping `$urls` out entirely).

Add `'gallery_extras' => __( 'Gallery filters, item names and captions — Divi\'s gallery has no filter bar and shows media library titles and captions', 'jhmg-converter-for-elementor-to-divi' ),` to `NotCarriedOverRenderer::groups()`; extend the `logNotCarriedOver` kinds docblock. Remove the `divi/gallery galleryGrid.innerContent` known gap.

Run: `vendor/bin/phpunit`. `fixtures/divi/image-gallery-native.json` now differs (postsNumber, layout): regenerate it as in Task 4, check the diff, rerun → OK. `vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 4: Render assertions**

Probe describe:

```ts
  test('image carousel (4 slides): a four-column gallery of full-size images', async ({ page }) => {
    const images = page.locator('.et_pb_gallery').first().locator('.et_pb_gallery_image img');
    await expect(images).toHaveCount(4);
    for (const img of await images.all()) {
      expect((await img.boundingBox())?.width ?? 0).toBeGreaterThan(200);
    }
    await expect(page.locator('.et_pb_gallery').first().locator('.et_pb_gallery_pagination')).toHaveCount(0);
  });

  test('image gallery: three items', async ({ page }) => {
    await expect(page.locator('.et_pb_gallery').nth(1).locator('.et_pb_gallery_item')).toHaveCount(3);
  });
```

A Spaces describe:

```ts
test.describe('spaces', () => {
  test('filterable gallery (EAEL): exactly its own images, not the media library', async ({ page }) => {
    await page.goto(draft('spaces'));
    await settle(page);
    const items = page.locator('.et_pb_gallery .et_pb_gallery_item');
    await expect(items).toHaveCount(SPACES_GALLERY_IMAGES);
    await expect(page.locator('.et_pb_gallery_pagination')).toHaveCount(0);
  });
});
```

with `const SPACES_GALLERY_IMAGES = N;` at the top of the file, N read from `demo/content/pages/spaces.php` (count the `eael_fg_gallery_items` entries).

Run: `demo/verify.sh render && demo/verify.sh reset` → passes.

- [ ] **Step 5: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes tests/GalleryConversionTest.php tests/bootstrap.php tests/support/divi-schema-known-gaps.php fixtures/divi/image-gallery-native.json demo/tests/render.spec.ts
git commit -m "fix(converter): carousels and galleries list their attachments instead of the media library

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 9: Google Maps renders

**Files:**
- Modify: `handlers/class-google-maps-converter.php`, `fixtures/divi/google-maps-native.json`, `docs/known-issues.md`, `demo/tests/render.spec.ts`
- Test: `tests/GoogleMapsConversionTest.php`

- [ ] **Step 1: Reproduce on the probe**

Run: `demo/verify.sh render` (leave the site converted; do not reset yet). Then:

```bash
ID=$(python3 -c "import json;print([c for c in json.load(open('demo/output/converted.json')) if c['slug']=='core-widgets'][0]['draft_id'])")
demo/wp post get "$ID" --field=post_content | grep -o '<!-- wp:divi/code.\{0,400\}'
```

Expected: the block carries the `<iframe src="https://maps.google.com/maps?q=…&amp;output=embed" …>` markup. Then open the draft in Playwright and dump what Divi rendered:

```bash
cat > /tmp/map-probe.cjs <<'JS'
const { chromium } = require('@playwright/test');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto('http://localhost:8040/wp-login.php'); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'ferncourt-demo'); await p.click('#wp-submit'); await p.waitForURL(/wp-admin/);
  await p.goto(`http://localhost:8040/?page_id=${process.argv[2]}&preview=true`);
  console.log(await p.locator('.et_pb_code').first().evaluate((el) => el.outerHTML));
  console.log(await p.locator('.et_pb_code iframe').count(), 'iframes', await p.locator('.et_pb_code iframe').first().boundingBox().catch(() => null));
  await b.close();
})();
JS
NODE_PATH=$PWD/node_modules node /tmp/map-probe.cjs "$ID"
```

Decide from the output:
- **No `<iframe>` in the rendered HTML** although the block has it: Divi's code module dropped it. Fix: emit `divi/text` instead (the text module renders raw HTML; Task 8's inline-image fallback proves it), keeping the same iframe markup, and count it as `text`.
- **`<iframe>` present but 0 px tall / not visible**: the `height="400"` attribute is being overridden; add `style="border:0;width:100%;height:400px;"` and a `min-height` on the module (`module.decoration.sizing.desktop.value.minHeight` = the Elementor `height` slider, default `400px`).
- **`<iframe>` present, sized, but its document is blank**: the `src` was altered (look for `&amp;amp;` or a stripped `output=embed`); build the URL with `&` (not `&amp;`) via `add_query_arg`-style concatenation and re-test.

Record the finding (which branch, with the evidence lines) in `docs/known-issues.md` under the map item.

- [ ] **Step 2: Failing test for the chosen fix**

Create `tests/GoogleMapsConversionTest.php` asserting the chosen output — for the first branch:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/** See docs/known-issues.md, "google_maps": why the embed is a text module. */
final class GoogleMapsConversionTest extends TestCase {
    public function test_map_embed_is_rendered_through_the_text_module_with_its_height(): void {
        $block = ( new ConverterEngine() )->convert( [ [ 'id' => 'm', 'elType' => 'widget', 'widgetType' => 'google_maps', 'settings' => [ 'address' => 'Downtown Portland, Oregon', 'zoom' => [ 'unit' => 'px', 'size' => 14 ], 'height' => [ 'unit' => 'px', 'size' => 360 ] ], 'elements' => [] ] ] )['divi']['elements'][0];

        $this->assertSame( 'divi/text', $block['name'] );
        $html = $block['settings']['content']['innerContent']['desktop']['value'];
        $this->assertStringContainsString( 'src="https://maps.google.com/maps?q=Downtown%20Portland%2C%20Oregon&z=14&output=embed"', $html );
        $this->assertStringContainsString( 'height:360px', $html );
        $this->assertSame( '360px', $block['settings']['module']['decoration']['sizing']['desktop']['value']['minHeight'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'map' );
    }
}
```

(For the other branches keep `divi/code` in the assertion and adjust the `src`/style expectations to the fix.)

Run: `vendor/bin/phpunit tests/GoogleMapsConversionTest.php` → FAIL.

- [ ] **Step 3: Fix, regenerate the fixture, render**

Change `GoogleMapsConverter::convert()` accordingly (block name, `$html` built with `&`, `style="border:0;width:100%;height:{$height}px;"`, `module.decoration.sizing…minHeight`, `logConverted('text')` or `'code'`), where `$height = (int) ( $settings['height']['size'] ?? 400 ) ?: 400`. Regenerate `fixtures/divi/google-maps-native.json` (Task 4's command with the fixture name), review the diff.

Append to the probe describe:

```ts
  test('google_maps: the embed is visible and at least 300px tall', async ({ page }) => {
    const frame = page.locator('iframe[src*="maps.google.com"]').first();
    await expect(frame).toBeVisible();
    expect((await frame.boundingBox())?.height ?? 0).toBeGreaterThan(300);
  });
```

Run: `vendor/bin/phpunit && demo/verify.sh render && demo/verify.sh reset` → passes.

- [ ] **Step 4: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-google-maps-converter.php fixtures/divi/google-maps-native.json tests/GoogleMapsConversionTest.php docs/known-issues.md demo/tests/render.spec.ts
git commit -m "fix(converter): Google Maps embeds render on the converted page

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 10: Social icons keep their network; the HFE menu loses its white bar

**Files:**
- Modify: `handlers/class-social-icons-converter.php`, `handlers/class-hfe-navigation-menu-converter.php`, `class-not-carried-over-renderer.php`, `demo/tests/render.spec.ts`
- Test: `tests/SocialIconsConversionTest.php`, `tests/AddonSettingNamesTest.php` (menu case)

- [ ] **Step 1: Failing tests**

Create `tests/SocialIconsConversionTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor 4.1.3 social-icons.php stores each item's icon under `social_icon`
 * as {value: "fab fa-instagram", library: "fa-brands"} (ICONS control,
 * fa4compatibility "social" keeps the legacy `social` string). The converter
 * read `social_icon` as a string, found nothing, and fell back to Facebook.
 * Divi's network slugs: SocialMediaFollowItemModule::get_social_networks().
 */
final class SocialIconsConversionTest extends TestCase {
    private function convert( array $items ): array {
        return ( new ConverterEngine() )->convert( [ [ 'id' => 's1', 'elType' => 'widget', 'widgetType' => 'social-icons', 'settings' => [ 'social_icon_list' => $items ], 'elements' => [] ] ] );
    }

    private function networks( array $result ): array {
        return array_map( static fn( array $c ): string => $c['settings']['socialNetwork']['innerContent']['desktop']['value']['title'], $result['divi']['elements'][0]['elements'] );
    }

    public function test_icons_control_values_map_to_their_networks(): void {
        $result = $this->convert( [
            [ '_id' => 'a', 'social_icon' => [ 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.instagram.com/' ] ],
            [ '_id' => 'b', 'social_icon' => [ 'value' => 'fab fa-linkedin-in', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.linkedin.com/' ] ],
            [ '_id' => 'c', 'social_icon' => [ 'value' => 'fab fa-facebook-f', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.facebook.com/' ] ],
            [ '_id' => 'd', 'social_icon' => [ 'value' => 'fab fa-x-twitter', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://x.com/' ] ],
        ] );
        $this->assertSame( [ 'instagram', 'linkedin', 'facebook', 'twitter' ], $this->networks( $result ) );
        $this->assertSame( 'https://www.instagram.com/', $result['divi']['elements'][0]['elements'][0]['settings']['socialNetwork']['innerContent']['desktop']['value']['link'] );
        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], 'social icons' );
    }

    public function test_legacy_social_string_still_maps(): void {
        $this->assertSame( [ 'youtube' ], $this->networks( $this->convert( [ [ 'social' => 'fa fa-youtube', 'link' => [ 'url' => 'https://youtube.com/' ] ] ] ) ) );
    }

    public function test_unknown_network_is_skipped_and_reported(): void {
        $result = $this->convert( [
            [ 'social_icon' => [ 'value' => 'fab fa-mastodon', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://m.test/' ] ],
            [ 'social_icon' => [ 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.instagram.com/' ] ],
        ] );
        $this->assertSame( [ 'instagram' ], $this->networks( $result ) );
        $this->assertSame( 'social_network', $result['report']['not_carried_over'][0]['kind'] );
        $this->assertStringContainsString( 'mastodon', $result['report']['not_carried_over'][0]['detail'] );
    }
}
```

Add to `tests/AddonSettingNamesTest.php` (HFE section):

```php
    public function test_navigation_menu_sits_on_a_transparent_background(): void {
        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => 'primary', 'layout' => 'horizontal' ] );

        $this->assertSame( 'divi/menu', $block['name'] );
        // menu/module-default-render-attributes.json paints #ffffff behind the menu; the header
        // container's background is what the page shows through in Elementor.
        $this->assertSame( 'rgba(255,255,255,0)', $block['settings']['module']['decoration']['background']['desktop']['value']['color'] );
    }

    public function test_navigation_menu_item_background_becomes_the_menu_background(): void {
        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => 'primary', 'bg_color_menu_item' => '#2F4F3A' ] );

        $this->assertSame( '#2F4F3A', $block['settings']['module']['decoration']['background']['desktop']['value']['color'] );
    }
```

Run: `vendor/bin/phpunit tests/SocialIconsConversionTest.php tests/AddonSettingNamesTest.php` → FAIL.

- [ ] **Step 2: Fix both converters**

In `class-social-icons-converter.php`, add to `NETWORK_MAP`: `'facebook-f' => 'facebook', 'square-facebook' => 'facebook', 'linkedin-in' => 'linkedin', 'square-instagram' => 'instagram', 'square-x-twitter' => 'twitter', 'youtube-square' => 'youtube', 'square-youtube' => 'youtube', 'pinterest-p' => 'pinterest', 'square-pinterest' => 'pinterest', 'flickr' => 'flikr', 'google-plus' => 'google', 'google-plus-g' => 'google', 'vk' => 'vk', 'xing' => 'xing', 'yelp' => 'yelp', 'meetup' => 'meetup', 'quora' => 'quora', 'amazon' => 'amazon'` (Divi spells Flickr `flikr`). Add a `DIVI_NETWORKS` constant listing every slug from `get_social_networks()`: `amazon bandcamp behance bitbucket buffer codepen deviantart dribbble facebook flikr flipboard foursquare github goodreads google houzz instagram itunes last_fm line linkedin medium meetup myspace odnoklassniki patreon periscope pinterest quora reddit researchgate rss skype snapchat soundcloud spotify steam telegram tiktok tripadvisor tumblr twitch twitter vimeo vk weibo whatsapp xing yelp youtube`.

Replace `resolveNetwork()` with one returning `?string`:

```php
    /**
     * Elementor 4.1.3 social-icons.php: `social_icon` is an ICONS value
     * {value: "fab fa-instagram", library}; `social` is the pre-FA5 string
     * ("fa fa-facebook"). Returns the Divi network slug, or null when Divi has
     * no such network (SocialMediaFollowItemModule::get_social_networks()).
     */
    private function resolveNetwork( array $item ): ?string {
        $raw = '';
        $icon = $item['social_icon'] ?? null;
        if ( is_array( $icon ) && is_string( $icon['value'] ?? null ) ) {
            $raw = $icon['value'];
        } elseif ( is_string( $icon ) ) {
            $raw = $icon;
        }
        if ( $raw === '' && is_string( $item['social'] ?? null ) ) {
            $raw = $item['social'];
        }

        if ( preg_match( '/fa-([a-z0-9_-]+)/i', $raw, $m ) ) {
            $slug = strtolower( $m[1] );
        } else {
            $slug = strtolower( trim( preg_replace( '/^(fab?|fas?|far)\s+/i', '', $raw ), " \t\n-_" ) );
        }
        if ( $slug === '' ) {
            return null;
        }
        $network = self::NETWORK_MAP[ $slug ] ?? $slug;

        return in_array( $network, self::DIVI_NETWORKS, true ) ? $network : null;
    }
```

In the loop, replace `$network = $this->resolveNetwork( $item );` with:

```php
            $network = $this->resolveNetwork( $item );
            if ( $network === null ) {
                $raw = $item['social_icon']['value'] ?? $item['social'] ?? 'unknown icon';
                $this->engine->logNotCarriedOver( 'social_network', (string) $id, 'no Divi network for ' . ( is_string( $raw ) ? $raw : 'this icon' ) );
                continue;
            }
```

Add `'social_network' => __( 'Social networks Divi does not offer — dropped', 'jhmg-converter-for-elementor-to-divi' ),` to `NotCarriedOverRenderer::groups()`.

In `class-hfe-navigation-menu-converter.php`, before `$this->engine->logConverted( 'menu' );`:

```php
        // Divi paints #ffffff behind the menu by default
        // (menu/module-default-render-attributes.json), a white bar over the
        // header's background. HFE's menu has no bar background of its own; its
        // per-item bg_color_menu_item (navigation-menu.php) is the nearest thing.
        $item_bg = $settings['bg_color_menu_item'] ?? '';
        $block_settings['module']['decoration']['background']['desktop']['value']['color'] =
            is_string( $item_bg ) && $item_bg !== '' ? $item_bg : 'rgba(255,255,255,0)';
```

and add `'bg_color_menu_item'` to the handled list.

Run: `vendor/bin/phpunit && vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 3: Render assertions**

Probe describe:

```ts
  test('social-icons: Instagram and LinkedIn, nothing else', async ({ page }) => {
    const items = page.locator('.et_pb_social_media_follow li');
    await expect(items).toHaveCount(2);
    await expect(items.nth(0)).toHaveClass(/et-social-instagram/);
    await expect(items.nth(1)).toHaveClass(/et-social-linkedin/);
  });
```

Header describe:

```ts
  test('navigation-menu (HFE): no white bar behind the menu', async ({ page }) => {
    await page.goto(draft('home'));
    const menu = page.locator('.et_pb_menu').first();
    await expect(menu).toBeVisible();
    expect(await css(menu, 'background-color')).toBe('rgba(0, 0, 0, 0)');
  });
```

Footer (same describe, the footer is on every draft):

```ts
  test('footer social-icons (HFE footer): Instagram and LinkedIn', async ({ page }) => {
    await page.goto(draft('home'));
    const items = page.locator('footer .et_pb_social_media_follow li, .et-l--footer .et_pb_social_media_follow li');
    await expect(items).toHaveCount(2);
    await expect(items.nth(0)).toHaveClass(/et-social-instagram/);
  });
```

The header and footer layouts must be rebuilt for these to pass: `demo/wp theme activate Divi && demo/wp --user=admin eval-file /demo/lib/theme-builder.php && demo/wp theme activate hello-elementor && demo/snapshot.sh`, then `demo/verify.sh render && demo/verify.sh reset`.

- [ ] **Step 4: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes tests/SocialIconsConversionTest.php tests/AddonSettingNamesTest.php demo/tests/render.spec.ts
git commit -m "fix(converter): social icons keep their network; HFE menus lose Divi's white bar

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 11: Info box and flip box render as blurbs; FontAwesome icons reach Divi

**Files:**
- Create: `includes/helpers/class-font-awesome-icons.php`
- Modify: `handlers/class-eael-info-box-converter.php`, `handlers/class-eael-flip-box-converter.php`, `handlers/class-icon-box-converter.php`, `handlers/class-icon-converter.php`, `fixtures/divi/icon.json`, `fixtures/divi/image-box.json` (regenerated if they change), `tests/support/divi-schema-known-gaps.php`, `demo/tests/render.spec.ts`
- Test: `tests/FontAwesomeIconsTest.php`, `tests/AddonSettingNamesTest.php`

**Interfaces:**
- Produces: `ElementorDivi5Converter\Helpers\FontAwesomeIcons::diviIcon( string $class ): ?array` — `"fab fa-instagram"` → `['type' => 'fa', 'unicode' => '&#xe09a;'…]`; precisely: the entry from `includes/data/fa-icons.php` for the name after `fa-`, weight by prefix (`fas`/`fa` → solid weight, `far`/`fal` → line weight, `fab` → whatever the entry has), or null when the name is unknown.
- Produces: `FontAwesomeIcons::fromControl( mixed $control ): ?array` — accepts Elementor's ICONS value `{value, library}` or a class string; returns null for `svg` library or unknown icons.

- [ ] **Step 1: Failing tests**

Create `tests/FontAwesomeIconsTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

/**
 * Divi 5 renders an icon from {type, unicode, weight} (IconModule.php:344-350:
 * type fa → FontAwesome font). includes/data/fa-icons.php is generated from Divi's
 * own icon list, so every mapped icon exists in the font Divi ships.
 */
final class FontAwesomeIconsTest extends TestCase {
    public function test_brand_icon(): void {
        $this->assertSame( [ 'type' => 'fa', 'unicode' => '&#xf39e;', 'weight' => '400' ], FontAwesomeIcons::diviIcon( 'fab fa-facebook-f' ) );
    }

    public function test_solid_and_regular_weights(): void {
        $this->assertSame( '900', FontAwesomeIcons::diviIcon( 'fas fa-clock' )['weight'] );
        $this->assertSame( '400', FontAwesomeIcons::diviIcon( 'far fa-clock' )['weight'] );
        $this->assertSame( '&#xf017;', FontAwesomeIcons::diviIcon( 'far fa-clock' )['unicode'] );
    }

    public function test_control_value_and_unknowns(): void {
        $this->assertSame( '&#xf0e0;', FontAwesomeIcons::fromControl( [ 'value' => 'fas fa-envelope', 'library' => 'fa-solid' ] )['unicode'] );
        $this->assertNull( FontAwesomeIcons::fromControl( [ 'value' => [ 'id' => 5, 'url' => 'x.svg' ], 'library' => 'svg' ] ) );
        $this->assertNull( FontAwesomeIcons::diviIcon( 'fas fa-no-such-icon' ) );
        $this->assertNull( FontAwesomeIcons::diviIcon( '' ) );
    }
}
```

Add to `tests/AddonSettingNamesTest.php` (EAEL section):

```php
    public function test_info_box_becomes_a_blurb_divi_renders(): void {
        [ $block, $result ] = $this->convert( 'eael-info-box', [
            'eael_infobox_img_or_icon' => 'icon',
            'eael_infobox_icon_new'    => [ 'value' => 'fas fa-wifi', 'library' => 'fa-solid' ],
            'eael_infobox_title'       => 'Fast wifi',
            'eael_infobox_text'        => '<p>Gigabit fibre.</p>',
        ] );

        $this->assertSame( 'divi/blurb', $block['name'] );
        // BlurbModule.php: title is a headingLink ({text}), body is content.innerContent,
        // the icon is imageIcon.innerContent {useIcon, icon{type,unicode,weight}}.
        $this->assertSame( [ 'text' => 'Fast wifi' ], $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Gigabit fibre.</p>', $block['settings']['content']['innerContent']['desktop']['value'] );
        $icon = $block['settings']['imageIcon']['innerContent']['desktop']['value'];
        $this->assertSame( 'on', $icon['useIcon'] );
        $this->assertSame( [ 'type' => 'fa', 'unicode' => '&#xf1eb;', 'weight' => '900' ], $icon['icon'] );
        $this->assertArrayNotHasKey( 'module', $block['settings'] );
        $this->assertSame( [], $result['report']['warnings'] );
    }

    public function test_info_box_with_an_image_uses_src(): void {
        [ $block ] = $this->convert( 'eael-info-box', [
            'eael_infobox_img_or_icon' => 'img',
            'eael_infobox_image'       => [ 'url' => 'https://x.test/i.jpg', 'id' => 3 ],
            'eael_infobox_title'       => 'T',
        ] );
        $this->assertSame( [ 'src' => 'https://x.test/i.jpg' ], $block['settings']['imageIcon']['innerContent']['desktop']['value'] );
    }

    public function test_info_box_unknown_icon_falls_back_to_a_star_with_a_warning(): void {
        [ $block, $result ] = $this->convert( 'eael-info-box', [
            'eael_infobox_img_or_icon' => 'icon',
            'eael_infobox_icon_new'    => [ 'value' => 'fas fa-no-such-icon', 'library' => 'fa-solid' ],
            'eael_infobox_title'       => 'T',
        ] );
        $this->assertSame( '&#xf005;', $block['settings']['imageIcon']['innerContent']['desktop']['value']['icon']['unicode'] );
        $this->assertStringContainsString( 'no-such-icon', $result['report']['warnings'][0] );
    }

    public function test_flip_box_becomes_a_blurb_with_front_and_back_text(): void {
        [ $block ] = $this->convert( 'eael-flip-box', [
            'eael_flipbox_img_or_icon' => 'icon',
            'eael_flipbox_icon_new'    => [ 'value' => 'fas fa-door-open', 'library' => 'fa-solid' ],
            'eael_flipbox_front_title' => 'Private offices',
            'eael_flipbox_front_text'  => 'Two to twelve desks.',
            'eael_flipbox_back_title'  => 'Private offices',
            'eael_flipbox_back_text'   => 'From $900 a month.',
        ] );
        $this->assertSame( [ 'text' => 'Private offices' ], $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'Two to twelve desks.', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'From $900 a month.', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['imageIcon']['innerContent']['desktop']['value']['useIcon'] );
    }

    public function test_icon_widget_emits_an_icon_object(): void {
        [ $block ] = $this->convert( 'icon', [ 'selected_icon' => [ 'value' => 'fas fa-star', 'library' => 'fa-solid' ] ] );
        $this->assertSame( [ 'type' => 'fa', 'unicode' => '&#xf005;', 'weight' => '900' ], $block['settings']['icon']['innerContent']['desktop']['value'] );
    }

    public function test_icon_box_maps_its_icon_instead_of_always_a_star(): void {
        [ $block ] = $this->convert( 'icon-box', [ 'selected_icon' => [ 'value' => 'fas fa-wifi', 'library' => 'fa-solid' ], 'title_text' => 'T' ] );
        $this->assertSame( '&#xf1eb;', $block['settings']['imageIcon']['innerContent']['desktop']['value']['icon']['unicode'] );
    }
```

Run: `vendor/bin/phpunit tests/FontAwesomeIconsTest.php tests/AddonSettingNamesTest.php` → FAIL.

- [ ] **Step 2: The helper**

Create `includes/helpers/class-font-awesome-icons.php`:

```php
<?php

namespace ElementorDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor icons are FontAwesome classes ("fas fa-clock"); Divi 5 wants
 * {type, unicode, weight} (server IconModule.php:344-350: type "fa" selects the
 * FontAwesome font Divi ships). includes/data/fa-icons.php is generated from
 * Divi's own icon list by scripts/divi-module-schema.php.
 */
final class FontAwesomeIcons {
    private static ?array $icons = null;

    /** Divi's default icon (a solid star), for the cases nothing maps. */
    public const STAR = [ 'type' => 'fa', 'unicode' => '&#xf005;', 'weight' => '900' ];

    /** @param mixed $control An Elementor ICONS value {value, library} or a class string. */
    public static function fromControl( mixed $control ): ?array {
        if ( is_array( $control ) ) {
            if ( ( $control['library'] ?? '' ) === 'svg' || ! is_string( $control['value'] ?? null ) ) {
                return null;
            }
            return self::diviIcon( $control['value'] );
        }
        return is_string( $control ) ? self::diviIcon( $control ) : null;
    }

    public static function diviIcon( string $class ): ?array {
        if ( ! preg_match( '/(?:^|\s)fa-([a-z0-9-]+)/i', $class, $m ) ) {
            return null;
        }
        $entry = self::icons()[ strtolower( $m[1] ) ] ?? null;
        if ( $entry === null ) {
            return null;
        }

        $prefix = preg_match( '/^\s*(fab|fas|far|fal|fad|fa)\b/i', $class, $p ) ? strtolower( $p[1] ) : 'fas';
        $weight = match ( $prefix ) {
            'far', 'fal' => $entry['line'] ?? $entry['solid'] ?? '400',
            default      => $entry['solid'] ?? $entry['line'] ?? '400',
        };

        return [ 'type' => 'fa', 'unicode' => $entry['unicode'], 'weight' => (string) $weight ];
    }

    private static function icons(): array {
        if ( self::$icons === null ) {
            self::$icons = require dirname( __DIR__ ) . '/data/fa-icons.php';
        }
        return self::$icons;
    }
}
```

- [ ] **Step 3: The converters**

Rewrite `EaelInfoBoxConverter::convert()`'s block-building part (keep the setting reads, add `use ElementorDivi5Converter\Helpers\FontAwesomeIcons;`):

```php
        // divi/blurb (blurb/module.json, BlurbModule.php): title is a
        // headingLink whose value is {text}; the body is content.innerContent;
        // the picture is imageIcon.innerContent {useIcon, icon, src}. Writing
        // module.advanced.text and a FontAwesome class rendered nothing at all.
        $block_settings = [];
        if ( $full_title !== '' ) {
            $block_settings['title']['innerContent']['desktop']['value'] = [ 'text' => $full_title ];
        }
        if ( $description !== '' ) {
            $block_settings['content']['innerContent']['desktop']['value'] = $description;
        }

        $mode = $settings['eael_infobox_img_or_icon'] ?? 'icon'; // EAEL 6.6.7 Info_Box.php: icon | img | number
        if ( $mode === 'img' && $image_url !== '' ) {
            $block_settings['imageIcon']['innerContent']['desktop']['value'] = [ 'src' => $image_url ];
        } elseif ( $mode === 'icon' && is_array( $icon_raw ) && ( $icon_raw['value'] ?? '' ) !== '' ) {
            $divi_icon = FontAwesomeIcons::fromControl( $icon_raw );
            if ( $divi_icon === null ) {
                $this->engine->logWarning( "Info box {$id}: icon '" . ( is_string( $icon_raw['value'] ) ? $icon_raw['value'] : 'svg' ) . "' has no FontAwesome equivalent in Divi; a star was used." );
                $divi_icon = FontAwesomeIcons::STAR;
            }
            $block_settings['imageIcon']['innerContent']['desktop']['value'] = [ 'useIcon' => 'on', 'icon' => $divi_icon ];
        }
```

(delete the old `$icon = …` extraction and the `module.advanced.text` block; `$icon_raw` is the `eael_infobox_icon_new` array, `$image_url` from `eael_infobox_image`).

Apply the same shape in `EaelFlipBoxConverter` (title `{text}` = front title, body = front text + back title/text as today's `$body`, icon from `eael_flipbox_icon_new` when `eael_flipbox_img_or_icon` is `icon` (default), image `eael_flipbox_image` when `img`), with the warning text "Flip box {$id}: …".

In `IconBoxConverter`, replace the fixed star literal with `FontAwesomeIcons::fromControl( $settings['selected_icon'] ?? $settings['icon'] ?? null ) ?? FontAwesomeIcons::STAR` and drop the "Always fall back to a default Divi star" comment; warn as above when it fell back and an icon was set.

In `IconConverter`, replace `'innerContent' => [ 'desktop' => [ 'value' => $icon_value ] ]` with the icon object: `FontAwesomeIcons::diviIcon( $icon_value ) ?? FontAwesomeIcons::STAR` (warn when the class was set and did not map).

Remove the `divi/blurb …` and `divi/icon icon.innerContent` known gaps.

Run: `vendor/bin/phpunit`. Regenerate `fixtures/divi/icon.json` and `fixtures/divi/image-box.json` if they changed (icon object instead of a string), review the diffs. `vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 4: Render assertions**

Add a Home describe:

```ts
test.describe('home', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('home'));
    await settle(page);
  });

  test('info boxes (EAEL): title, body and icon render', async ({ page }) => {
    const blurbs = page.locator('.et_pb_blurb');
    expect(await blurbs.count()).toBeGreaterThanOrEqual(3);
    await expect(blurbs.first().locator('.et_pb_module_header')).not.toBeEmpty();
    await expect(blurbs.first().locator('.et_pb_blurb_description')).not.toBeEmpty();
    await expect(blurbs.first().locator('.et_pb_main_blurb_image .et-pb-icon')).toBeVisible();
  });
});
```

and to the Spaces describe:

```ts
  test('flip boxes (EAEL): titles and text render as blurbs', async ({ page }) => {
    await page.goto(draft('spaces'));
    await settle(page);
    const blurbs = page.locator('.et_pb_blurb');
    expect(await blurbs.count()).toBeGreaterThanOrEqual(3);
    await expect(blurbs.first().locator('.et_pb_module_header')).not.toBeEmpty();
  });
```

Run: `demo/verify.sh render && demo/verify.sh reset` → passes.

- [ ] **Step 5: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes tests/FontAwesomeIconsTest.php tests/AddonSettingNamesTest.php tests/support/divi-schema-known-gaps.php fixtures/divi demo/tests/render.spec.ts
git commit -m "fix(converter): info boxes, flip boxes and icons render; FontAwesome icons map to Divi's

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 12: Pricing tables show title, subtitle, price, features and button

**Files:**
- Modify: `handlers/class-eael-pricing-table-converter.php`, `tests/support/divi-schema-known-gaps.php`, `demo/content/pages/memberships.php`, `demo/tests/render.spec.ts`
- Test: `tests/AddonSettingNamesTest.php`

- [ ] **Step 1: Failing test**

Add to `tests/AddonSettingNamesTest.php`:

```php
    public function test_pricing_table_writes_the_attributes_divi_reads(): void {
        [ $block ] = $this->convert( 'eael-pricing-table', [
            'eael_pricing_table_title'        => 'Day Pass',
            'eael_pricing_table_sub_title'    => 'Try us out',
            'eael_pricing_table_price'        => '29',
            'eael_pricing_table_price_cur'    => '$',
            'eael_pricing_table_price_period' => 'day',
            'eael_pricing_table_items'        => [
                [ 'eael_pricing_table_item' => 'Any open desk' ],
                [ 'eael_pricing_table_item' => 'Meeting rooms', 'eael_pricing_table_icon_mood' => 'no' ],
            ],
            'eael_pricing_table_btn'          => 'Book a day',
            'eael_pricing_table_btn_link'     => [ 'url' => 'https://x.test/contact/' ],
            'eael_pricing_table_featured'     => 'yes',
        ] );

        $this->assertSame( 'divi/pricing-tables', $block['name'] );
        $table = $block['elements'][0]['settings'];
        // PricingTablesItemModule.php: title, subtitle, currencyFrequency{currency,per},
        // price, content (one feature per line, "-" = excluded), button{text,linkUrl}, featured.
        $this->assertSame( 'Day Pass', $table['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Try us out', $table['subtitle']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'currency' => '$', 'per' => 'day' ], $table['currencyFrequency']['innerContent']['desktop']['value'] );
        $this->assertSame( '29', $table['price']['innerContent']['desktop']['value'] );
        $this->assertSame( "Any open desk\n-Meeting rooms", $table['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'text' => 'Book a day', 'linkUrl' => 'https://x.test/contact/' ], $table['button']['innerContent']['desktop']['value'] );
        $this->assertSame( 'on', $table['module']['advanced']['featured']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'title', $table['module']['advanced'] );
    }
```

Run → FAIL.

- [ ] **Step 2: Fix**

Replace the `$child_settings` construction in `EaelPricingTableConverter::convert()`:

```php
        $sub_title = is_string( $settings['eael_pricing_table_sub_title'] ?? '' ) ? ( $settings['eael_pricing_table_sub_title'] ?? '' ) : '';

        // divi/pricing-table (pricing-table/module.json, PricingTablesItemModule.php):
        // every text is its own attribute's innerContent; the feature list is one
        // line per item, a leading "-" marking an excluded item
        // (render_pricing_list); featured is module.advanced.featured.
        $features = [];
        foreach ( is_array( $settings['eael_pricing_table_items'] ?? null ) ? $settings['eael_pricing_table_items'] : [] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $text = is_string( $item['eael_pricing_table_item'] ?? '' ) ? trim( $item['eael_pricing_table_item'] ?? '' ) : '';
            if ( $text === '' ) {
                continue;
            }
            // EAEL 6.6.7 Pricing_Table.php: eael_pricing_table_icon_mood "Item Active?", default yes.
            $active     = ( $item['eael_pricing_table_icon_mood'] ?? 'yes' ) === 'yes';
            $features[] = ( $active ? '' : '-' ) . $text;
        }

        $child_settings = [];
        if ( $title !== '' ) {
            $child_settings['title']['innerContent']['desktop']['value'] = $title;
        }
        if ( $sub_title !== '' ) {
            $child_settings['subtitle']['innerContent']['desktop']['value'] = $sub_title;
        }
        if ( $currency !== '' || $per !== '' ) {
            $child_settings['currencyFrequency']['innerContent']['desktop']['value'] = array_filter( [ 'currency' => $currency, 'per' => $per ], static fn( string $v ): bool => $v !== '' );
        }
        if ( $price !== '' ) {
            $child_settings['price']['innerContent']['desktop']['value'] = $price;
        }
        if ( $features !== [] ) {
            $child_settings['content']['innerContent']['desktop']['value'] = implode( "\n", $features );
        }
        if ( $btn_text !== '' || $btn_url !== '' ) {
            $child_settings['button']['innerContent']['desktop']['value'] = array_filter( [ 'text' => $btn_text, 'linkUrl' => $btn_url ], static fn( string $v ): bool => $v !== '' );
        }
        if ( ( $settings['eael_pricing_table_featured'] ?? '' ) === 'yes' ) {
            $child_settings['module']['advanced']['featured']['desktop']['value'] = 'on';
        }
```

(delete the old `$feature_items` loop and the `module.advanced.*` writes). Add `'eael_pricing_table_sub_title'` to the handled list. Remove the `divi/pricing-table module.advanced` known gap.

Run: `vendor/bin/phpunit && vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 3: Demo content and render**

In `demo/content/pages/memberships.php`, replace the header comment's second and third lines with ` * Each pricing table carries a subtitle.` and give `$plan` a subtitle: add a `string $subtitle` parameter after `$title`, write `'eael_pricing_table_sub_title' => $subtitle,` after the title, and pass a short subtitle at each call site (for example `'For the occasional day'`, `'Most popular'`, `'Your own room'` — read the three `$plan(` calls and choose wording that fits). Add the three subtitles to `'survive'`.

Add a Memberships describe:

```ts
test.describe('memberships', () => {
  test('pricing tables (EAEL): title, price, period, features, button', async ({ page }) => {
    await page.goto(draft('memberships'));
    await settle(page);
    const tables = page.locator('.et_pb_pricing_table');
    await expect(tables).toHaveCount(3);
    const first = tables.first();
    await expect(first.locator('.et_pb_pricing_title')).not.toBeEmpty();
    await expect(first.locator('.et_pb_best_value')).not.toBeEmpty();
    await expect(first.locator('.et_pb_sum')).not.toBeEmpty();
    await expect(first.locator('.et_pb_frequency')).not.toBeEmpty();
    expect(await first.locator('ul.et_pb_pricing li').count()).toBeGreaterThan(0);
    await expect(first.locator('a.et_pb_button')).toBeVisible();
  });
});
```

Run: `vendor/bin/phpunit -c demo/phpunit.xml` (the seed changed, so the offline harness first), then `demo/build.sh` is **not** needed: reseed only the page — there is no per-page reseed, so run `demo/build.sh` in the background (about 10 minutes) once Tasks 12–15's content changes are all made; until then run the render check against the current site and expect the subtitle line (`.et_pb_best_value`) to be empty. Mark the subtitle assertion `test.fixme` until the rebuild in Task 15, then un-fixme it there.

- [ ] **Step 4: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-pricing-table-converter.php tests/AddonSettingNamesTest.php tests/support/divi-schema-known-gaps.php demo/content/pages/memberships.php demo/tests/render.spec.ts
git commit -m "fix(converter): pricing tables write the attributes Divi's pricing table reads

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 13: Team members and testimonials render whole

**Files:**
- Modify: `handlers/class-eael-team-member-converter.php`, `handlers/class-eael-testimonial-converter.php`, `tests/support/divi-schema-known-gaps.php`, `demo/tests/render.spec.ts`
- Test: `tests/AddonSettingNamesTest.php`

- [ ] **Step 1: Failing tests**

```php
    public function test_team_member_writes_position_description_image_and_social_links(): void {
        [ $block, $result ] = $this->convert( 'eael-team-member', [
            'eael_team_member_image'                  => [ 'url' => 'https://x.test/h.jpg', 'id' => 9, 'alt' => 'Hannah' ],
            'eael_team_member_name'                   => 'Hannah Moore',
            'eael_team_member_job_title'              => 'Founder',
            'eael_team_member_description'            => 'Ran a design studio.',
            'eael_team_member_enable_social_profiles' => 'yes',
            'eael_team_member_social_profile_links'   => [
                [ 'social_new' => [ 'value' => 'fab fa-linkedin', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.linkedin.com/in/h' ] ],
                [ 'social_new' => [ 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.instagram.com/h' ] ],
            ],
        ] );

        $s = $block['settings'];
        // TeamMemberModule.php: name, position, content innerContent; image.innerContent.url (line 98);
        // social.innerContent {facebookUrl, twitterUrl, googleUrl, linkedinUrl} (line 775).
        $this->assertSame( 'Hannah Moore', $s['name']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Founder', $s['position']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Ran a design studio.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'url' => 'https://x.test/h.jpg', 'alt' => 'Hannah' ], $s['image']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'linkedinUrl' => 'https://www.linkedin.com/in/h' ], $s['social']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'module', $s );
        $this->assertSame( 'social_network', $result['report']['not_carried_over'][0]['kind'] );
    }

    public function test_testimonial_writes_author_job_title_and_portrait(): void {
        [ $block ] = $this->convert( 'eael-testimonial', [
            'image'                          => [ 'url' => 'https://x.test/p.jpg', 'id' => 4 ],
            'eael_testimonial_name'          => 'Priya Raman',
            'eael_testimonial_company_title' => 'Brand designer',
            'eael_testimonial_description'   => 'The quiet rooms alone paid for it.',
        ] );

        $s = $block['settings'];
        // TestimonialModule.php: content, author, jobTitle innerContent; portrait.innerContent.src (line 98).
        $this->assertSame( 'The quiet rooms alone paid for it.', $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Priya Raman', $s['author']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Brand designer', $s['jobTitle']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'src' => 'https://x.test/p.jpg' ], $s['portrait']['innerContent']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'company', $s );
        $this->assertArrayNotHasKey( 'module', $s );
    }
```

Run → FAIL.

- [ ] **Step 2: Fix**

`EaelTeamMemberConverter`: replace the `$block_settings` construction with:

```php
        // divi/team-member (team-member/module.json, TeamMemberModule.php): name,
        // position and content are each an innerContent; the photo is
        // image.innerContent {url, alt} (line 98 reads url); social links are
        // social.innerContent {facebookUrl, twitterUrl, googleUrl, linkedinUrl}.
        $block_settings = [];
        if ( $name !== '' ) {
            $block_settings['name']['innerContent']['desktop']['value'] = $name;
        }
        if ( $position !== '' ) {
            $block_settings['position']['innerContent']['desktop']['value'] = $position;
        }
        if ( $description !== '' ) {
            $block_settings['content']['innerContent']['desktop']['value'] = $description;
        }
        if ( $image_url !== '' ) {
            $image = [ 'url' => $image_url ];
            if ( is_array( $image_raw ) && is_string( $image_raw['alt'] ?? null ) && $image_raw['alt'] !== '' ) {
                $image['alt'] = $image_raw['alt'];
            }
            $block_settings['image']['innerContent']['desktop']['value'] = $image;
        }

        // EAEL 6.6.7 Team_Member.php: eael_team_member_social_profile_links repeater of
        // {social_new: ICONS value, link}. Divi offers four networks; the rest are reported.
        $social = [];
        foreach ( is_array( $settings['eael_team_member_social_profile_links'] ?? null ) ? $settings['eael_team_member_social_profile_links'] : [] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $class = is_array( $item['social_new'] ?? null ) ? ( $item['social_new']['value'] ?? '' ) : ( $item['social'] ?? '' );
            $url   = is_array( $item['link'] ?? null ) ? ( $item['link']['url'] ?? '' ) : '';
            if ( ! is_string( $class ) || ! is_string( $url ) || $url === '' ) {
                continue;
            }
            $network = null;
            foreach ( [ 'facebook', 'twitter', 'linkedin', 'google' ] as $candidate ) {
                if ( str_contains( $class, 'fa-' . $candidate ) ) {
                    $network = $candidate;
                    break;
                }
            }
            if ( $network === null ) {
                $this->engine->logNotCarriedOver( 'social_network', (string) $id, "team member link '{$class}': Divi's team member offers Facebook, Twitter, Google and LinkedIn" );
                continue;
            }
            $social[ "{$network}Url" ] = $url;
        }
        if ( $social !== [] ) {
            $block_settings['social']['innerContent']['desktop']['value'] = $social;
        }
```

and add `'eael_team_member_enable_social_profiles', 'eael_team_member_social_profile_links'` to the handled list.

`EaelTestimonialConverter`: replace the `$block_settings` construction with:

```php
        // divi/testimonial (testimonial/module.json, TestimonialModule.php):
        // content, author and jobTitle are each an innerContent; the photo is
        // portrait.innerContent {src} (line 98). EAEL's "company / position"
        // field is a job title; Divi's company is a separate linked field.
        $block_settings = [];
        if ( $content !== '' ) {
            $block_settings['content']['innerContent']['desktop']['value'] = $content;
        }
        if ( $name !== '' ) {
            $block_settings['author']['innerContent']['desktop']['value'] = $name;
        }
        if ( $company !== '' ) {
            $block_settings['jobTitle']['innerContent']['desktop']['value'] = $company;
        }
        if ( $image_url !== '' ) {
            $block_settings['portrait']['innerContent']['desktop']['value'] = [ 'src' => $image_url ];
        }
```

Remove the four team-member/testimonial known gaps. Run: `vendor/bin/phpunit && vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 3: Render**

Home describe:

```ts
  test('testimonials (EAEL): portrait, author and position', async ({ page }) => {
    const first = page.locator('.et_pb_testimonial').first();
    await expect(first.locator('.et_pb_testimonial_author')).toHaveText('Priya Raman');
    await expect(first.locator('.et_pb_testimonial_position')).toHaveText('Brand designer');
    await expect(first.locator('.et_pb_testimonial_portrait')).toBeVisible();
    expect(await css(first.locator('.et_pb_testimonial_portrait'), 'background-image')).toContain('member-1');
  });
```

About describe:

```ts
test.describe('about', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(draft('about'));
    await settle(page);
  });

  test('team members (EAEL): photo, position and description', async ({ page }) => {
    const members = page.locator('.et_pb_team_member');
    await expect(members).toHaveCount(4);
    await expect(members.first().locator('.et_pb_team_member_image img')).toBeVisible();
    await expect(members.first().locator('.et_pb_member_position')).toHaveText('Founder');
    await expect(members.first().locator('.et_pb_team_member_description_content')).toContainText('design studio');
    await expect(members.first().locator('.et_pb_linkedin_icon')).toHaveCount(1);
  });
});
```

Run: `demo/verify.sh render && demo/verify.sh reset` → passes (if Divi's portrait is an `<img>` rather than a background, switch the assertion to `locator('.et_pb_testimonial_portrait img')` after reading the rendered HTML).

- [ ] **Step 4: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-team-member-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-testimonial-converter.php tests/AddonSettingNamesTest.php tests/support/divi-schema-known-gaps.php demo/tests/render.spec.ts
git commit -m "fix(converter): team members and testimonials write the attributes Divi reads

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 14: Countdown counts to its date

**Files:**
- Modify: `handlers/class-eael-countdown-converter.php`, `tests/support/divi-schema-known-gaps.php`, `demo/content/pages/events.php` (the `survive_exact` comment), `demo/tests/render.spec.ts`
- Test: `tests/AddonSettingNamesTest.php`

- [ ] **Step 1: Failing test**

```php
    public function test_countdown_due_date_lands_in_content_advanced_date_time(): void {
        [ $block, $result ] = $this->convert( 'eael-countdown', [ 'eael_countdown_type' => 'due_date', 'eael_countdown_due_time' => '2026-10-06 18:00' ] );

        // countdown-timer/module.json + CountdownTimerModule.php:361: content.advanced.dateTime,
        // parsed with strtotime; the VB timepicker stores "Y-m-d H:i".
        $this->assertSame( '2026-10-06 18:00', $block['settings']['content']['advanced']['dateTime']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'module', $block['settings'] );
        $this->assertSame( [], $result['report']['warnings'] );
    }

    public function test_countdown_date_only_and_unparsable_dates(): void {
        [ $block ] = $this->convert( 'eael-countdown', [ 'eael_countdown_due_time' => '2026-10-06' ] );
        $this->assertSame( '2026-10-06 00:00', $block['settings']['content']['advanced']['dateTime']['desktop']['value'] );

        [ $block, $result ] = $this->convert( 'eael-countdown', [ 'eael_countdown_due_time' => 'next tuesday-ish' ] );
        $this->assertArrayNotHasKey( 'content', $block['settings'] );
        $this->assertStringContainsString( 'next tuesday-ish', $result['report']['warnings'][0] );
    }
```

- [ ] **Step 2: Fix**

Replace the `$block_settings` block in `EaelCountdownConverter::convert()`:

```php
        // divi/countdown-timer reads content.advanced.dateTime
        // (countdown-timer/module.json, CountdownTimerModule.php:361) through
        // strtotime(); its own picker stores "Y-m-d H:i". EAEL 6.6.7 Countdown.php
        // stores a DATE_TIME control the same way, so normalise rather than copy.
        $block_settings = [];
        if ( $due_date !== '' ) {
            $timestamp = strtotime( $due_date );
            if ( $timestamp === false ) {
                $this->engine->logWarning( "Countdown {$id}: could not read the due date '{$due_date}'; set it in the Divi module." );
            } else {
                $block_settings['content']['advanced']['dateTime']['desktop']['value'] = gmdate( 'Y-m-d H:i', $timestamp );
            }
        }
```

`gmdate` keeps the wall-clock value EAEL stored (strtotime on a bare date parses as UTC under the CLI default). Remove the countdown known gap. In `events.php`, the comment `// The countdown converter keeps EAEL's due date string as-is.` stays true for `Y-m-d H:i` input; leave it.

Run: `vendor/bin/phpunit && vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 3: Render**

```ts
test.describe('events', () => {
  test('countdown (EAEL): counts down to a future date', async ({ page }) => {
    await page.goto(draft('events'));
    await settle(page);
    const timer = page.locator('.et_pb_countdown_timer').first();
    const end = Number(await timer.locator('.et_pb_countdown_timer_container').getAttribute('data-end-timestamp'));
    expect(end).toBeGreaterThan(Date.now() / 1000);
    await expect(timer.locator('.days .value')).not.toHaveText('000');
  });
});
```

Run: `demo/verify.sh render && demo/verify.sh reset` → passes (adjust the `data-end-timestamp` element selector to where `CountdownTimerModule.php:467` puts it, if it is not the container).

- [ ] **Step 4: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-countdown-converter.php tests/AddonSettingNamesTest.php tests/support/divi-schema-known-gaps.php demo/tests/render.spec.ts
git commit -m "fix(converter): countdown due dates reach Divi's countdown timer

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 15: Converters that dropped content: progress bar, CTA body, ElementsKit subtitle, post grid categories

**Files:**
- Modify: `handlers/class-eael-progress-bar-converter.php`, `handlers/class-eael-cta-box-converter.php`, `handlers/class-elementskit-heading-converter.php`, `handlers/class-eael-post-grid-converter.php`, `class-not-carried-over-renderer.php`, `demo/content/pages/about.php`, `home.php`, `events.php`, `tests/support/divi-schema-known-gaps.php` (delete the file), `tests/support/DiviModuleSchema.php` (drop the gaps plumbing), `demo/tests/render.spec.ts`
- Test: `tests/AddonSettingNamesTest.php`

- [ ] **Step 1: Failing tests**

```php
    public function test_progress_bar_reads_the_slider_value(): void {
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_title' => 'Freelancers', 'progress_bar_value' => [ 'unit' => '%', 'size' => 58, 'sizes' => [] ] ] );
        $this->assertSame( '58', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
    }

    public function test_progress_bar_defaults_to_50_and_keeps_legacy_scalars_and_dynamic_values(): void {
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_title' => 'x' ] );
        $this->assertSame( '50', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_value' => '72' ] );
        $this->assertSame( '72', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
        [ $block ] = $this->convert( 'eael-progress-bar', [ 'progress_bar_value_type' => 'dynamic', 'progress_bar_value_dynamic' => '33', 'progress_bar_value' => [ 'size' => 50 ] ] );
        $this->assertSame( '33', $block['elements'][0]['settings']['barProgress']['innerContent']['desktop']['value'] );
    }

    public function test_cta_box_keeps_its_body_text_after_the_subtitle(): void {
        [ $block ] = $this->convert( 'eael-cta-box', [ 'eael_cta_title' => 'Try us', 'eael_cta_sub_title' => 'A day is free.', 'eael_cta_content' => '<p>Bring a laptop.</p>', 'eael_cta_btn_text' => 'Go' ] );
        $this->assertSame( "<p>A day is free.</p>\n<p>Bring a laptop.</p>", $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_elementskit_heading_subtitle_becomes_a_text_block_before_the_heading(): void {
        [ $first, $result ] = $this->convert( 'elementskit-heading', [ 'ekit_heading_title' => 'Built by freelancers', 'ekit_heading_title_tag' => 'h1', 'ekit_heading_sub_title_show' => 'yes', 'ekit_heading_sub_title' => 'Since 2019' ] );
        $blocks = $result['divi']['elements'];
        $this->assertSame( 'divi/text', $blocks[0]['name'] );
        $this->assertSame( '<p>Since 2019</p>', $blocks[0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'divi/heading', $blocks[1]['name'] );
    }

    public function test_post_grid_carries_its_category_filter_and_reports_other_filters(): void {
        [ $block, $result ] = $this->convert( 'eael-post-grid', [ 'post_type' => 'post', 'posts_per_page' => 3, 'category_ids' => [ '4', '7' ], 'post_tag_ids' => [ '9' ] ] );
        // blog/module.json + BlogModule.php:769: post.advanced.categories, a list of term IDs.
        $this->assertSame( [ '4', '7' ], $block['settings']['post']['advanced']['categories']['desktop']['value'] );
        $this->assertSame( 'query_filter', $result['report']['not_carried_over'][0]['kind'] );
        $this->assertStringContainsString( 'post_tag_ids', $result['report']['not_carried_over'][0]['detail'] );

        [ $block, $result ] = $this->convert( 'eael-post-grid', [ 'post_type' => 'product', 'posts_per_page' => 6 ] );
        $this->assertStringContainsString( 'product', $result['report']['not_carried_over'][0]['detail'] );
    }
```

Run → FAIL.

- [ ] **Step 2: Fix the four converters**

Progress bar — replace the `$percent` line:

```php
        // EAEL 6.6.7 Progress_Bar.php: progress_bar_value is a SLIDER {unit, size}
        // (default size 50); progress_bar_value_dynamic applies only when
        // progress_bar_value_type is "dynamic". Casting the slider array to a
        // string printed "Array".
        $type = $settings['progress_bar_value_type'] ?? 'static';
        if ( $type === 'dynamic' ) {
            $percent = (string) ( $settings['progress_bar_value_dynamic'] ?? '50' );
        } else {
            $raw     = $settings['progress_bar_value'] ?? null;
            $percent = is_array( $raw ) ? (string) ( $raw['size'] ?? '50' ) : ( is_scalar( $raw ) && (string) $raw !== '' ? (string) $raw : '50' );
        }
```

CTA box — replace the subtitle write:

```php
        // EAEL 6.6.7 Cta_Box.php renders eael_cta_sub_title and eael_cta_content;
        // the body used to be dropped.
        $body_raw = $settings['eael_cta_content'] ?? '';
        $body     = is_string( $body_raw ) && trim( wp_strip_all_tags( $body_raw ) ) !== '' ? trim( $body_raw ) : '';
        $parts    = [];
        if ( $subtitle !== '' ) {
            $parts[] = str_starts_with( trim( $subtitle ), '<' ) ? $subtitle : '<p>' . $subtitle . '</p>';
        }
        if ( $body !== '' ) {
            $parts[] = $body;
        }
        if ( $parts !== [] ) {
            $block_settings['content']['innerContent']['desktop']['value'] = implode( "\n", $parts );
        }
```

and add `'eael_cta_content'` to the handled list.

ElementsKit heading — after the `$heading_block` is built (Task 5's branch) and before the extra-title return, add:

```php
        // ElementsKit Lite 4.0.5 heading.php: the subtitle renders above the
        // title when ekit_heading_sub_title_show is "yes".
        $blocks = [];
        if ( ( $settings['ekit_heading_sub_title_show'] ?? '' ) === 'yes' && trim( wp_strip_all_tags( $sub_title ) ) !== '' ) {
            $this->engine->logConverted( 'text' );
            $blocks[] = [
                'id'       => $id . '-sub',
                'name'     => 'divi/text',
                'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => '<p>' . $sub_title . '</p>' ] ] ] ],
                'elements' => [],
            ];
        }
        $blocks[] = $heading_block;
```

then make the extra-title branch append its text block to `$blocks` and `return count( $blocks ) === 1 ? $blocks[0] : $blocks;` at the end (the engine spreads lists). Add `'ekit_heading_sub_title_show'` to `$handled`.

Post grid — replace `$block_settings` and the handled list:

```php
        // EAEL 6.6.7 Traits/Controls.php: post_type, posts_per_page and one
        // "<taxonomy>_ids" control per taxonomy of that post type. Divi's blog
        // module lists posts only and filters by category
        // (blog/module.json post.advanced.categories, BlogModule.php:769).
        $block_settings = [ 'post' => [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'perPage' => $per_page ] ] ] ] ];

        $categories = $settings['category_ids'] ?? [];
        if ( is_array( $categories ) && $categories !== [] ) {
            $block_settings['post']['advanced']['categories']['desktop']['value'] = array_values( array_map( 'strval', $categories ) );
        }

        $handled = [ 'posts_per_page', 'post_type', 'category_ids', 'layout_mode', 'eael_post_grid_preset_style', 'eael_show_title', 'title_tag', 'eael_title_length', 'show_load_more' ];
        if ( $post_type !== 'post' ) {
            $this->engine->logNotCarriedOver( 'query_filter', (string) $id, "post type '{$post_type}' (Divi's blog lists posts)" );
        }
        foreach ( $settings as $key => $value ) {
            if ( is_string( $key ) && str_ends_with( $key, '_ids' ) && $key !== 'category_ids' && $key !== 'posts_ids' && is_array( $value ) && $value !== [] ) {
                $handled[] = $key;
                $this->engine->logNotCarriedOver( 'query_filter', (string) $id, "{$key} (" . count( $value ) . ' terms)' );
            }
        }
```

and `$this->logUnmappedSettings( $id, $settings, $handled );`. Add `'query_filter' => __( 'Post query filters Divi\'s blog module cannot express — dropped', 'jhmg-converter-for-elementor-to-divi' ),` to the renderer.

- [ ] **Step 3: Retire the known-gaps file**

Every entry has now been removed. Delete `tests/support/divi-schema-known-gaps.php`; in `DiviModuleSchema.php` delete `knownGaps()`, `matchGap()`, `unusedKnownGaps()`, `$gaps`, and simplify `assertBlocksValid()` to report every problem. Run: `vendor/bin/phpunit && vendor/bin/phpunit -c demo/phpunit.xml` → OK, no gaps left anywhere.

- [ ] **Step 4: Demo content back to the widgets the fixes cover**

`about.php`: change the header comment to ` * About: the story, the team, who works here, a member testimonial, and the space tour video.` (drop the two workaround sentences); replace the `$share` closure with:

```php
    $share = static fn ( int $percent, string $who ): array => col( [
        widget( 'eael-progress-bar', [
            'progress_bar_title' => $who,
            'progress_bar_value' => slider( $percent, '%' ),
        ] ),
    ], 30 );
```

(add `slider` to the `use function` list) and set the ElementsKit heading to `'ekit_heading_sub_title_show' => 'yes', 'ekit_heading_sub_title' => 'Our story',` adding `'Our story'` to `survive` and `58`, `31`, `11` to `survive_exact` (they are the bar values).

`home.php`: delete the two workaround comment lines above the CTA, set `'eael_cta_sub_title' => 'Your first day pass is on us.'` and `'eael_cta_content' => '<p>Bring your laptop and see if it fits.</p>'`, and keep both sentences in `survive`.

`events.php`: replace the header comment's last two sentences with ` * The post grid shows the "events" category.` and add `'category_ids' => [ (string) $ctx->categoryId( 'events' ) ],` to the post grid widget.

Run: `vendor/bin/phpunit -c demo/phpunit.xml` → OK. Then rebuild the site so the seed matches: `demo/build.sh > demo/output/build.log 2>&1 &` (about 10 minutes; `tail -f demo/output/build.log`). When it finishes, remove the `test.fixme` from Task 12's subtitle assertion, add:

```ts
  test('progress bars (EAEL): the percentage renders', async ({ page }) => {
    const bars = page.locator('.et_pb_counter_amount');
    await expect(bars).toHaveCount(3);
    await expect(bars.first()).toHaveAttribute('data-width', '58%');
  });
```

to the About describe (check the attribute Divi's bar counter uses on the draft and adjust), and to the Events describe:

```ts
  test('post grid (EAEL): only the events category', async ({ page }) => {
    const posts = page.locator('.et_pb_blog_grid .et_pb_post');
    expect(await posts.count()).toBeGreaterThan(0);
    for (const post of await posts.all()) {
      await expect(post.locator('.post-meta')).toContainText('Events');
    }
  });
```

Run: `demo/verify.sh` (all six checks) → `All checks passed`.

- [ ] **Step 5: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes tests demo/content demo/tests/render.spec.ts
git commit -m "fix(converter): progress bar value, CTA body, ElementsKit subtitle and post grid categories survive

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 16: Pro keeps one default Theme Builder template with the page body enabled

**Files:**
- Modify: `plugin/jhmg-converter-for-elementor-to-divi-pro/includes/exporters/class-divi-theme-builder-exporter.php`, `demo/lib/theme-builder.php`
- Test: `tests/ThemeBuilderDedupeTest.php`

- [ ] **Step 1: Failing tests**

Replace `test_a_header_and_a_footer_do_not_collide()` with:

```php
    /**
     * Divi applies one et_template per request and treats an area with no
     * `_et_<area>_layout_id` and no `_et_<area>_layout_enabled` as "override and
     * hide" (theme-builder.php et_theme_builder_get_template()). Two default
     * templates, each carrying only its own area, therefore hid the footer or
     * the header — and the page body — on a real site (docs/known-issues.md).
     */
    public function test_a_header_and_a_footer_share_one_default_template_with_the_body_enabled(): void {
        $header = $this->exporter()->saveHeader( 'Site chrome', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );
        $footer = $this->exporter()->saveFooter( 'Site chrome', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 8 ] );

        $this->assertCount( 1, $this->idsOfType( 'et_header_layout' ) );
        $this->assertCount( 1, $this->idsOfType( 'et_footer_layout' ) );
        $this->assertCount( 1, $this->idsOfType( 'et_template' ), 'one default template carries both areas' );
        $this->assertSame( $header['template_id'], $footer['template_id'] );

        $template = (int) $header['template_id'];
        $this->assertSame( $header['post_id'], (int) get_post_meta( $template, '_et_header_layout_id', true ) );
        $this->assertSame( $footer['post_id'], (int) get_post_meta( $template, '_et_footer_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_header_layout_enabled', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_footer_layout_enabled', true ) );
        $this->assertSame( 0, (int) get_post_meta( $template, '_et_body_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_body_layout_enabled', true ) );
    }

    public function test_a_header_alone_leaves_the_footer_and_body_to_the_theme(): void {
        $header   = $this->exporter()->saveHeader( 'Header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );
        $template = (int) $header['template_id'];

        $this->assertSame( 0, (int) get_post_meta( $template, '_et_footer_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_footer_layout_enabled', true ) );
        $this->assertSame( 0, (int) get_post_meta( $template, '_et_body_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_body_layout_enabled', true ) );
    }

    public function test_reimporting_the_header_keeps_the_footer_on_the_template(): void {
        $this->exporter()->saveHeader( 'Header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );
        $footer = $this->exporter()->saveFooter( 'Footer', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 8 ] );
        $header = $this->exporter()->saveHeader( 'Header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );

        $this->assertSame( $footer['post_id'], (int) get_post_meta( (int) $header['template_id'], '_et_footer_layout_id', true ) );
        $this->assertSame( 1, $this->defaultTemplateCount() );
    }
```

Check the other tests in the file: `test_two_different_headers_remain_two_layouts` asserts two `et_template` posts — with one default template, a second header replaces the first header's layout on the same template; change that assertion to `assertCount( 1, $this->idsOfType( 'et_template' ) )` and assert the template's `_et_header_layout_id` is the second header's post ID (the site has one global header; the older layout post stays in the library). Run: `vendor/bin/phpunit tests/ThemeBuilderDedupeTest.php` → FAIL.

- [ ] **Step 2: One default template**

In `DiviThemeBuilderExporter`, replace `upsertTemplatePost()` with:

```php
    /**
     * The site's one default template. Divi applies a single et_template per
     * request, so the header and footer must share it, and every area it does
     * not set must be told to fall through to the theme (layout id 0, enabled)
     * — a missing pair reads as "override and hide" in
     * et_theme_builder_get_template(). The template is keyed by its own source
     * so re-imports find it; a default template made in Divi's UI is reused too.
     *
     * @param string $slot 'header' or 'footer'.
     */
    private function upsertTemplatePost( string $title, string $source_key, string $slot, int $layout_id ): int {
        $template_id = $this->findBySourceKey( 'et_template', self::DEFAULT_TEMPLATE_KEY );
        if ( $template_id === 0 ) {
            $template_id = $this->findLiveDefaultTemplate();
        }

        if ( $template_id === 0 ) {
            $template_id = wp_insert_post( [
                'post_type'   => 'et_template',
                'post_title'  => 'Default Website Template',
                'post_status' => 'publish',
            ] );
            if ( is_wp_error( $template_id ) || (int) $template_id === 0 ) {
                return 0;
            }
            $template_id = (int) $template_id;
        }

        update_post_meta( $template_id, self::SOURCE_META, self::DEFAULT_TEMPLATE_KEY );
        update_post_meta( $template_id, '_et_default', '1' );
        update_post_meta( $template_id, '_et_enabled', '1' );
        update_post_meta( $template_id, "_et_{$slot}_layout_id", $layout_id );
        update_post_meta( $template_id, "_et_{$slot}_layout_enabled", '1' );

        foreach ( [ 'header', 'body', 'footer' ] as $area ) {
            if ( $area === $slot ) {
                continue;
            }
            if ( get_post_meta( $template_id, "_et_{$area}_layout_id", true ) === '' ) {
                update_post_meta( $template_id, "_et_{$area}_layout_id", 0 );
            }
            if ( get_post_meta( $template_id, "_et_{$area}_layout_enabled", true ) === '' ) {
                update_post_meta( $template_id, "_et_{$area}_layout_enabled", '1' );
            }
        }

        return $template_id;
    }

    /** A published default template already attached to the live Theme Builder post, or 0. */
    private function findLiveDefaultTemplate(): int {
        $theme_builder_id = $this->getOrCreateThemeBuilderPost();
        if ( $theme_builder_id === 0 ) {
            return 0;
        }
        foreach ( get_post_meta( $theme_builder_id, '_et_template', false ) as $candidate ) {
            $candidate = (int) $candidate;
            if ( $candidate > 0 && get_post_meta( $candidate, '_et_default', true ) === '1' && get_post_status( $candidate ) === 'publish' ) {
                return $candidate;
            }
        }
        return 0;
    }
```

with `const DEFAULT_TEMPLATE_KEY = 'template:default';` next to `SOURCE_META`. `get_post_status` is stubbed? Check `tests/bootstrap.php`; if absent, add `function get_post_status( $id ) { return $GLOBALS['__test_posts'][ (int) $id ]->post_status ?? false; }`. The layout-post source keys (`header:post-7`) are untouched, so re-imports still replace their own layout.

Run: `vendor/bin/phpunit` → OK.

- [ ] **Step 3: Drop the demo's manual repair**

In `demo/lib/theme-builder.php`, delete everything from the comment `// Pro 1.2.0 saves the header and the footer as two separate default templates` through the `if ( $footer_template_id > 0 … ) { … }` block, leaving the success line. Rebuild the Theme Builder on the running site and check the assertion the repair used to satisfy:

```bash
demo/wp theme activate Divi && demo/wp --user=admin eval-file /demo/lib/theme-builder.php
demo/wp post list --post_type=et_template --fields=ID,post_title --format=csv
T=$(demo/wp post list --post_type=et_template --format=ids | tr ' ' '\n' | tail -1); demo/wp post meta list "$T" --keys=_et_header_layout_id,_et_footer_layout_id,_et_body_layout_id,_et_body_layout_enabled --format=csv
demo/wp theme activate hello-elementor && demo/snapshot.sh
```

Expected: one template with both layout IDs, body id 0 and enabled 1. Then `demo/verify.sh pages` (the theme-switch spec checks the header renders and the page body still shows under Divi) → passes.

- [ ] **Step 4: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi-pro/includes/exporters/class-divi-theme-builder-exporter.php tests/ThemeBuilderDedupeTest.php tests/bootstrap.php demo/lib/theme-builder.php
git commit -m "fix(pro): header and footer share one default Theme Builder template with the body enabled

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 17: Warn when Header Footer Elementor is still active under a Divi Theme Builder header

**Files:**
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-hfe-conflict-notice.php`
- Modify: `includes/helpers/class-plugin.php` (`register_hooks`), Pro `includes/admin/class-kit-page.php` (batch result screen), `tests/bootstrap.php` (stubs if needed)
- Test: `tests/HfeConflictNoticeTest.php`

**Interfaces:**
- Produces: `ElementorDivi5Converter\Admin\HfeConflictNotice` with `init(): void` (hooks `admin_notices`), `applies(): bool`, `message(): string`, `render(): void`. `applies()` is true when `header-footer-elementor/header-footer-elementor.php` is active, `get_template()` is `Divi`, and a published `et_header_layout` or `et_footer_layout` exists.

- [ ] **Step 1: Failing test**

Create `tests/HfeConflictNoticeTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\HfeConflictNotice;

/**
 * With Divi active and a Theme Builder header in place, Header Footer Elementor's
 * remove_all_actions('wp_head') runs inside Divi's header buffer and every page
 * fatals ("Call to a member function do_action() on array", docs/known-issues.md).
 * Not our bug, but the first thing a user hits after converting a site that used HFE.
 */
final class HfeConflictNoticeTest extends TestCase {
    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
        $GLOBALS['__test_options']  = [];
    }

    private function site( bool $hfe, string $theme, bool $header ): void {
        update_option( 'active_plugins', $hfe ? [ 'header-footer-elementor/header-footer-elementor.php' ] : [] );
        update_option( 'template', $theme );
        if ( $header ) {
            $GLOBALS['__test_posts'][ 500 ] = (object) [ 'ID' => 500, 'post_type' => 'et_header_layout', 'post_status' => 'publish', 'post_title' => 'H' ];
        }
    }

    public function test_applies_only_with_hfe_divi_and_a_theme_builder_layout(): void {
        $this->site( true, 'Divi', true );
        $this->assertTrue( ( new HfeConflictNotice() )->applies() );

        $this->site( false, 'Divi', true );
        $this->assertFalse( ( new HfeConflictNotice() )->applies() );

        $this->site( true, 'hello-elementor', true );
        $this->assertFalse( ( new HfeConflictNotice() )->applies() );

        $this->site( true, 'Divi', false );
        $this->assertFalse( ( new HfeConflictNotice() )->applies() );
    }

    public function test_message_tells_the_user_what_to_do_and_why(): void {
        $message = ( new HfeConflictNotice() )->message();
        $this->assertStringContainsString( 'Deactivate Header Footer Elementor', $message );
        $this->assertStringContainsString( 'Theme Builder', $message );
    }

    public function test_renders_an_admin_notice_when_it_applies(): void {
        $this->site( true, 'Divi', true );
        ob_start();
        ( new HfeConflictNotice() )->render();
        $html = ob_get_clean();
        $this->assertStringContainsString( 'notice-warning', $html );
        $this->assertStringContainsString( 'Deactivate Header Footer Elementor', $html );
    }
}
```

`get_template()` in `tests/bootstrap.php` reads the `template` option (check line 429; if it reads something else, follow it in `site()`). If `get_posts()` in the stub does not filter by `post_type`/`post_status`, read `applies()`'s query below and use `$GLOBALS['__test_posts']` filtering that the stub supports.

Run: `vendor/bin/phpunit tests/HfeConflictNoticeTest.php` → FAIL (class missing).

- [ ] **Step 2: The notice**

Create `includes/admin/class-hfe-conflict-notice.php`:

```php
<?php

namespace ElementorDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Header Footer Elementor active + Divi active + a Divi Theme Builder header or
 * footer = every front-end page fatals: HFE's unsupported-theme layer hooks
 * get_header and calls remove_all_actions('wp_head') inside the window where
 * Divi's et_theme_builder_frontend_override_partial() has taken $wp_filter['wp_head']
 * out, so Divi restores an empty array and do_action() fails. Converter users
 * hit this the moment they import their first header with Pro.
 */
class HfeConflictNotice {
    const HFE_PLUGIN = 'header-footer-elementor/header-footer-elementor.php';

    public function init(): void {
        add_action( 'admin_notices', [ $this, 'render' ] );
    }

    public function applies(): bool {
        $active = (array) get_option( 'active_plugins', [] );
        if ( ! in_array( self::HFE_PLUGIN, $active, true ) ) {
            return false;
        }
        if ( get_template() !== 'Divi' ) {
            return false;
        }
        $layouts = get_posts( [
            'post_type'      => [ 'et_header_layout', 'et_footer_layout' ],
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );
        return ! empty( $layouts );
    }

    public function message(): string {
        return __( 'Deactivate Header Footer Elementor: with Divi active and a Divi Theme Builder header or footer in place, every page fails to load (HFE removes the wp_head actions while Divi is rendering its header). Your converted header and footer live in Divi → Theme Builder now.', 'jhmg-converter-for-elementor-to-divi' );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) || ! $this->applies() ) {
            return;
        }
        printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $this->message() ) );
    }
}
```

In `Plugin::register_hooks()`, add `( new \ElementorDivi5Converter\Admin\HfeConflictNotice() )->init();` after the `ReviewPrompt` line. In Pro's batch result screen (`class-kit-page.php`, where `template_type` results are rendered — search for `theme_builder_id` in the rendering code), append `( new \ElementorDivi5Converter\Admin\HfeConflictNotice() )->message()` as a paragraph under a successful header/footer result when `HfeConflictNotice::applies()` is true.

Run: `vendor/bin/phpunit` → OK. Check it on the demo (HFE is disabled there under Divi by the must-use plugin, so the notice should **not** show): `demo/wp theme activate Divi`, open `http://localhost:8040/wp-admin/` with Playwright or `demo/wp eval 'echo ( new ElementorDivi5Converter\Admin\HfeConflictNotice() )->applies() ? "yes" : "no";'` → `no`; then `demo/wp theme activate hello-elementor`.

- [ ] **Step 3: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes/admin/class-hfe-conflict-notice.php plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-plugin.php plugin/jhmg-converter-for-elementor-to-divi-pro/includes/admin/class-kit-page.php tests/HfeConflictNoticeTest.php tests/bootstrap.php
git commit -m "feat(admin): warn when Header Footer Elementor is active under a Divi Theme Builder header

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 18: Header Footer Elementor templates reach the Theme Builder

**Files:**
- Modify: `includes/conversion/class-installed-post-source.php`, `includes/admin/class-elementor-page-repository.php`, `includes/parsers/class-elementor-import-parser.php`, Pro `includes/admin/class-kit-page.php` (`handle_upload_kit`)
- Test: `tests/InstalledPostSourceTest.php`, `tests/ElementorPageRepositoryTest.php`, `tests/HeaderTemplateConversionTest.php`

- [ ] **Step 1: Failing tests**

`tests/InstalledPostSourceTest.php`:

```php
    public function test_hfe_templates_carry_their_slot_as_template_type(): void {
        $this->seed_elementor_post( 61, 'Site header', 'elementor-hf' );
        update_post_meta( 61, 'ehf_template_type', 'type_header' );
        $this->seed_elementor_post( 62, 'Site footer', 'elementor-hf' );
        update_post_meta( 62, 'ehf_template_type', 'type_footer' );
        $this->seed_elementor_post( 63, 'Before footer', 'elementor-hf' );
        update_post_meta( 63, 'ehf_template_type', 'type_before_footer' );

        $items = ( new InstalledPostSource( [ 61, 62, 63 ] ) )->items();

        $this->assertSame( 'header', $items[0]['template_type'] );
        $this->assertSame( 'footer', $items[1]['template_type'] );
        $this->assertSame( '', $items[2]['template_type'], 'HFE "before footer" has no Divi area' );
        $this->assertSame( 'page', $items[0]['post_type'] );
    }
```

`tests/ElementorPageRepositoryTest.php`:

```php
    public function test_it_lists_header_footer_elementor_templates(): void {
        $this->assertContains( 'elementor-hf', ( new ElementorPageRepository() )->query_args()['post_type'] );
    }
```

`tests/HeaderTemplateConversionTest.php`:

```php
    public function test_detects_hfe_post_types_in_a_zip_export(): void {
        $this->assertSame( 'header', $this->parser->templateTypeFor( 'elementor-hf', [ 'ehf_template_type' => 'type_header' ] ) );
        $this->assertSame( 'footer', $this->parser->templateTypeFor( 'elementor-hf', [ 'ehf_template_type' => 'type_footer' ] ) );
        $this->assertSame( '', $this->parser->templateTypeFor( 'elementor-hf', [] ) );
        $this->assertSame( 'header', $this->parser->templateTypeFor( 'et_header_layout', [] ) );
        $this->assertSame( '', $this->parser->templateTypeFor( 'hfe-template', [] ), 'a value HFE never used' );
    }
```

Run → FAIL.

- [ ] **Step 2: Fix**

`InstalledPostSource::itemFor()`: replace the `post_type`/`template_type` block:

```php
        $post_type     = (string) ( $post->post_type ?? 'page' );
        $template_type = '';

        if ( $post_type === 'elementor_library' ) {
            $template_type = $this->templateType( $post_id );
            $post_type     = 'page';
        } elseif ( $post_type === 'elementor-hf' ) {
            // Header Footer Elementor stores its templates as elementor-hf posts
            // with ehf_template_type type_header | type_footer | type_before_footer
            // (header-footer-elementor 2.8.8). Only the first two have a Divi area.
            $template_type = self::hfeTemplateType( (string) get_post_meta( $post_id, 'ehf_template_type', true ) );
            $post_type     = 'page';
        } elseif ( $post_type !== 'page' ) {
            $post_type = 'post';
        }
```

and add `public static function hfeTemplateType( string $ehf_type ): string { return match ( $ehf_type ) { 'type_header' => 'header', 'type_footer' => 'footer', default => '' }; }`.

`ElementorPageRepository::query_args()`: `'post_type' => [ 'page', 'post', 'elementor_library', 'elementor-hf' ]`.

`ElementorImportParser`: add `public function templateTypeFor( string $post_type, array $meta ): string` that returns `'header'` for `header`/`et_header_layout`, `'footer'` for `footer`/`et_footer_layout`, and for `elementor-hf` maps `$meta['ehf_template_type']` through `InstalledPostSource::hfeTemplateType()`; delete `isHeaderTemplateType()`/`isFooterTemplateType()` and call `templateTypeFor()` at both former call sites (the ZIP path passes the entry's meta array; the JSON path passes `$meta`). Kit ZIP exports carry `ehf_template_type` in the post's meta map next to `post_type` (check `parseZip()`'s `$meta` construction and pass the same array).

Pro `handle_upload_kit()`: after `$items = $parser->parse( … )`, force the chosen slot: `foreach ( $items as &$item ) { $item['template_type'] = $upload_type; } unset( $item );` with the comment `// The user chose the slot on the upload form; a free-Elementor "Save as template" export is typed page.`

Run: `vendor/bin/phpunit` → OK. On the demo, with Divi active, check the picker lists the two HFE templates: `demo/wp eval 'foreach ( ( new ElementorDivi5Converter\Admin\ElementorPageRepository() )->find() as $r ) echo $r["post_type"], " ", $r["title"], "\n";'` → includes `elementor-hf Ferncourt Header` and `elementor-hf Ferncourt Footer`. Update `demo/lib/theme-builder.php`'s header comment (it can now go through `ConversionCommitter`, but keep it calling the exporter directly — no change needed beyond the comment: "HFE templates are elementor-hf posts, which InstalledPostSource now understands; this script still calls the exporter directly so it can name the layouts.").

- [ ] **Step 3: Commit**

```bash
git add plugin/jhmg-converter-for-elementor-to-divi/includes plugin/jhmg-converter-for-elementor-to-divi-pro/includes/admin/class-kit-page.php tests/InstalledPostSourceTest.php tests/ElementorPageRepositoryTest.php tests/HeaderTemplateConversionTest.php demo/lib/theme-builder.php
git commit -m "feat(conversion): Header Footer Elementor templates convert as Theme Builder headers and footers

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 19: Docs, versions, kit screenshots, final proof

**Files:**
- Modify: `docs/known-issues.md`, `docs/conversion-map.md`, `plugin/jhmg-converter-for-elementor-to-divi/readme.txt`, `plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php`, `plugin/jhmg-converter-for-elementor-to-divi-pro/jhmg-converter-for-elementor-to-divi-pro.php`, `plugin/jhmg-converter-for-elementor-to-divi-pro/readme.txt` (if it has a changelog), `tests/ReleaseMetadataTest.php`, `demo/versions.env`, `demo/output/kit-screenshots/*.png`

- [ ] **Step 1: known-issues.md**

Add a section `## Fixed in free 3.0.2 and Pro 1.2.1` at the top listing each fixed item with its commit hash (`git log --oneline fix/correctness-pass-2026-08..HEAD`), and move the corresponding paragraphs under it (keep the original "why"). Items that stay open: any widget the render check still fails on, with the evidence, and the HFE/Divi crash (now warned about, not fixed).

- [ ] **Step 2: conversion-map.md**

Update the rows for `heading` (span/p/div → `divi/text`), `counter`, `image-carousel` (→ `divi/gallery`), `google_maps` (whatever Task 9 chose), `social-icons`, `eael-info-box`, `eael-flip-box`, `eael-pricing-table`, `eael-team-member`, `eael-testimonial`, `eael-countdown`, `eael-filterable-gallery`, `eael-post-grid`, `navigation-menu`, `button` (Elementor defaults + kit theme style), and the column background rule.

- [ ] **Step 3: Versions and changelog**

Free: `Version:     3.0.2`, `EDC_PLUGIN_VERSION', '3.0.2'`, `Stable tag: 3.0.2`; in `readme.txt` add under `== Changelog ==`:

```
= 3.0.2 =
* Converted pages render as designed in Divi 5.7.4: column backgrounds fill their column, oversized display headings keep their typography, buttons keep Elementor's default look and your kit's button style, counters show the right number, carousels and galleries show their own images, Google Maps embeds render, social icons keep their network.
* Essential Addons info boxes, flip boxes, pricing tables, team members, testimonials, countdowns and progress bars now render with all their content; Call to Action body text, pricing table subtitles, ElementsKit heading subtitles and post grid category filters are carried over.
* Header Footer Elementor templates can be converted as Divi Theme Builder headers and footers, and the plugin warns when HFE is still active under a Divi header.
* Every converted block is checked against Divi 5.7.4's module definitions in the test suite.
```

and under `== Upgrade Notice ==`: `= 3.0.2 =` / `Converted pages now render as designed in Divi 5.7.4. Reconvert pages whose buttons, galleries, counters or add-on widgets looked wrong.` Update `tests/ReleaseMetadataTest.php` (`3.0.1` → `3.0.2`, and the test name `test_readme_documents_3_0_2_…`). Pro: `Version: 1.2.1`, `EDCP_PLUGIN_VERSION`, and a changelog line about the single default template and HFE slot upload if its readme has a changelog. `demo/versions.env`: `EDC_FREE_VERSION=3.0.2`, `EDC_PRO_VERSION=1.2.1`.

Run: `vendor/bin/phpunit && vendor/bin/phpunit -c demo/phpunit.xml` → OK.

- [ ] **Step 4: Kit screenshots before/after and the full verify**

```bash
demo/verify.sh                # all six checks, ends with a reset
demo/wp --user=admin elementor kit import /demo/references/kits/ceramic-studio.zip
```

Then follow `demo/README.md` "Trying another kit": regenerate `demo/output/kit-pages.json` (page IDs from `demo/wp post list --post_type=page --format=json --fields=ID,post_name,url` filtered to the kit's slugs), screenshot the originals, activate Divi, commit the five pages through `ConversionCommitter` (a one-off `demo/wp eval` mirroring `commit-conversions.php` for those IDs, writing `kit-converted.json`), screenshot the drafts, and `demo/reset.sh`. Read each `kit-*-divi.png` beside its `kit-*-elementor.png` (Read tool) and confirm: hero image fills the column, "eramic"/"what we offer"/"participants' work" are large, buttons are terracotta with cream text, the four-photo carousel shows full-size images. Commit the after shots.

- [ ] **Step 5: Commit and report**

```bash
git add docs/known-issues.md docs/conversion-map.md plugin/jhmg-converter-for-elementor-to-divi/readme.txt plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php plugin/jhmg-converter-for-elementor-to-divi-pro tests/ReleaseMetadataTest.php demo/versions.env demo/output/kit-screenshots
git commit -m "release(free): 3.0.2, release(pro): 1.2.1 — converted pages render correctly in Divi 5

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

Stop. Do not merge or release: report the branch state, the verify output, and the kit screenshot comparison, and ask the user about merging into `fix/correctness-pass-2026-08` and `main` and about the wordpress.org release (`RELEASE.md`).

---

## Tasks added by the schema harvest (Task 2, Step 3)

The first schema run reported paths no task owned. Three were fixed inside Task 2 as one-line renames (Contact Form 7 `form.advanced.formId`, menu `menu.advanced.menuId` in both menu converters, custom CSS `css.*.mainElement` instead of the never-read `main`, no text alignment on dividers). These three need their own cycle.

### Task 20: Video widget writes the source Divi reads

**Files:**
- Modify: `handlers/class-video-converter.php`, `tests/support/divi-schema-known-gaps.php`, `demo/tests/render.spec.ts`
- Test: `tests/AddonSettingNamesTest.php` (the existing video cases read `videoSrc()`)

- [ ] **Step 1: Failing test** — change the `videoSrc()` helper in `tests/AddonSettingNamesTest.php` to read `$block['settings']['video']['innerContent']['desktop']['value']['src']` (video/conversion-outline.json: `src → video.innerContent.*.src`, `src_webm → video.innerContent.*.webm`, `image_src → thumbnail.innerContent.*.src`; VideoModule.php:153 reads `['src']`). Run `vendor/bin/phpunit --filter video tests/AddonSettingNamesTest.php` → FAIL.
- [ ] **Step 2: Fix** — in `VideoConverter::convert()`, write `$attrs['video']['innerContent']['desktop']['value'] = [ 'src' => $url ]` (add `'webm' => …` when Elementor's `hosted_url` is a `.webm`), the poster (`image_overlay.url` when `show_image_overlay` is `yes`) to `$attrs['thumbnail']['innerContent']['desktop']['value'] = [ 'src' => $poster ]`, and delete the `module.advanced.videoUrl` write. YouTube/Vimeo URLs go in `src` too: Divi's video module embeds oEmbed URLs (VideoModule.php, `get_video_embed`). Remove the `divi/video module.advanced.videoUrl` known gap. `vendor/bin/phpunit` → OK; regenerate `fixtures/divi/video.json` and review the diff.
- [ ] **Step 3: Render** — add `widget( 'video', [ 'video_type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ] )` to the probe band and assert `page.locator('.et_pb_video iframe, .et_pb_video video')` is visible with height > 200. `demo/verify.sh render && demo/verify.sh reset`.
- [ ] **Step 4: Commit** — `fix(converter): video widgets write the source Divi's video module reads`.

### Task 21: Image spacing and sizing live under `module.advanced` in Divi's image module

**Files:**
- Modify: `includes/stylemapper/class-style-mapper.php` (`mapSpacing`), `tests/support/divi-schema-known-gaps.php`, `fixtures/divi/image.json` (regenerated)
- Test: `tests/StyleMapperTest.php`

- [ ] **Step 1: Failing test** — add to `StyleMapperTest`: `map( 'image', [ '_margin' => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '24', 'left' => '0', 'isLinked' => '' ] ] )` must write `module.advanced.spacing.desktop.value.margin.bottom === '24px'` and no `module.decoration.spacing` (image/conversion-outline.json `margin_padding → module.advanced.spacing`; ImageModule.php:958 reads `module.advanced.spacing`; image/module.json declares no `module.decoration.spacing`). Run → FAIL.
- [ ] **Step 2: Fix** — in `mapSpacing()`, choose the path prefix: `$prefix = $widget_type === 'image' ? 'module.advanced.spacing' : 'module.decoration.spacing';` and use it in the `transformPath` call. Check `mapImageHeight`/`mapImageWidth` already use `module.advanced.sizing` (they do). Remove the known gap. Regenerate `fixtures/divi/image.json`, review, `vendor/bin/phpunit` → OK.
- [ ] **Step 3: Render** — on the probe, the Ceramic-style hero has no image margin; add an `image` widget with `_margin` bottom 40px to the probe band and assert its `.et_pb_image` computed `margin-bottom` is `40px`.
- [ ] **Step 4: Commit** — `fix(converter): image margins and padding reach Divi's image module`.

### Task 22: Icon list items (feature list, price list, content ticker) write text, icon and link where Divi reads them

**Files:**
- Modify: `handlers/class-eael-feature-list-converter.php`, `handlers/class-price-list-converter.php`, `handlers/class-eael-content-ticker-converter.php`, `tests/support/divi-schema-known-gaps.php`
- Test: `tests/AddonSettingNamesTest.php`

- [ ] **Step 1: Failing tests** — for each of the three widgets, convert a two-item example and assert each `divi/icon-list-item` child has `content.innerContent.desktop.value` = the item text, `icon.innerContent.desktop.value` = a `{type, unicode, weight}` object (IconListItemModule.php:77, via `FontAwesomeIcons::fromControl()` from Task 11, star fallback), `module.advanced.link.desktop.value.url` when the item has a link (IconListItemModule.php:178), and no `link` attribute or `module.advanced.text`. Run → FAIL.
- [ ] **Step 2: Fix** — rewrite the item-building code in the three converters to those paths; read EAEL 6.6.7 `Feature_List.php` (`eael_feature_list_title`, `eael_feature_list_content`, `eael_feature_list_icon_new`, `eael_feature_list_link`), `Content_Ticker.php` (`eael_ticker_custom_content`, `eael_ticker_custom_contents[].eael_ticker_custom_content_link`) and Elementor Pro's price list (`price_list[].title`, `price`, `item_description`, `link`) for the names, and add every name read to the handled list. Remove the two known gaps. `vendor/bin/phpunit` → OK.
- [ ] **Step 3: Render** — the Ferncourt pages carry no icon lists; add a Spaces-style `eael-feature-list` with two items to the probe band and assert `.et_pb_icon_list_item` count is 2 and each has a visible `.et_pb_icon_list_icon`.
- [ ] **Step 4: Commit** — `fix(converter): icon list items carry text, icon and link where Divi reads them`.

Task 19 (docs, versions) runs after Task 22.
