# Add-on Setting Names Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make 21 add-on widget converters read the setting names their add-ons actually store, so converted pages keep their content, and fix the Contact Form 7 converter that cannot load at all.

**Architecture:** Each converter reads the add-on's current setting name first and keeps its old name as a `??` fallback. Output shapes stay the same, with three exceptions: the interactive circle becomes `divi/tabs`, and the advanced data table and ElementsKit video get their own converters. One PHPUnit file proves content survives for every widget. A committed sweep script (with its own test) catches future renames.

**Tech Stack:** PHP 8.x, PHPUnit 13 (`vendor/bin/phpunit`), the plugin's own autoloader.

**Spec:** No separate spec file. The design was approved in conversation on 2026-09-15 and is restated in Global Constraints and in each task.

## Global Constraints

- Work only in the worktree `/Users/Lucas/Documents/JHMG-Local/jhmg-elementor-to-divi5-addon-names`, branch `fix/addon-setting-names`, based on `21f94c4`. Run every command from that directory.
- Baseline before any change: `vendor/bin/phpunit` → 644 tests, 1742 assertions, OK, with 1 pre-existing PHPUnit deprecation. The full suite must still pass at the end of every task.
- Source of truth for setting names: Essential Addons for Elementor Lite 6.6.7 (`references/essential-addons-for-elementor-lite.6.6.7.zip`), Header Footer Elementor 2.8.8 (`references/header-footer-elementor.2.8.8.zip`), ElementsKit Lite 4.0.5.
- Read the current name first, the old name second, written as a `??` chain on `$settings[...]` or `$item[...]` (for example `$settings['new'] ?? $settings['old'] ?? ''`). The sweep script in Task 7 treats a chain as satisfied when any name in it exists, so do not hide fallbacks inside helper functions or loops over key lists.
- Elementor does not store a control's default value in `_elementor_data`. When a select setting is absent, treat it as that control's default in the add-on (named in each task).
- Add every newly read setting name to that converter's `logUnmappedSettings()` list, and keep the old names there too.
- Do not bump versions, edit `readme.txt` changelogs, or release.
- Commit messages follow the repo style (`fix(converter): …`) and end with `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.

## File Map

| File | Change |
|---|---|
| `plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-autoloader.php` | Class-to-file rule splits on digits (Task 1) |
| `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-*.php` | Setting-name fixes (Tasks 1–4) |
| `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-advanced-data-table-converter.php` | **New** (Task 3) |
| `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-elementskit-video-converter.php` | **New** (Task 5) |
| `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-hfe-*.php`, `class-counter-converter.php` | Setting-name fixes (Task 6) |
| `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/registry/class-converter-registry.php` | Two registrations point at the new converters (Tasks 3, 5) |
| `tests/bootstrap.php` | `get_bloginfo` and `wp_get_nav_menu_object` stubs (Task 6) |
| `tests/AddonSettingNamesTest.php` | **New**: one or more cases per widget (Tasks 1–6) |
| `scripts/addon-setting-names.php` | **New**: sweep (Task 7) |
| `tests/AddonSettingNamesSweepTest.php` | **New** (Task 7) |

---

### Task 1: Test scaffold, loadable Contact Form 7 converter, and four Essential Addons renames

Fixes `eael-contact-form-7` (class not loadable, and reads `eael_contact_form_id` instead of `contact_form_list`), `eael-fancy-text`, `eael-info-box` and `eael-pricing-table`.

**Files:**
- Create: `tests/AddonSettingNamesTest.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-autoloader.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-contact-form-7-converter.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-fancy-text-converter.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-info-box-converter.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-pricing-table-converter.php`

**Interfaces:**
- Produces: `AddonSettingNamesTest` private helpers used by Tasks 2–6: `convert( string $type, array $settings ): array` returning `[ array $firstBlock, array $fullResult ]`, and `assertCarries( array $block, string ...$needles ): void`, which asserts each needle appears in some string or integer leaf of the block tree.

- [ ] **Step 1: Write the failing tests**

Create `tests/AddonSettingNamesTest.php`:

```php
<?php
// tests/AddonSettingNamesTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Add-on widgets built with the setting names their own plugin defines must
 * convert with their content intact.
 *
 * Every settings array below uses names copied from the add-on's source:
 * Essential Addons for Elementor Lite 6.6.7, Header Footer Elementor 2.8.8,
 * ElementsKit Lite 4.0.5. The converters used to read names none of those
 * versions define, so the widget converted "successfully" with its text, links
 * or table cells silently gone. Each "legacy" case pins the old names, which
 * are kept as fallbacks.
 */
final class AddonSettingNamesTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    /** @return array{0: array, 1: array} The first converted block and the full engine result. */
    private function convert( string $type, array $settings ): array {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'w1',
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => $settings,
            'elements'   => [],
        ] ] );

        return [ $result['divi']['elements'][0], $result ];
    }

    /** Every string and integer leaf in a block tree, one per line. */
    private function leaves( mixed $node ): string {
        if ( is_string( $node ) || is_int( $node ) ) {
            return $node . "\n";
        }
        if ( ! is_array( $node ) ) {
            return '';
        }
        $out = '';
        foreach ( $node as $child ) {
            $out .= $this->leaves( $child );
        }
        return $out;
    }

    private function assertCarries( array $block, string ...$needles ): void {
        $haystack = $this->leaves( $block );
        foreach ( $needles as $needle ) {
            $this->assertStringContainsString( $needle, $haystack, "Converted block lost '{$needle}'." );
        }
    }

    // -------------------------------------------------------------------------
    // Essential Addons — Contact Form 7
    // -------------------------------------------------------------------------

    public function test_contact_form_7_converter_loads_and_reads_contact_form_list(): void {
        [ $block ] = $this->convert( 'eael-contact-form-7', [ 'contact_form_list' => '42' ] );

        $this->assertSame( 'divi/contact-form-7', $block['name'] );
        $this->assertSame( 42, $block['settings']['module']['advanced']['formId']['desktop']['value'] );
    }

    public function test_contact_form_7_legacy_form_id_still_converts(): void {
        [ $block ] = $this->convert( 'eael-contact-form-7', [ 'eael_contact_form_id' => '12' ] );

        $this->assertSame( 12, $block['settings']['module']['advanced']['formId']['desktop']['value'] );
    }

    // -------------------------------------------------------------------------
    // Essential Addons — fancy text, info box, pricing table
    // -------------------------------------------------------------------------

    public function test_fancy_text_reads_the_strings_repeater(): void {
        [ $block ] = $this->convert( 'eael-fancy-text', [
            'eael_fancy_text_prefix'  => 'Work',
            'eael_fancy_text_strings' => [
                [ '_id' => 'a1', 'eael_fancy_text_strings_text_field' => 'better' ],
                [ '_id' => 'b2', 'eael_fancy_text_strings_text_field' => 'together' ],
            ],
            'eael_fancy_text_suffix'  => 'at Ferncourt',
        ] );

        $this->assertSame( 'Work better at Ferncourt', $block['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_fancy_text_legacy_strings_still_convert(): void {
        [ $block ] = $this->convert( 'eael-fancy-text', [
            'eael_fancy_strings' => [ [ 'eael_fancy_string_text' => 'Hello' ] ],
        ] );

        $this->assertSame( 'Hello', $block['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_info_box_reads_its_description_text(): void {
        [ $block ] = $this->convert( 'eael-info-box', [
            'eael_infobox_title' => 'Fast Wi-Fi',
            'eael_infobox_text'  => '<p>Gigabit fibre on every desk.</p>',
        ] );

        $this->assertSame( '<p>Gigabit fibre on every desk.</p>', $block['settings']['module']['advanced']['text']['desktop']['value'] );
    }

    public function test_info_box_legacy_content_still_converts(): void {
        [ $block ] = $this->convert( 'eael-info-box', [ 'eael_infobox_content' => 'Old body' ] );

        $this->assertSame( 'Old body', $block['settings']['module']['advanced']['text']['desktop']['value'] );
    }

    public function test_pricing_table_reads_period_and_button_link(): void {
        [ $block ] = $this->convert( 'eael-pricing-table', [
            'eael_pricing_table_title'        => 'Flex Desk',
            'eael_pricing_table_price'        => '249',
            'eael_pricing_table_price_cur'    => '$',
            'eael_pricing_table_price_period' => 'month',
            'eael_pricing_table_btn'          => 'Join',
            'eael_pricing_table_btn_link'     => [ 'url' => 'https://example.test/join', 'is_external' => '' ],
        ] );

        $advanced = $block['elements'][0]['settings']['module']['advanced'];
        $this->assertSame( 'month', $advanced['perText']['desktop']['value'] );
        $this->assertSame( 'https://example.test/join', $advanced['buttonUrl']['desktop']['value'] );
    }

    public function test_pricing_table_legacy_period_and_url_still_convert(): void {
        [ $block ] = $this->convert( 'eael-pricing-table', [
            'eael_pricing_table_price'     => '10',
            'eael_pricing_table_price_per' => 'year',
            'eael_pricing_table_btn'       => 'Buy',
            'eael_pricing_table_btn_url'   => [ 'url' => 'https://example.test/old' ],
        ] );

        $this->assertCarries( $block, 'year', 'https://example.test/old' );
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: FAIL. Both Contact Form 7 tests error with `Class "\ElementorDivi5Converter\Converter\Handlers\EaelContactForm7Converter" not found`. The fancy text, info box and pricing table tests that use the new names fail on missing array keys or wrong values. The legacy fancy text, info box and pricing table tests pass.

- [ ] **Step 3: Make the autoloader split class names on digits**

In `plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-autoloader.php`, replace:

```php
            $file_name = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name ) ) . '.php';
```

with:

```php
            // Split before capitals and around digits, so EaelContactForm7Converter
            // maps to class-eael-contact-form-7-converter.php. The old rule ignored
            // digits, and that class could never be loaded.
            $file_name = 'class-' . strtolower( preg_replace( [ '/([a-z])([A-Z0-9])/', '/([0-9])([A-Za-z])/' ], '$1-$2', $class_name ) ) . '.php';
```

It is the only class in the free plugin with a digit in its name (verified 2026-09-15), so no other class's file name changes.

- [ ] **Step 4: Fix the Contact Form 7 converter**

In `class-eael-contact-form-7-converter.php`, replace:

```php
        // EAEL stores the CF7 form ID in `eael_contact_form_id`.
        $form_id = (int) ( $settings['eael_contact_form_id'] ?? 0 );
```

with:

```php
        // EAEL 6.x stores the CF7 form's post ID in `contact_form_list`;
        // `eael_contact_form_id` is kept for older exports.
        $form_id = (int) ( $settings['contact_form_list'] ?? $settings['eael_contact_form_id'] ?? 0 );
```

and replace the handled-keys list:

```php
            'eael_contact_form_id', 'eael_contact_form_title',
```

with:

```php
            'contact_form_list', 'eael_contact_form_id', 'eael_contact_form_title',
            'form_title', 'form_title_text', 'form_description', 'form_description_text',
```

- [ ] **Step 5: Fix the fancy text converter**

In `class-eael-fancy-text-converter.php`, replace:

```php
        $fancy_items = $settings['eael_fancy_strings'] ?? [];
        $first_word  = '';
        if ( is_array( $fancy_items ) && ! empty( $fancy_items ) ) {
            $first_item = reset( $fancy_items );
            if ( is_array( $first_item ) ) {
                $first_word = is_string( $first_item['eael_fancy_string_text'] ?? '' ) ? ( $first_item['eael_fancy_string_text'] ?? '' ) : '';
            }
        }
```

with:

```php
        $fancy_items = $settings['eael_fancy_text_strings'] ?? $settings['eael_fancy_strings'] ?? [];
        $first_word  = '';
        if ( is_array( $fancy_items ) && ! empty( $fancy_items ) ) {
            $item = reset( $fancy_items );
            if ( is_array( $item ) ) {
                $text       = $item['eael_fancy_text_strings_text_field'] ?? $item['eael_fancy_string_text'] ?? '';
                $first_word = is_string( $text ) ? $text : '';
            }
        }
```

and in the handled-keys list replace `'eael_fancy_strings',` with `'eael_fancy_text_strings', 'eael_fancy_strings',`.

- [ ] **Step 6: Fix the info box converter**

In `class-eael-info-box-converter.php`, replace:

```php
        $description = is_string( $settings['eael_infobox_content'] ?? '' ) ? ( $settings['eael_infobox_content'] ?? '' ) : '';
```

with:

```php
        $description = $settings['eael_infobox_text'] ?? $settings['eael_infobox_content'] ?? '';
        $description = is_string( $description ) ? $description : '';
```

and in the handled-keys list replace `'eael_infobox_content',` with `'eael_infobox_text', 'eael_infobox_content',`.

- [ ] **Step 7: Fix the pricing table converter**

In `class-eael-pricing-table-converter.php`, replace:

```php
        $per      = is_string( $settings['eael_pricing_table_price_per'] ?? '' ) ? ( $settings['eael_pricing_table_price_per'] ?? '' ) : '';
```

with:

```php
        $per      = $settings['eael_pricing_table_price_period'] ?? $settings['eael_pricing_table_price_per'] ?? '';
        $per      = is_string( $per ) ? $per : '';
```

replace:

```php
        $btn_raw  = $settings['eael_pricing_table_btn_url'] ?? [];
```

with:

```php
        $btn_raw  = $settings['eael_pricing_table_btn_link'] ?? $settings['eael_pricing_table_btn_url'] ?? [];
```

and replace the two handled-keys lines:

```php
            'eael_pricing_table_price_cur', 'eael_pricing_table_price_per',
            'eael_pricing_table_items', 'eael_pricing_table_btn',
            'eael_pricing_table_btn_url', 'eael_pricing_table_onsale',
```

with:

```php
            'eael_pricing_table_price_cur', 'eael_pricing_table_price_period', 'eael_pricing_table_price_per',
            'eael_pricing_table_items', 'eael_pricing_table_btn',
            'eael_pricing_table_btn_link', 'eael_pricing_table_btn_url', 'eael_pricing_table_onsale',
```

- [ ] **Step 8: Run the new tests, then the full suite**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: PASS (8 tests).

Run: `vendor/bin/phpunit`
Expected: OK, 652 tests, with the same single pre-existing deprecation.

- [ ] **Step 9: Commit**

```bash
git add tests/AddonSettingNamesTest.php plugin/jhmg-converter-for-elementor-to-divi/includes/helpers/class-autoloader.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-contact-form-7-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-fancy-text-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-info-box-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-pricing-table-converter.php
git commit -m "fix(converter): read Essential Addons' real names for CF7, fancy text, info box, pricing

The Contact Form 7 converter could not be autoloaded — the class-to-file
rule ignored digits — so any page containing that widget failed to convert.
Fancy text, info box and pricing table read setting names EAEL 6.x never
stores, dropping the rotating words, the description, the price period and
the button link. Old names stay as fallbacks.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 2: Six more Essential Addons renames

Fixes `eael-code-snippet`, `eael-image-accordion`, `eael-login-register`, `eael-simple-menu`, `eael-sticky-video` and `eael-tooltip`.

**Files:**
- Modify: `tests/AddonSettingNamesTest.php`
- Modify (all in `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/`): `class-eael-code-snippet-converter.php`, `class-eael-image-accordion-converter.php`, `class-eael-login-register-converter.php`, `class-eael-simple-menu-converter.php`, `class-eael-sticky-video-converter.php`, `class-eael-tooltip-converter.php`

**Interfaces:**
- Consumes: `convert()` and `assertCarries()` from Task 1.

- [ ] **Step 1: Write the failing tests**

Append these methods inside `AddonSettingNamesTest`, before its closing `}`:

```php
    // -------------------------------------------------------------------------
    // Essential Addons — code snippet, image accordion, simple menu, sticky video, tooltip
    // -------------------------------------------------------------------------

    public function test_code_snippet_reads_code_content_and_language(): void {
        [ $block ] = $this->convert( 'eael-code-snippet', [ 'code_content' => '<?php echo 1;', 'language' => 'php' ] );

        $this->assertCarries( $block, 'language-php', '&lt;?php echo 1;' );
    }

    public function test_code_snippet_without_language_uses_the_html_default(): void {
        // EAEL's language select defaults to html, and Elementor omits defaults.
        [ $block ] = $this->convert( 'eael-code-snippet', [ 'code_content' => '<b>Hi</b>' ] );

        $this->assertSame( '<b>Hi</b>', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_code_snippet_legacy_names_still_convert(): void {
        [ $block ] = $this->convert( 'eael-code-snippet', [ 'eael_code_snippet_code' => 'x = 1', 'eael_code_snippet_type' => 'python' ] );

        $this->assertCarries( $block, 'language-python', 'x = 1' );
    }

    public function test_image_accordion_reads_the_accordions_repeater(): void {
        [ $block ] = $this->convert( 'eael-image-accordion', [
            'eael_img_accordions' => [ [
                '_id'                    => 'a1',
                'eael_accordion_tittle'  => 'Lounge',
                'eael_accordion_content' => '<p>Sofas and coffee</p>',
                'eael_accordion_bg'      => [ 'url' => 'https://example.test/lounge.jpg', 'id' => 5 ],
            ] ],
        ] );

        $this->assertSame( 'divi/accordion-item', $block['elements'][0]['name'] );
        $this->assertSame( 'Lounge', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertCarries( $block, '<p>Sofas and coffee</p>', 'https://example.test/lounge.jpg' );
    }

    public function test_image_accordion_legacy_items_still_convert(): void {
        [ $block ] = $this->convert( 'eael-image-accordion', [
            'eael_img_accordion_items' => [ [ 'eael_img_accordion_title' => 'Old', 'eael_img_accordion_content' => 'Body' ] ],
        ] );

        $this->assertCarries( $block, 'Old', 'Body' );
    }

    public function test_simple_menu_reads_the_selected_menu_id(): void {
        [ $block ] = $this->convert( 'eael-simple-menu', [ 'eael_simple_menu_menu' => '7' ] );

        $this->assertSame( '7', $block['settings']['menu']['innerContent']['desktop']['value']['menuId'] );
    }

    public function test_simple_menu_legacy_slug_still_converts(): void {
        [ $block ] = $this->convert( 'eael-simple-menu', [ 'eael_simple_menu_slug' => 'main' ] );

        $this->assertSame( 'main', $block['settings']['menu']['innerContent']['desktop']['value']['menuId'] );
    }

    public function test_sticky_video_defaults_to_youtube_link(): void {
        // EAEL's source select defaults to youtube, and Elementor omits defaults.
        [ $block ] = $this->convert( 'eael-sticky-video', [ 'eaelsv_link_youtube' => 'https://www.youtube.com/watch?v=abc' ] );

        $this->assertSame( 'https://www.youtube.com/watch?v=abc', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_reads_vimeo_link(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [ 'eael_video_source' => 'vimeo', 'eaelsv_link_vimeo' => 'https://vimeo.com/1' ] );

        $this->assertSame( 'https://vimeo.com/1', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_reads_self_hosted_media(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [
            'eael_video_source' => 'self_hosted',
            'eaelsv_hosted_url' => [ 'url' => 'https://example.test/tour.mp4', 'id' => 9 ],
        ] );

        $this->assertSame( 'https://example.test/tour.mp4', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_reads_external_url_when_switched_on(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [
            'eael_video_source'    => 'self_hosted',
            'eaelsv_link_external' => 'yes',
            'eaelsv_external_url'  => 'https://cdn.example.test/tour.mp4',
            'eaelsv_hosted_url'    => [ 'url' => 'https://example.test/ignored.mp4' ],
        ] );

        $this->assertSame( 'https://cdn.example.test/tour.mp4', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_sticky_video_legacy_url_still_converts(): void {
        [ $block ] = $this->convert( 'eael-sticky-video', [ 'eael_video_url' => 'https://example.test/old.mp4' ] );

        $this->assertSame( 'https://example.test/old.mp4', $block['settings']['module']['advanced']['videoUrl']['desktop']['value'] );
    }

    public function test_tooltip_reads_visible_text_and_hover_content(): void {
        [ $block ] = $this->convert( 'eael-tooltip', [
            'eael_tooltip_content'       => 'Day pass',
            'eael_tooltip_hover_content' => 'Valid 8am to 8pm',
        ] );

        $this->assertSame( 'Day pass (Valid 8am to 8pm)', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_tooltip_legacy_trigger_text_still_converts(): void {
        [ $block ] = $this->convert( 'eael-tooltip', [
            'eael_tooltip_trigger_text' => 'Hover me',
            'eael_tooltip_content'      => 'Tip',
        ] );

        $this->assertSame( 'Hover me (Tip)', $block['settings']['content']['innerContent']['desktop']['value'] );
    }
```

`eael-login-register` has no content test: its output (`[woocommerce_my_account]`) never depended on the settings it read. Task 7's sweep test covers that it no longer reads names EAEL doesn't define.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: FAIL for the new-name cases of code snippet (2), image accordion, simple menu, sticky video (4: YouTube, Vimeo, self-hosted, external) and tooltip. The legacy cases pass.

- [ ] **Step 3: Fix the code snippet converter**

In `class-eael-code-snippet-converter.php`, replace:

```php
        $code = is_string( $settings['eael_code_snippet_code'] ?? '' ) ? ( $settings['eael_code_snippet_code'] ?? '' ) : '';
        $type = is_string( $settings['eael_code_snippet_type'] ?? '' ) ? ( $settings['eael_code_snippet_type'] ?? '' ) : '';
```

with:

```php
        $code = $settings['code_content'] ?? $settings['eael_code_snippet_code'] ?? '';
        $code = is_string( $code ) ? $code : '';
        // EAEL 6.x's `language` select defaults to 'html', which is left unwrapped below.
        $type = $settings['language'] ?? $settings['eael_code_snippet_type'] ?? '';
        $type = is_string( $type ) ? $type : '';
```

and replace the handled-keys list with:

```php
            'code_content', 'language', 'file_name', 'theme', 'show_line_numbers', 'show_copy_button',
            'eael_code_snippet_code', 'eael_code_snippet_type',
            'eael_code_snippet_theme', 'eael_code_snippet_line_numbers',
```

- [ ] **Step 4: Fix the image accordion converter**

In `class-eael-image-accordion-converter.php`, replace:

```php
        $items    = $settings['eael_img_accordion_items'] ?? [];
```

with:

```php
        $items    = $settings['eael_img_accordions'] ?? $settings['eael_img_accordion_items'] ?? [];
        $items    = is_array( $items ) ? $items : [];
```

replace:

```php
            $title   = is_string( $item['eael_img_accordion_title'] ?? '' ) ? ( $item['eael_img_accordion_title'] ?? '' ) : '';
            $content = is_string( $item['eael_img_accordion_content'] ?? '' ) ? ( $item['eael_img_accordion_content'] ?? '' ) : '';

            $image_raw = $item['eael_img_accordion_image'] ?? [];
```

with:

```php
            // `eael_accordion_tittle` is EAEL's own spelling.
            $title   = $item['eael_accordion_tittle'] ?? $item['eael_img_accordion_title'] ?? '';
            $title   = is_string( $title ) ? $title : '';
            $content = $item['eael_accordion_content'] ?? $item['eael_img_accordion_content'] ?? '';
            $content = is_string( $content ) ? $content : '';

            $image_raw = $item['eael_accordion_bg'] ?? $item['eael_img_accordion_image'] ?? [];
```

If the blank lines between those statements differ in the file, match the three statements individually. Then replace the handled-keys list with:

```php
            'eael_img_accordions', 'eael_img_accordion_type', 'eael_img_accordion_direction',
            'eael_img_accordion_items', 'eael_img_accordion_event',
            'eael_img_accordion_active_item',
```

A per-item title link (`eael_accordion_title_link`) is not carried over, as before: Divi accordion titles are plain text.

- [ ] **Step 5: Remove the login/register converter's dead reads**

In `class-eael-login-register-converter.php`, replace:

```php
        $show_login    = ( $settings['eael_show_login_content'] ?? 'yes' ) === 'yes';
        $show_register = ( $settings['eael_show_register_content'] ?? 'yes' ) === 'yes';

        if ( $show_register ) {
            $shortcode = '[woocommerce_my_account]';
        } elseif ( $show_login ) {
            $shortcode = '[woocommerce_my_account]';
        } else {
            $shortcode = '[woocommerce_my_account]';
        }
```

with:

```php
        // Every form type EAEL offers (`default_form_type`: login, register,
        // lostpassword) becomes WooCommerce's account shortcode, which shows
        // login and registration together.
        $shortcode = '[woocommerce_my_account]';
```

(Match the statements individually if the blank lines differ.) Then replace the handled-keys list with:

```php
            'default_form_type', 'show_login_link', 'show_register_link', 'show_lost_password',
            'login_link_text', 'registration_link_text', 'hide_for_logged_in_user',
            'eael_show_login_content', 'eael_show_register_content',
            'eael_login_redirect_url', 'eael_registration_redirect_url',
            'eael_login_form_title', 'eael_register_form_title',
```

- [ ] **Step 6: Fix the simple menu converter**

In `class-eael-simple-menu-converter.php`, replace:

```php
        $menu_id = $settings['eael_simple_menu_slug'] ?? $settings['menu_slug'] ?? $settings['menu'] ?? '';
```

with:

```php
        // EAEL 6.x stores the menu's term ID in `eael_simple_menu_menu`.
        $menu_id = $settings['eael_simple_menu_menu'] ?? $settings['eael_simple_menu_slug'] ?? $settings['menu_slug'] ?? $settings['menu'] ?? '';
```

and in the handled-keys list replace `'eael_simple_menu_slug', 'menu_slug', 'menu',` with `'eael_simple_menu_menu', 'eael_simple_menu_slug', 'menu_slug', 'menu',`.

- [ ] **Step 7: Fix the sticky video converter**

In `class-eael-sticky-video-converter.php`, replace:

```php
        $url = is_string( $settings['eael_video_url'] ?? '' ) ? ( $settings['eael_video_url'] ?? '' ) : '';
        if ( $url === '' ) {
            $url = is_string( $settings['url'] ?? '' ) ? ( $settings['url'] ?? '' ) : '';
        }
```

with:

```php
        $url = $settings['eael_video_url'] ?? $settings['url'] ?? '';
        $url = is_string( $url ) ? $url : '';

        if ( $url === '' ) {
            // EAEL 6.x: `eael_video_source` is youtube (the default, so often
            // absent), vimeo or self_hosted, each with its own link setting.
            $source = $settings['eael_video_source'] ?? 'youtube';

            if ( $source === 'vimeo' ) {
                $url = $settings['eaelsv_link_vimeo'] ?? '';
            } elseif ( $source === 'self_hosted' ) {
                if ( ( $settings['eaelsv_link_external'] ?? '' ) === 'yes' ) {
                    $url = $settings['eaelsv_external_url'] ?? '';
                } else {
                    $hosted = $settings['eaelsv_hosted_url'] ?? [];
                    $url    = is_array( $hosted ) ? ( $hosted['url'] ?? '' ) : '';
                }
            } else {
                $url = $settings['eaelsv_link_youtube'] ?? '';
            }

            $url = is_string( $url ) ? $url : '';
        }
```

and replace the handled-keys list with:

```php
            'eael_video_source', 'eaelsv_link_youtube', 'eaelsv_link_vimeo',
            'eaelsv_link_external', 'eaelsv_external_url', 'eaelsv_hosted_url',
            'eael_video_url', 'url', 'eael_sticky_video_type',
            'eael_sticky_video_sticky_position', 'eael_video_autoplay',
```

- [ ] **Step 8: Fix the tooltip converter**

In `class-eael-tooltip-converter.php`, replace:

```php
        $trigger_text  = is_string( $settings['eael_tooltip_trigger_text'] ?? '' ) ? ( $settings['eael_tooltip_trigger_text'] ?? '' ) : '';
        $tooltip_text  = is_string( $settings['eael_tooltip_content'] ?? '' ) ? ( $settings['eael_tooltip_content'] ?? '' ) : '';
```

with:

```php
        $legacy_trigger = $settings['eael_tooltip_trigger_text'] ?? '';

        if ( is_string( $legacy_trigger ) && $legacy_trigger !== '' ) {
            // Older exports: trigger text plus the tooltip in `eael_tooltip_content`.
            $trigger_text = $legacy_trigger;
            $tooltip_text = $settings['eael_tooltip_content'] ?? '';
        } else {
            // EAEL 6.x: `eael_tooltip_content` is the visible text and
            // `eael_tooltip_hover_content` the tooltip. Icon and image triggers
            // have no text to carry over.
            $type         = $settings['eael_tooltip_type'] ?? 'text';
            $trigger_text = match ( $type ) {
                'shortcode'     => $settings['eael_tooltip_shortcode_content'] ?? '',
                'icon', 'image' => '',
                default         => $settings['eael_tooltip_content'] ?? '',
            };
            $tooltip_text = $settings['eael_tooltip_hover_content'] ?? '';
        }

        $trigger_text = is_string( $trigger_text ) ? $trigger_text : '';
        $tooltip_text = is_string( $tooltip_text ) ? $tooltip_text : '';
```

and replace the handled-keys list with:

```php
            'eael_tooltip_type', 'eael_tooltip_hover_content', 'eael_tooltip_shortcode_content',
            'eael_tooltip_trigger_text', 'eael_tooltip_content',
            'eael_tooltip_target_element_type', 'eael_tooltip_trigger_icon',
            'eael_tooltip_placement', 'eael_tooltip_animation',
```

- [ ] **Step 9: Run the new tests, then the full suite**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: PASS (22 tests).

Run: `vendor/bin/phpunit`
Expected: OK, 666 tests, same single pre-existing deprecation.

- [ ] **Step 10: Commit**

```bash
git add tests/AddonSettingNamesTest.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-code-snippet-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-image-accordion-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-login-register-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-simple-menu-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-sticky-video-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-tooltip-converter.php
git commit -m "fix(converter): read Essential Addons' real names for six more widgets

Code snippet, image accordion, simple menu, sticky video and tooltip read
setting names EAEL 6.x never stores, so their code, panels, menu, video URL
and tooltip text were lost. Login/register read two names that never
affected its output; those reads are gone. Old names stay as fallbacks.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 3: Data table (flat rows) and its own advanced data table converter

`eael-data-table` reads nested repeaters EAEL 6.x doesn't have, so every table converts empty. `eael-advanced-data-table` shares that converter, but EAEL stores that widget's whole table as HTML.

**Files:**
- Modify: `tests/AddonSettingNamesTest.php`
- Replace: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-data-table-converter.php`
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-advanced-data-table-converter.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/registry/class-converter-registry.php` (the `eael-advanced-data-table` line)

**Interfaces:**
- Consumes: `convert()` and `assertCarries()` from Task 1.
- Produces: class `ElementorDivi5Converter\Converter\Handlers\EaelAdvancedDataTableConverter extends BaseElementorConverter`, registered for `eael-advanced-data-table`.

- [ ] **Step 1: Write the failing tests**

Append inside `AddonSettingNamesTest`:

```php
    // -------------------------------------------------------------------------
    // Essential Addons — data table, advanced data table
    // -------------------------------------------------------------------------

    private function codeValue( array $block ): string {
        return (string) $block['settings']['content']['innerContent']['desktop']['value'];
    }

    public function test_data_table_groups_flat_row_and_col_entries_into_rows(): void {
        [ $block ] = $this->convert( 'eael-data-table', [
            'eael_data_table_header_cols_data' => [
                [ '_id' => 'h1', 'eael_data_table_header_col' => 'Plan' ],
                [ '_id' => 'h2', 'eael_data_table_header_col' => 'Price' ],
            ],
            'eael_data_table_content_rows' => [
                // 'row' is the default row type, so Elementor often omits it.
                [ '_id' => 'r1' ],
                [ '_id' => 'c1', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_row_title' => 'Day pass' ],
                [ '_id' => 'c2', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_type' => 'editor', 'eael_data_table_content_row_content' => '<strong>$25</strong>' ],
                [ '_id' => 'r2', 'eael_data_table_content_row_type' => 'row' ],
                [ '_id' => 'c3', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_row_title' => 'Private office', 'eael_data_table_content_row_colspan' => 2 ],
            ],
        ] );

        $html = $this->codeValue( $block );
        $this->assertStringContainsString( '>Plan</th>', $html );
        $this->assertStringContainsString( '>Price</th>', $html );
        $this->assertStringContainsString( '>Day pass</td>', $html );
        $this->assertStringContainsString( '<strong>$25</strong>', $html );
        $this->assertStringContainsString( ' colspan="2">Private office</td>', $html );
        $this->assertSame( 3, substr_count( $html, '<tr>' ), 'One header row and two body rows.' );
    }

    public function test_data_table_template_cell_is_reported(): void {
        [ , $result ] = $this->convert( 'eael-data-table', [
            'eael_data_table_content_rows' => [
                [ '_id' => 'r1' ],
                [ '_id' => 'c1', 'eael_data_table_content_row_type' => 'col', 'eael_data_table_content_type' => 'template' ],
            ],
        ] );

        $this->assertStringContainsString( 'template', implode( "\n", $result['report']['warnings'] ) );
    }

    public function test_data_table_legacy_nested_rows_still_convert(): void {
        [ $block ] = $this->convert( 'eael-data-table', [
            'eael_data_table_header_cols' => [ [ 'eael_dt_header_col' => 'H' ] ],
            'eael_data_table_body_rows'   => [ [ 'eael_dt_body_col_rows' => [ [ 'eael_dt_body_col' => 'A' ] ] ] ],
        ] );

        $html = $this->codeValue( $block );
        $this->assertStringContainsString( '>H</th>', $html );
        $this->assertStringContainsString( '>A</td>', $html );
    }

    public function test_advanced_data_table_wraps_static_html_by_default(): void {
        // 'static' is the default source, so it is absent here.
        [ $block ] = $this->convert( 'eael-advanced-data-table', [
            'ea_adv_data_table_static_html' => '<thead><tr><th>Room</th></tr></thead><tbody><tr><td>Studio</td></tr></tbody>',
        ] );

        $html = $this->codeValue( $block );
        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertSame( 1, substr_count( $html, '<table' ) );
        $this->assertStringContainsString( '<td>Studio</td>', $html );
    }

    public function test_advanced_data_table_does_not_double_wrap_a_full_table(): void {
        [ $block ] = $this->convert( 'eael-advanced-data-table', [
            'ea_adv_data_table_static_html' => '<table><tr><td>Studio</td></tr></table>',
        ] );

        $this->assertSame( 1, substr_count( $this->codeValue( $block ), '<table' ) );
    }

    public function test_advanced_data_table_reads_csv_html(): void {
        [ $block ] = $this->convert( 'eael-advanced-data-table', [
            'ea_adv_data_table_source'   => 'csv',
            'ea_adv_data_table_csv_html' => '<tr><td>Meeting room</td></tr>',
        ] );

        $this->assertStringContainsString( '<td>Meeting room</td>', $this->codeValue( $block ) );
    }

    public function test_advanced_data_table_external_source_is_reported_not_invented(): void {
        [ $block, $result ] = $this->convert( 'eael-advanced-data-table', [ 'ea_adv_data_table_source' => 'database' ] );

        $this->assertSame( '', $this->codeValue( $block ) );
        $this->assertStringContainsString( 'outside the page', implode( "\n", $result['report']['warnings'] ) );
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: FAIL for the flat-rows test, the template test, and all four advanced data table tests. The legacy nested test passes.

- [ ] **Step 3: Replace the data table converter**

Overwrite `class-eael-data-table-converter.php` with:

```php
<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Data Table → divi/code holding an HTML table.
 *
 * EAEL 6.x keeps the header in `eael_data_table_header_cols_data` and the whole
 * body in ONE flat repeater, `eael_data_table_content_rows`: an entry whose
 * `eael_data_table_content_row_type` is 'row' (the default, so often absent)
 * starts a table row, and each following 'col' entry is a cell in that row.
 * The nested `eael_data_table_body_rows` shape is still read for older exports.
 *
 * The Advanced Data Table stores its table as HTML and has its own converter.
 */
class EaelDataTableConverter extends BaseElementorConverter {

    private const CELL_STYLE = 'border:1px solid #ddd;padding:8px;';

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $html = '<table style="width:100%;border-collapse:collapse;">'
            . $this->header( $settings )
            . $this->body( (string) $id, $settings )
            . '</table>';

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_data_table_header_cols_data', 'eael_data_table_content_rows',
            'eael_data_table_header_cols', 'eael_data_table_body_rows',
            'eael_data_table_responsive', 'eael_data_table_search',
            'eael_data_table_sort', 'eael_data_table_pagination',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $html ] ],
                ],
            ],
            'elements' => [],
        ];
    }

    private function header( array $settings ): string {
        $cols = $settings['eael_data_table_header_cols_data'] ?? $settings['eael_data_table_header_cols'] ?? [];
        if ( ! is_array( $cols ) || empty( $cols ) ) {
            return '';
        }

        $html = '<thead><tr>';
        foreach ( $cols as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $text = $item['eael_data_table_header_col'] ?? $item['eael_dt_header_col'] ?? '';
            $span = (int) ( $item['eael_data_table_header_col_span'] ?? $item['eael_dt_header_col_span'] ?? 1 );

            $html .= '<th style="' . self::CELL_STYLE . 'background:#f5f5f5;"' . $this->span( 'colspan', $span ) . '>'
                . esc_html( is_string( $text ) ? $text : '' ) . '</th>';
        }

        return $html . '</tr></thead>';
    }

    private function body( string $id, array $settings ): string {
        $entries = $settings['eael_data_table_content_rows'] ?? [];
        $rows    = is_array( $entries ) && ! empty( $entries )
            ? $this->flatRows( $id, $entries )
            : $this->legacyRows( $settings );

        if ( empty( $rows ) ) {
            return '';
        }

        return '<tbody><tr>' . implode( '</tr><tr>', $rows ) . '</tr></tbody>';
    }

    /** @return string[] Each body row's cells as HTML. */
    private function flatRows( string $id, array $entries ): array {
        $rows  = [];
        $cells = null;

        foreach ( $entries as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            if ( ( $item['eael_data_table_content_row_type'] ?? 'row' ) !== 'col' ) {
                if ( $cells !== null ) {
                    $rows[] = $cells;
                }
                $cells = '';
                continue;
            }
            // A 'col' before any 'row' still belongs to a first row.
            $cells = ( $cells ?? '' ) . $this->flatCell( $id, $item );
        }

        if ( $cells !== null ) {
            $rows[] = $cells;
        }

        return $rows;
    }

    private function flatCell( string $id, array $item ): string {
        // 'textarea' is the plain-text cell type.
        $type = $item['eael_data_table_content_type'] ?? 'textarea';

        if ( $type === 'editor' ) {
            $content = $item['eael_data_table_content_row_content'] ?? '';
            $inner   = wp_kses_post( is_string( $content ) ? $content : '' );
        } elseif ( $type === 'icon' || $type === 'template' ) {
            // Icon cells have no text; template cells render a saved Elementor template.
            $inner = '';
            if ( $type === 'template' ) {
                $this->engine->logWarning( "Data table {$id} has a cell that renders a saved Elementor template; the template was not carried over." );
            }
        } else {
            $title = $item['eael_data_table_content_row_title'] ?? '';
            $inner = esc_html( is_string( $title ) ? $title : '' );
            $link  = $item['eael_data_table_content_row_title_link'] ?? [];
            $url   = is_array( $link ) && is_string( $link['url'] ?? null ) ? $link['url'] : '';
            if ( $url !== '' && $inner !== '' ) {
                $inner = '<a href="' . esc_url( $url ) . '">' . $inner . '</a>';
            }
        }

        $colspan = (int) ( $item['eael_data_table_content_row_colspan'] ?? 1 );
        $rowspan = (int) ( $item['eael_data_table_content_row_rowspan'] ?? 1 );

        return '<td style="' . self::CELL_STYLE . '"' . $this->span( 'colspan', $colspan ) . $this->span( 'rowspan', $rowspan ) . '>'
            . $inner . '</td>';
    }

    /** @return string[] Each body row's cells as HTML, from the pre-6.x nested shape. */
    private function legacyRows( array $settings ): array {
        $body = $settings['eael_data_table_body_rows'] ?? [];
        $rows = [];

        foreach ( is_array( $body ) ? $body : [] as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $cells = $row['eael_dt_body_col_rows'] ?? [];
            if ( ! is_array( $cells ) || empty( $cells ) ) {
                // Flat legacy row: the row itself is one cell.
                $text   = $row['eael_dt_body_row_value'] ?? '';
                $rows[] = '<td style="' . self::CELL_STYLE . '">' . esc_html( is_string( $text ) ? $text : '' ) . '</td>';
                continue;
            }

            $html = '';
            foreach ( $cells as $cell ) {
                if ( ! is_array( $cell ) ) {
                    continue;
                }
                $text  = $cell['eael_dt_body_col'] ?? '';
                $html .= '<td style="' . self::CELL_STYLE . '"'
                    . $this->span( 'colspan', (int) ( $cell['eael_dt_body_col_span'] ?? 1 ) )
                    . $this->span( 'rowspan', (int) ( $cell['eael_dt_body_row_span'] ?? 1 ) ) . '>'
                    . esc_html( is_string( $text ) ? $text : '' ) . '</td>';
            }
            $rows[] = $html;
        }

        return $rows;
    }

    private function span( string $attribute, int $count ): string {
        return $count > 1 ? " {$attribute}=\"{$count}\"" : '';
    }
}
```

- [ ] **Step 4: Create the advanced data table converter**

Create `class-eael-advanced-data-table-converter.php`:

```php
<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Advanced Data Table → divi/code.
 *
 * Unlike the plain Data Table, EAEL stores this widget's table as ready-made
 * HTML: `ea_adv_data_table_static_html` for the 'static' source (the default)
 * and `ea_adv_data_table_csv_html` for an imported CSV. Other sources (Ninja
 * Tables, a database, a remote database, …) are read at render time from
 * outside the page, so there is nothing to carry over; that is reported
 * rather than left silently empty.
 */
class EaelAdvancedDataTableConverter extends BaseElementorConverter {

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_code_' );
        $settings = $element['settings'] ?? [];

        $source = $settings['ea_adv_data_table_source'] ?? 'static';
        $source = is_string( $source ) ? $source : 'static';

        $table = match ( $source ) {
            'static' => $settings['ea_adv_data_table_static_html'] ?? '',
            'csv'    => $settings['ea_adv_data_table_csv_html'] ?? '',
            default  => '',
        };
        $table = is_string( $table ) ? wp_kses_post( $table ) : '';

        if ( $table !== '' && stripos( $table, '<table' ) === false ) {
            $table = '<table style="width:100%;border-collapse:collapse;">' . $table . '</table>';
        }

        if ( ! in_array( $source, [ 'static', 'csv' ], true ) ) {
            $this->engine->logWarning( "Advanced data table {$id} loads its rows from '{$source}', which lives outside the page; the table was not carried over." );
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, [
            'ea_adv_data_table_source', 'ea_adv_data_table_static_html', 'ea_adv_data_table_csv_html',
            'ea_adv_data_table_search', 'ea_adv_data_table_search_placeholder',
            'ea_adv_data_table_sort', 'ea_adv_data_table_pagination', 'ea_adv_data_table_items_per_page',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/code',
            'settings' => [
                'content' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $table ] ],
                ],
            ],
            'elements' => [],
        ];
    }
}
```

- [ ] **Step 5: Register it**

In `class-converter-registry.php`, replace:

```php
        $this->registerWidget( 'eael-advanced-data-table','\\ElementorDivi5Converter\\Converter\\Handlers\\EaelDataTableConverter' );
```

with:

```php
        $this->registerWidget( 'eael-advanced-data-table','\\ElementorDivi5Converter\\Converter\\Handlers\\EaelAdvancedDataTableConverter' );
```

- [ ] **Step 6: Run the new tests, then the full suite**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: PASS (29 tests).

Run: `vendor/bin/phpunit`
Expected: OK, 673 tests, same single pre-existing deprecation.

- [ ] **Step 7: Commit**

```bash
git add tests/AddonSettingNamesTest.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-data-table-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-advanced-data-table-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/registry/class-converter-registry.php
git commit -m "fix(converter): convert EAEL data tables from the rows EAEL actually stores

EAEL 6.x keeps a data table's body as one flat list of row and col entries;
the converter looked for nested repeaters that don't exist, so every table
converted empty. The advanced data table shared that converter but stores its
table as HTML; it now has its own converter, and sources that live outside
the page are reported instead of silently dropped.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 4: Interactive circle becomes tabs; content ticker reports its live feed

`eael-interactive-circle` looks for numbers the widget doesn't have. Each EAEL 6.x item is a title plus content, which maps onto Divi tabs. `eael-content-ticker` reads a heading and items that EAEL Lite doesn't store. Lite only offers the "dynamic" ticker (latest posts, rendered live); custom items are an EAEL Pro feature.

**Files:**
- Modify: `tests/AddonSettingNamesTest.php`
- Replace: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-interactive-circle-converter.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-content-ticker-converter.php`

**Interfaces:**
- Consumes: `convert()` and `assertCarries()` from Task 1.
- Produces: `eael-interactive-circle` now outputs `divi/tabs` with `divi/tab` children (`settings.title` / `settings.content`), the same shape `TabsConverter` produces.

- [ ] **Step 1: Write the failing tests**

Append inside `AddonSettingNamesTest`:

```php
    // -------------------------------------------------------------------------
    // Essential Addons — interactive circle, content ticker
    // -------------------------------------------------------------------------

    public function test_interactive_circle_items_become_tabs(): void {
        [ $block ] = $this->convert( 'eael-interactive-circle', [
            'eael_interactive_circle_item' => [
                [ '_id' => 'i1', 'eael_interactive_circle_btn_title' => 'Community', 'eael_interactive_circle_item_content' => '<p>Monthly socials</p>' ],
                [ '_id' => 'i2', 'eael_interactive_circle_btn_title' => 'Focus', 'eael_interactive_circle_item_content' => '<p>Quiet zones</p>' ],
            ],
        ] );

        $this->assertSame( 'divi/tabs', $block['name'] );
        $this->assertCount( 2, $block['elements'] );
        $this->assertSame( 'divi/tab', $block['elements'][0]['name'] );
        $this->assertSame( 'Community', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '<p>Monthly socials</p>', $block['elements'][0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Focus', $block['elements'][1]['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_interactive_circle_legacy_title_still_converts(): void {
        [ $block ] = $this->convert( 'eael-interactive-circle', [
            'eael_interactive_circle_item' => [ [ 'eael_ic_title' => 'Old title' ] ],
        ] );

        $this->assertSame( 'Old title', $block['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_content_ticker_dynamic_feed_keeps_its_label_and_is_reported(): void {
        // 'dynamic' is EAEL's default ticker type, so it is absent here.
        [ $block, $result ] = $this->convert( 'eael-content-ticker', [ 'eael_ticker_tag_text' => 'Latest news' ] );

        $this->assertSame( 'divi/icon-list', $block['name'] );
        $this->assertCarries( $block, 'Latest news' );
        $this->assertStringContainsString( 'not carried over', implode( "\n", $result['report']['warnings'] ) );
    }

    public function test_content_ticker_legacy_items_still_convert_without_a_warning(): void {
        [ $block, $result ] = $this->convert( 'eael-content-ticker', [
            'eael_ticker_heading' => 'News',
            'eael_ticker_items'   => [ [ 'eael_ct_title' => 'Open day', 'eael_ct_link' => [ 'url' => 'https://example.test/open' ] ] ],
        ] );

        $this->assertCarries( $block, 'News', 'Open day', 'https://example.test/open' );
        $this->assertStringNotContainsString( 'not carried over', implode( "\n", $result['report']['warnings'] ) );
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: FAIL for both interactive circle tests (block is `divi/number-counter` or `divi/group`, not `divi/tabs`) and for the dynamic ticker test (label missing, no warning). The legacy ticker test passes.

- [ ] **Step 3: Replace the interactive circle converter**

Overwrite `class-eael-interactive-circle-converter.php` with:

```php
<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts EAEL Interactive Circle → divi/tabs.
 *
 * Each item in `eael_interactive_circle_item` is a button title
 * (`eael_interactive_circle_btn_title`) that reveals rich content
 * (`eael_interactive_circle_item_content`) — tabs arranged around a circle.
 * Divi has no circular layout, so the items become ordinary tabs. The circular
 * arrangement, icons and autoplay are not carried over.
 */
class EaelInteractiveCircleConverter extends BaseElementorConverter {

    public function convert( array $element ): array {
        $id       = $element['id'] ?? uniqid( 'divi_tabs_' );
        $settings = $element['settings'] ?? [];
        $items    = $settings['eael_interactive_circle_item'] ?? [];
        $children = [];

        foreach ( is_array( $items ) ? $items : [] as $idx => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title   = $item['eael_interactive_circle_btn_title'] ?? $item['eael_ic_title'] ?? '';
            $title   = is_string( $title ) ? $title : '';
            $content = $item['eael_interactive_circle_item_content'] ?? '';
            $content = is_string( $content ) ? $content : '';

            $child_attrs = [];
            if ( $title !== '' ) {
                $child_attrs['title'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $title ] ] ];
            }
            if ( $content !== '' ) {
                $child_attrs['content'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $content ] ] ];
            }

            $children[] = [
                'id'       => $id . '-tab-' . ( $idx + 1 ),
                'name'     => 'divi/tab',
                'settings' => $child_attrs,
                'elements' => [],
            ];
        }

        $this->engine->logConverted( 'tabs' );
        $this->logUnmappedSettings( $id, $settings, [
            'eael_interactive_circle_item', 'eael_interactive_circle_preset',
            'eael_interactive_circle_event', 'eael_interactive_circle_autoplay',
            'eael_interactive_circle_autoplay_interval', 'eael_interactive_circle_rotation',
        ] );

        return [
            'id'       => $id,
            'name'     => 'divi/tabs',
            'settings' => [],
            'elements' => $children,
        ];
    }
}
```

The old converter's `eael_ic_progress` / `eael_ic_count` numbers are dropped; no EAEL version this plan can verify stores them.

- [ ] **Step 4: Fix the content ticker converter**

In `class-eael-content-ticker-converter.php`, replace:

```php
        $heading  = is_string( $settings['eael_ticker_heading'] ?? '' ) ? ( $settings['eael_ticker_heading'] ?? '' ) : '';
        $items    = $settings['eael_ticker_items'] ?? [];
```

with:

```php
        // EAEL 6.x: the label is `eael_ticker_tag_text`. Lite only offers the
        // 'dynamic' ticker (the default), which renders the latest posts live and
        // stores no items. Custom items (`eael_ticker_custom_contents`) are an
        // EAEL Pro feature; their per-item names below are unverified.
        $type     = $settings['eael_ticker_type'] ?? 'dynamic';
        $heading  = $settings['eael_ticker_tag_text'] ?? $settings['eael_ticker_heading'] ?? '';
        $heading  = is_string( $heading ) ? $heading : '';
        $items    = $settings['eael_ticker_custom_contents'] ?? $settings['eael_ticker_items'] ?? [];
        $items    = is_array( $items ) ? $items : [];
```

Immediately before `$this->engine->logConverted( 'icon-list' );` insert:

```php
        if ( empty( $items ) && $type === 'dynamic' ) {
            $this->engine->logWarning( "Content ticker {$id} shows your latest posts live; the post feed was not carried over, only its label." );
        }

```

and replace the handled-keys line:

```php
            'eael_ticker_heading', 'eael_ticker_items', 'eael_ticker_type',
```

with:

```php
            'eael_ticker_tag_text', 'eael_ticker_custom_contents',
            'eael_ticker_heading', 'eael_ticker_items', 'eael_ticker_type',
```

- [ ] **Step 5: Run the new tests, then the full suite**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: PASS (33 tests).

Run: `vendor/bin/phpunit`
Expected: OK, 677 tests, same single pre-existing deprecation.

- [ ] **Step 6: Commit**

```bash
git add tests/AddonSettingNamesTest.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-interactive-circle-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-eael-content-ticker-converter.php
git commit -m "fix(converter): interactive circle items become tabs; ticker reports its live feed

The interactive circle converter read numbers EAEL never stores and emitted
empty counters; each item is a title and content, which Divi tabs can hold.
The content ticker read a heading and items EAEL Lite doesn't store; it now
keeps the label and reports that the live post feed was not carried over.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 5: ElementsKit video gets its own converter

`elementskit-video` is registered to the core `VideoConverter`, which reads Elementor's `video_type` / `youtube_url` / `vimeo_url` / `hosted_url`. ElementsKit Lite 4.0.5 stores `ekit_video_popup_video_type` (`youtube` by default, `vimeo`, `self`), `ekit_video_popup_url`, `ekit_video_self_url` (a switch that defaults to `yes`), `ekit_video_self_external_url` and `ekit_video_player_self_hosted` (media). The new converter translates those into core names and hands off to `VideoConverter`, so core `video` is untouched.

**Files:**
- Modify: `tests/AddonSettingNamesTest.php`
- Create: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-elementskit-video-converter.php`
- Modify: `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/registry/class-converter-registry.php` (the `elementskit-video` line)

**Interfaces:**
- Consumes: `convert()` from Task 1; `VideoConverter::convert( array $element ): array` (unchanged).
- Produces: class `ElementorDivi5Converter\Converter\Handlers\ElementskitVideoConverter extends BaseElementorConverter`, registered for `elementskit-video`.

- [ ] **Step 1: Write the failing tests**

Append inside `AddonSettingNamesTest`:

```php
    // -------------------------------------------------------------------------
    // ElementsKit — video
    // -------------------------------------------------------------------------

    private function videoSrc( array $block ): string {
        return (string) ( $block['settings']['video']['innerContent']['desktop']['value']['src'] ?? '' );
    }

    public function test_elementskit_video_defaults_to_youtube_popup_url(): void {
        [ $block, $result ] = $this->convert( 'elementskit-video', [ 'ekit_video_popup_url' => 'https://www.youtube.com/watch?v=VhBl3dHT5SY' ] );

        $this->assertSame( 'divi/video', $block['name'] );
        $this->assertSame( 'https://www.youtube.com/watch?v=VhBl3dHT5SY', $this->videoSrc( $block ) );
        $this->assertStringNotContainsString( 'missing source URL', implode( "\n", $result['report']['warnings'] ) );
    }

    public function test_elementskit_video_reads_vimeo_popup_url(): void {
        [ $block ] = $this->convert( 'elementskit-video', [ 'ekit_video_popup_video_type' => 'vimeo', 'ekit_video_popup_url' => 'https://vimeo.com/42' ] );

        $this->assertSame( 'https://vimeo.com/42', $this->videoSrc( $block ) );
    }

    public function test_elementskit_video_reads_self_hosted_external_url(): void {
        // The external-URL switch defaults to on, so it is absent here.
        [ $block ] = $this->convert( 'elementskit-video', [
            'ekit_video_popup_video_type'  => 'self',
            'ekit_video_self_external_url' => 'https://cdn.example.test/tour.mp4',
        ] );

        $this->assertSame( 'https://cdn.example.test/tour.mp4', $this->videoSrc( $block ) );
    }

    public function test_elementskit_video_reads_self_hosted_media(): void {
        [ $block ] = $this->convert( 'elementskit-video', [
            'ekit_video_popup_video_type'   => 'self',
            'ekit_video_self_url'           => '',
            'ekit_video_player_self_hosted' => [ 'url' => 'https://example.test/tour.mp4', 'id' => 3 ],
        ] );

        $this->assertSame( 'https://example.test/tour.mp4', $this->videoSrc( $block ) );
    }

    public function test_core_video_is_unaffected(): void {
        [ $block ] = $this->convert( 'video', [ 'video_type' => 'hosted', 'hosted_url' => [ 'url' => 'https://example.test/core.mp4' ] ] );

        $this->assertSame( 'https://example.test/core.mp4', $this->videoSrc( $block ) );
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: FAIL for the four `elementskit_video` tests (empty `src`). `test_core_video_is_unaffected` passes.

- [ ] **Step 3: Create the converter**

Create `class-elementskit-video-converter.php`:

```php
<?php

namespace ElementorDivi5Converter\Converter\Handlers;

use ElementorDivi5Converter\Converter\BaseElementorConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Converts ElementsKit Video → divi/video.
 *
 * ElementsKit stores its source under its own names, which the core
 * VideoConverter never read, so the video converted with no URL. This
 * translates them into Elementor's core video settings and delegates:
 *
 *   ekit_video_popup_video_type  youtube (default) | vimeo | self
 *   ekit_video_popup_url         YouTube or Vimeo URL
 *   ekit_video_self_url          switch, default 'yes': use the external URL below
 *   ekit_video_self_external_url self-hosted URL typed in
 *   ekit_video_player_self_hosted self-hosted media-library file
 */
class ElementskitVideoConverter extends BaseElementorConverter {

    private const EKIT_SOURCE_KEYS = [
        'ekit_video_popup_video_type', 'ekit_video_popup_url', 'ekit_video_self_url',
        'ekit_video_self_external_url', 'ekit_video_player_self_hosted',
    ];

    public function convert( array $element ): array {
        $settings = $element['settings'] ?? [];
        $type     = $settings['ekit_video_popup_video_type'] ?? 'youtube';

        $core = array_diff_key( $settings, array_flip( self::EKIT_SOURCE_KEYS ) );

        if ( $type === 'self' ) {
            $url = '';
            if ( ( $settings['ekit_video_self_url'] ?? 'yes' ) === 'yes' ) {
                $url = $settings['ekit_video_self_external_url'] ?? '';
            }
            if ( ! is_string( $url ) || $url === '' ) {
                $media = $settings['ekit_video_player_self_hosted'] ?? [];
                $url   = is_array( $media ) ? ( $media['url'] ?? '' ) : '';
            }
            $core['video_type'] = 'hosted';
            $core['hosted_url'] = [ 'url' => is_string( $url ) ? $url : '' ];
        } elseif ( $type === 'vimeo' ) {
            $core['video_type'] = 'vimeo';
            $core['vimeo_url']  = $settings['ekit_video_popup_url'] ?? '';
        } else {
            $core['video_type']  = 'youtube';
            $core['youtube_url'] = $settings['ekit_video_popup_url'] ?? '';
        }

        $element['settings'] = $core;

        return ( new VideoConverter( $this->engine ) )->convert( $element );
    }
}
```

- [ ] **Step 4: Register it**

In `class-converter-registry.php`, replace:

```php
        $this->registerWidget( 'elementskit-video', '\\ElementorDivi5Converter\\Converter\\Handlers\\VideoConverter' );
```

with:

```php
        $this->registerWidget( 'elementskit-video', '\\ElementorDivi5Converter\\Converter\\Handlers\\ElementskitVideoConverter' );
```

- [ ] **Step 5: Run the new tests, then the full suite**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: PASS (38 tests).

Run: `vendor/bin/phpunit`
Expected: OK, 682 tests, same single pre-existing deprecation.

- [ ] **Step 6: Commit**

```bash
git add tests/AddonSettingNamesTest.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-elementskit-video-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/registry/class-converter-registry.php
git commit -m "fix(converter): give ElementsKit video its own converter

elementskit-video went through the core VideoConverter, which only reads
Elementor's own video settings, so every ElementsKit video converted with no
URL and no warning. The new converter maps ElementsKit's source settings onto
the core ones and delegates.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 6: Header Footer Elementor widgets

Fixes `navigation-menu` (reads `nav_menu`; HFE stores the menu **slug** in `menu`), `copyright` (`copyright_text` → `shortcode`), `hfe-site-title` and `hfe-site-tagline` (`before_title_text` / `after_title_text` / `title_html_tag` → `before` / `after` / `heading_tag`), `hfe-counter` (`end_number`, via the shared `CounterConverter`) and `retina` (`retina_image`, via the shared `HfeSiteLogoConverter`).

**Files:**
- Modify: `tests/bootstrap.php`
- Modify: `tests/AddonSettingNamesTest.php`
- Modify (all in `plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/`): `class-hfe-navigation-menu-converter.php`, `class-hfe-copyright-converter.php`, `class-hfe-site-title-converter.php`, `class-hfe-site-tagline-converter.php`, `class-counter-converter.php`, `class-hfe-site-logo-converter.php`

**Interfaces:**
- Consumes: `convert()` from Task 1.
- Produces: test stubs `get_bloginfo( string $show = '' ): string`, backed by `$GLOBALS['__test_bloginfo']` (`name` => `Ferncourt Test`, `description` => `Coworking for freelancers`), and `wp_get_nav_menu_object( $menu )`, backed by `$GLOBALS['__test_nav_menus']` (slug => term ID; returns `false` for unknown slugs).

- [ ] **Step 1: Add the bootstrap stubs**

In `tests/bootstrap.php`, immediately before:

```php
if ( file_exists( __DIR__ . '/../plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php' ) ) {
```

insert:

```php
// --- site identity and nav menus (HFE converters) ---
if ( ! function_exists( 'get_bloginfo' ) ) {
    $GLOBALS['__test_bloginfo'] = [ 'name' => 'Ferncourt Test', 'description' => 'Coworking for freelancers' ];

    function get_bloginfo( string $show = '' ): string {
        return (string) ( $GLOBALS['__test_bloginfo'][ $show ] ?? '' );
    }
}

if ( ! function_exists( 'wp_get_nav_menu_object' ) ) {
    // slug => term ID. Tests add the menus they need.
    $GLOBALS['__test_nav_menus'] = [];

    function wp_get_nav_menu_object( $menu ) {
        $term_id = $GLOBALS['__test_nav_menus'][ (string) $menu ] ?? null;

        return $term_id === null ? false : (object) [ 'term_id' => (int) $term_id, 'slug' => (string) $menu ];
    }
}

```

Run: `vendor/bin/phpunit`
Expected: OK, 682 tests. (`LicenseClient` already calls `get_bloginfo( 'version' )` behind `function_exists`; the stub returns `''` for it, the same value as before.)

- [ ] **Step 2: Write the failing tests**

Append inside `AddonSettingNamesTest`:

```php
    // -------------------------------------------------------------------------
    // Header Footer Elementor
    // -------------------------------------------------------------------------

    private function menuId( array $block ): string {
        return (string) $block['settings']['menu']['innerContent']['desktop']['value']['menuId'];
    }

    public function test_hfe_navigation_menu_resolves_the_stored_slug_to_a_menu_id(): void {
        $GLOBALS['__test_nav_menus'] = [ 'primary' => 7 ];

        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => 'primary' ] );

        $this->assertSame( '7', $this->menuId( $block ) );
    }

    public function test_hfe_navigation_menu_keeps_a_numeric_menu_id(): void {
        $GLOBALS['__test_nav_menus'] = [];

        [ $block ] = $this->convert( 'navigation-menu', [ 'menu' => '12' ] );

        $this->assertSame( '12', $this->menuId( $block ) );
    }

    public function test_hfe_navigation_menu_legacy_nav_menu_still_converts(): void {
        $GLOBALS['__test_nav_menus'] = [];

        [ $block ] = $this->convert( 'navigation-menu', [ 'nav_menu' => '5' ] );

        $this->assertSame( '5', $this->menuId( $block ) );
    }

    public function test_hfe_copyright_reads_shortcode_and_fills_in_hfe_shortcodes(): void {
        [ $block ] = $this->convert( 'copyright', [ 'shortcode' => 'Copyright © [hfe_current_year] [hfe_site_title]' ] );

        $this->assertSame(
            'Copyright © ' . gmdate( 'Y' ) . ' Ferncourt Test',
            $block['settings']['content']['innerContent']['desktop']['value']
        );
    }

    public function test_hfe_copyright_legacy_text_still_converts(): void {
        [ $block ] = $this->convert( 'copyright', [ 'copyright_text' => 'Old ©' ] );

        $this->assertSame( 'Old ©', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_hfe_site_title_reads_before_after_and_heading_tag(): void {
        [ $block ] = $this->convert( 'hfe-site-title', [ 'before' => 'Welcome to', 'after' => '!', 'heading_tag' => 'h1' ] );

        $this->assertSame( 'Welcome to Ferncourt Test !', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h1', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
    }

    public function test_hfe_site_title_legacy_names_still_convert(): void {
        [ $block ] = $this->convert( 'hfe-site-title', [ 'before_title_text' => 'Hi', 'title_html_tag' => 'h3' ] );

        $this->assertSame( 'Hi Ferncourt Test', $block['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h3', $block['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );
    }

    public function test_hfe_site_tagline_reads_before_and_after(): void {
        [ $block ] = $this->convert( 'hfe-site-tagline', [ 'before' => 'Ferncourt:', 'after' => '(est. 2019)' ] );

        $this->assertSame( 'Ferncourt: Coworking for freelancers (est. 2019)', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_hfe_counter_reads_end_number(): void {
        [ $block ] = $this->convert( 'hfe-counter', [ 'start_number' => 0, 'end_number' => 120, 'suffix' => '+', 'title' => 'Members' ] );

        $this->assertSame( '120+', $block['settings']['number']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Members', $block['settings']['title']['innerContent']['desktop']['value'] );
    }

    public function test_core_counter_is_unaffected(): void {
        [ $block ] = $this->convert( 'counter', [ 'ending_number' => 42 ] );

        $this->assertSame( '42', $block['settings']['number']['innerContent']['desktop']['value'] );
    }

    public function test_hfe_retina_logo_reads_retina_image(): void {
        [ $block ] = $this->convert( 'retina', [ 'retina_image' => [ 'url' => 'https://example.test/logo@2x.png', 'id' => 4 ] ] );

        $this->assertSame( 'https://example.test/logo@2x.png', $block['settings']['image']['innerContent']['desktop']['value']['src'] );
    }
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: FAIL for seven tests: the slug navigation menu test, the numeric navigation menu test, the copyright shortcode test, the site title new-names test, the site tagline test, the HFE counter test and the retina test. The three legacy tests and `test_core_counter_is_unaffected` pass.

- [ ] **Step 4: Fix the navigation menu converter**

In `class-hfe-navigation-menu-converter.php`, replace:

```php
        // `nav_menu` holds the WordPress menu ID (integer stored as string/int).
        $menu_id = $settings['nav_menu'] ?? '';
```

with:

```php
        // HFE 2.x stores the menu's slug in `menu`; Divi's menu module needs the
        // term ID, so resolve it when WordPress can. `nav_menu` (an ID) is kept
        // for older exports.
        $menu_id = $settings['menu'] ?? $settings['nav_menu'] ?? '';
        if ( is_string( $menu_id ) && $menu_id !== '' && ! ctype_digit( $menu_id ) && function_exists( 'wp_get_nav_menu_object' ) ) {
            $menu_object = wp_get_nav_menu_object( $menu_id );
            if ( is_object( $menu_object ) && isset( $menu_object->term_id ) ) {
                $menu_id = (string) $menu_object->term_id;
            }
        }
```

and in the handled-keys list replace `'nav_menu', 'layout',` with `'menu', 'nav_menu', 'layout',`.

- [ ] **Step 5: Fix the copyright converter**

In `class-hfe-copyright-converter.php`, replace:

```php
        $text = is_string( $settings['copyright_text'] ?? '' ) ? ( $settings['copyright_text'] ?? '' ) : '';

        // Resolve the [hfe_current_year] shortcode when running inside WordPress.
        if ( function_exists( 'do_shortcode' ) ) {
            $text = do_shortcode( $text );
        }
```

with:

```php
        $text = $settings['shortcode'] ?? $settings['copyright_text'] ?? '';
        $text = is_string( $text ) ? $text : '';

        // HFE's own shortcodes are filled in here: a migrated site may no longer
        // run HFE, and they would otherwise print as literal text.
        $site_title = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
        $text       = str_replace( [ '[hfe_current_year]', '[hfe_site_title]' ], [ gmdate( 'Y' ), $site_title ], $text );

        if ( function_exists( 'do_shortcode' ) ) {
            $text = do_shortcode( $text );
        }
```

(Match the two statements individually if the blank line differs.) In the handled-keys list replace `'copyright_text', 'link',` with `'shortcode', 'copyright_text', 'link',`.

- [ ] **Step 6: Fix the site title and site tagline converters**

In `class-hfe-site-title-converter.php`, replace:

```php
        $before = is_string( $settings['before_title_text'] ?? '' ) ? ( $settings['before_title_text'] ?? '' ) : '';
        $after  = is_string( $settings['after_title_text'] ?? '' ) ? ( $settings['after_title_text'] ?? '' ) : '';
        $tag    = is_string( $settings['title_html_tag'] ?? '' ) ? ( $settings['title_html_tag'] ?? 'h2' ) : 'h2';
```

with:

```php
        $before = $settings['before'] ?? $settings['before_title_text'] ?? '';
        $before = is_string( $before ) ? $before : '';
        $after  = $settings['after'] ?? $settings['after_title_text'] ?? '';
        $after  = is_string( $after ) ? $after : '';
        $tag    = $settings['heading_tag'] ?? $settings['title_html_tag'] ?? 'h2';
        $tag    = is_string( $tag ) ? $tag : 'h2';
```

and replace its handled-keys line `'before_title_text', 'after_title_text', 'title_html_tag',` with `'before', 'after', 'heading_tag', 'heading_link', 'icon', 'before_title_text', 'after_title_text', 'title_html_tag',`.

In `class-hfe-site-tagline-converter.php`, replace:

```php
        $before = is_string( $settings['before_title_text'] ?? '' ) ? ( $settings['before_title_text'] ?? '' ) : '';
        $after  = is_string( $settings['after_title_text'] ?? '' ) ? ( $settings['after_title_text'] ?? '' ) : '';
```

with:

```php
        $before = $settings['before'] ?? $settings['before_title_text'] ?? '';
        $before = is_string( $before ) ? $before : '';
        $after  = $settings['after'] ?? $settings['after_title_text'] ?? '';
        $after  = is_string( $after ) ? $after : '';
```

and replace its handled-keys line `'before_title_text', 'after_title_text', 'heading_alignment',` with `'before', 'after', 'icon', 'before_title_text', 'after_title_text', 'heading_alignment',`.

- [ ] **Step 7: Fix the shared counter and site logo converters**

In `class-counter-converter.php`, replace:

```php
        $number = (string) ( $settings['ending_number'] ?? $settings['number'] ?? '0' );
```

with:

```php
        // Elementor's counter: ending_number. HFE's counter: end_number.
        $number = (string) ( $settings['ending_number'] ?? $settings['end_number'] ?? $settings['number'] ?? '0' );
```

and replace `'starting_number', 'ending_number', 'number',` in its handled-keys list with `'starting_number', 'ending_number', 'start_number', 'end_number', 'number',`.

In `class-hfe-site-logo-converter.php`, replace:

```php
        // Retina widget uses a plain 'logo' media field.
        $logo = $settings['logo'] ?? null;
```

with:

```php
        // HFE's retina widget stores its image in `retina_image`; `logo` is kept
        // for older exports.
        $logo = $settings['retina_image'] ?? $settings['logo'] ?? null;
```

and replace `'custom_image', 'custom_image_url',` in its handled-keys list with `'custom_image', 'custom_image_url', 'retina_image', 'real_retina',`.

- [ ] **Step 8: Run the new tests, then the full suite**

Run: `vendor/bin/phpunit --filter AddonSettingNamesTest`
Expected: PASS (49 tests).

Run: `vendor/bin/phpunit`
Expected: OK, 693 tests, same single pre-existing deprecation.

- [ ] **Step 9: Commit**

```bash
git add tests/bootstrap.php tests/AddonSettingNamesTest.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-hfe-navigation-menu-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-hfe-copyright-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-hfe-site-title-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-hfe-site-tagline-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-counter-converter.php plugin/jhmg-converter-for-elementor-to-divi/includes/converter/handlers/class-hfe-site-logo-converter.php
git commit -m "fix(converter): read Header Footer Elementor's real setting names

The navigation menu, copyright, site title, site tagline, counter and retina
logo converters read names HFE 2.x never stores, dropping the menu, the
copyright line, the before/after text and heading level, the counter's number
and the retina image. HFE stores a menu's slug, which is now resolved to the
ID Divi needs, and HFE's own copyright shortcodes are filled in. Old names
stay as fallbacks.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 7: A repeatable sweep for unknown setting names

Commits the check that found these bugs. For every widget the registry maps, it lists the setting names its converter reads that the add-on providing the widget never defines. A `??` chain counts as one read and passes when any name in it is defined.

**Files:**
- Create: `scripts/addon-setting-names.php`
- Create: `tests/AddonSettingNamesSweepTest.php`

**Interfaces:**
- Produces: `asn_reads( string $php ): array` (each read as its chain of names, `string[][]`); `asn_registry( string $root ): array` (slug => converter file); `asn_addon_index( string $zip_path ): array` (`['widgets' => slug => true, 'defined' => name => true]`); `addon_setting_name_gaps( string $root, array $zip_paths ): array` (slug => list of undefined reads, each written `a ?? b`).

- [ ] **Step 1: Write the failing tests**

Create `tests/AddonSettingNamesSweepTest.php`:

```php
<?php
// tests/AddonSettingNamesSweepTest.php
use PHPUnit\Framework\TestCase;

/**
 * Converters must not read setting names their add-on never defines — every
 * such name is content a conversion silently drops. See
 * scripts/addon-setting-names.php.
 */
final class AddonSettingNamesSweepTest extends TestCase {

    public static function setUpBeforeClass(): void {
        $script = __DIR__ . '/../scripts/addon-setting-names.php';
        if ( is_file( $script ) ) {
            require_once $script;
        }
    }

    public function test_a_fallback_chain_is_read_as_one_read(): void {
        $php = '<?php $a = $settings[\'eael_new_name\'] ?? $settings[\'eael_old_name\'] ?? \'\'; $b = $item[\'single\'];';

        $this->assertSame( [ [ 'eael_new_name', 'eael_old_name' ], [ 'single' ] ], asn_reads( $php ) );
    }

    public function test_no_converter_reads_a_name_essential_addons_or_hfe_does_not_define(): void {
        $root = dirname( __DIR__ );

        $gaps = addon_setting_name_gaps( $root, [
            $root . '/references/essential-addons-for-elementor-lite.6.6.7.zip',
            $root . '/references/header-footer-elementor.2.8.8.zip',
        ] );

        $this->assertSame( [], $gaps, 'Converters read setting names the add-on never defines: ' . json_encode( $gaps, JSON_PRETTY_PRINT ) );
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter AddonSettingNamesSweepTest`
Expected: FAIL with `Call to undefined function asn_reads()` and `Call to undefined function addon_setting_name_gaps()`.

- [ ] **Step 3: Write the sweep script**

Create `scripts/addon-setting-names.php`:

```php
<?php
/**
 * Add-on setting-name sweep.
 *
 * For every widget the converter registry maps, lists the setting names its
 * converter reads that the add-on providing that widget never defines. Each
 * one is content a conversion silently drops: the widget converts without
 * error and without an unsupported flag, just emptier.
 *
 * A read like `$settings['new'] ?? $settings['old']` counts once and passes
 * when any name in the chain is defined, so fallbacks kept for older add-on
 * versions are not reported. Write fallbacks as `??` chains for this reason.
 *
 * Usage:
 *   php scripts/addon-setting-names.php [addon.zip ...]
 *
 * With no arguments it sweeps the Essential Addons and Header Footer Elementor
 * zips in references/. Pass other add-on zips (ElementsKit Lite, Premium
 * Addons, …) to sweep those instead. Exits 1 when anything is found.
 */

/** Keys a converter reads that are not add-on controls: nested value keys and Elementor internals. */
const ASN_GENERIC = [
    'url', 'id', 'value', 'library', 'size', 'unit', 'sizes', 'is_external', 'nofollow',
    'alt', 'src', 'source', 'custom_attributes', '__globals__', '__dynamic__',
];

/** Names the free add-on source cannot confirm, with the reason. */
const ASN_UNVERIFIABLE = [
    'eael-embedpress'     => [
        'eael_embedpress_url' => "EAEL's element is only an 'install EmbedPress' prompt; embeds live in EmbedPress's own widgets.",
    ],
    'eael-content-ticker' => [
        'eael_ticker_custom_contents' => 'Custom ticker items are an EAEL Pro control.',
        'eael_ct_title'               => 'Per-item name of the EAEL Pro custom ticker; Pro source unavailable.',
        'eael_ct_link'                => 'Per-item name of the EAEL Pro custom ticker; Pro source unavailable.',
    ],
];

/**
 * Names read on purpose for older exports, outside a `??` chain because the
 * old shape needs different handling from the current one.
 */
const ASN_LEGACY = [
    'eael-tooltip'    => [
        'eael_tooltip_trigger_text' => 'Pre-6.x trigger text; its presence selects the legacy branch.',
    ],
    'eael-data-table' => [
        'eael_data_table_body_rows' => 'Pre-6.x nested body rows.',
        'eael_dt_body_col_rows'     => 'Pre-6.x nested body rows.',
        'eael_dt_body_row_value'    => 'Pre-6.x nested body rows.',
        'eael_dt_body_col'          => 'Pre-6.x nested body rows.',
        'eael_dt_body_col_span'     => 'Pre-6.x nested body rows.',
        'eael_dt_body_row_span'     => 'Pre-6.x nested body rows.',
    ],
];

/** @return array<string,string> widget slug => converter source file */
function asn_registry( string $root ): array {
    $converter = $root . '/plugin/jhmg-converter-for-elementor-to-divi/includes/converter/';
    $registry  = (string) file_get_contents( $converter . 'registry/class-converter-registry.php' );

    $class_files = [];
    foreach ( glob( $converter . 'handlers/*.php' ) ?: [] as $file ) {
        if ( preg_match( '/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', (string) file_get_contents( $file ), $m ) ) {
            $class_files[ $m[1] ] = $file;
        }
    }

    preg_match_all( "/registerWidget\(\s*'([a-z0-9_-]+)'\s*,\s*'([^']+)'/", $registry, $matches, PREG_SET_ORDER );

    $map = [];
    foreach ( $matches as [ , $slug, $class ] ) {
        $short = substr( (string) strrchr( '\\' . $class, '\\' ), 1 );
        if ( isset( $class_files[ $short ] ) ) {
            $map[ $slug ] = $class_files[ $short ];
        }
    }

    return $map;
}

/** @return string[][] Every settings read in the source, as the chain of names it tries in order. */
function asn_reads( string $php ): array {
    $access = '\$(?:settings|s|item|tab|row|col|cell|entry|slide|feature|member|link|link_raw|hosted|media|logo|custom)\[\s*\'[a-z0-9_]+\'\s*\]';

    preg_match_all( '/' . $access . '(?:\s*\?\?\s*' . $access . ')*/', $php, $chains );

    $reads = [];
    foreach ( $chains[0] as $chain ) {
        preg_match_all( '/\[\s*\'([a-z0-9_]+)\'\s*\]/', $chain, $keys );
        $reads[] = $keys[1];
    }

    return $reads;
}

/** @return array{widgets: array<string,true>, defined: array<string,true>} */
function asn_addon_index( string $zip_path ): array {
    $zip = new ZipArchive();
    if ( $zip->open( $zip_path ) !== true ) {
        throw new RuntimeException( "Cannot open add-on zip: {$zip_path}" );
    }

    $widgets = [];
    $defined = [];

    for ( $i = 0; $i < $zip->numFiles; $i++ ) {
        if ( ! str_ends_with( (string) $zip->getNameIndex( $i ), '.php' ) ) {
            continue;
        }
        $src = (string) $zip->getFromIndex( $i );

        preg_match_all( '/add_(?:responsive_|group_)?control\(\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $controls );
        preg_match_all( '/[\'"]name[\'"]\s*=>\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $repeater_fields );
        foreach ( array_merge( $controls[1], $repeater_fields[1] ) as $name ) {
            $defined[ $name ] = true;
        }

        if ( preg_match_all( '/function get_name\(\)\s*(?::\s*string\s*)?\{\s*return\s*[\'"]([a-z0-9_-]+)[\'"]/', $src, $slugs ) ) {
            foreach ( $slugs[1] as $slug ) {
                $widgets[ $slug ] = true;
            }
        }
    }

    $zip->close();

    return [ 'widgets' => $widgets, 'defined' => $defined ];
}

/** @return array<string,string[]> widget slug => reads (written "a ?? b") the add-on never defines */
function addon_setting_name_gaps( string $root, array $zip_paths ): array {
    $registry = asn_registry( $root );
    $gaps     = [];

    foreach ( $zip_paths as $zip_path ) {
        $addon = asn_addon_index( $zip_path );

        foreach ( $registry as $slug => $file ) {
            if ( ! isset( $addon['widgets'][ $slug ] ) ) {
                continue;
            }
            $unverifiable = ( ASN_UNVERIFIABLE[ $slug ] ?? [] ) + ( ASN_LEGACY[ $slug ] ?? [] );

            foreach ( asn_reads( (string) file_get_contents( $file ) ) as $chain ) {
                $satisfied = false;
                foreach ( $chain as $name ) {
                    // Responsive variants (title_tablet) are generated from the base control.
                    $base = (string) preg_replace( '/_(?:tablet_extra|mobile_extra|tablet|mobile|laptop|widescreen)$/', '', $name );
                    if ( in_array( $name, ASN_GENERIC, true ) || str_starts_with( $name, '_' )
                        || isset( $addon['defined'][ $name ] ) || isset( $addon['defined'][ $base ] )
                        || isset( $unverifiable[ $name ] ) ) {
                        $satisfied = true;
                        break;
                    }
                }
                if ( ! $satisfied ) {
                    $gaps[ $slug ][] = implode( ' ?? ', $chain );
                }
            }
        }
    }

    foreach ( $gaps as $slug => $reads ) {
        $gaps[ $slug ] = array_values( array_unique( $reads ) );
    }
    ksort( $gaps );

    return $gaps;
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
    $root = dirname( __DIR__ );
    $zips = array_slice( $argv, 1 ) ?: [
        $root . '/references/essential-addons-for-elementor-lite.6.6.7.zip',
        $root . '/references/header-footer-elementor.2.8.8.zip',
    ];

    foreach ( $zips as $zip ) {
        echo 'Swept ', basename( $zip ), "\n";
    }

    $gaps = addon_setting_name_gaps( $root, $zips );
    if ( ! $gaps ) {
        echo "No converter reads a setting name its add-on does not define.\n";
        exit( 0 );
    }

    foreach ( $gaps as $slug => $reads ) {
        echo "\n{$slug}\n";
        foreach ( $reads as $read ) {
            echo "  - {$read}\n";
        }
    }
    exit( 1 );
}
```

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter AddonSettingNamesSweepTest`
Expected: PASS (2 tests).

**If the second test fails**, its message lists the widgets and reads it found. Do not add them to `ASN_UNVERIFIABLE` and do not change converters to make it pass. Stop and report the list: it means either a converter missed in Tasks 1–6 or a false positive in the sweep, and deciding which is the user's call.

- [ ] **Step 5: Run the script from the command line, including ElementsKit Lite**

Run: `php scripts/addon-setting-names.php; echo "exit=$?"`
Expected: `Swept essential-addons-for-elementor-lite.6.6.7.zip`, `Swept header-footer-elementor.2.8.8.zip`, `No converter reads a setting name its add-on does not define.`, `exit=0`.

Then sweep ElementsKit Lite, which is not in `references/`:

```bash
EKIT_DIR=$(mktemp -d) && curl -sSL -o "$EKIT_DIR/elementskit-lite.4.0.5.zip" https://downloads.wordpress.org/plugin/elementskit-lite.4.0.5.zip && php scripts/addon-setting-names.php "$EKIT_DIR/elementskit-lite.4.0.5.zip"; echo "exit=$?"
```

Expected: `elementskit-video` is not listed. Other ElementsKit widgets may be listed. Copy the full output into your task report without changing any converter; ElementsKit fixes beyond video are outside this plan.

- [ ] **Step 6: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: OK, 695 tests, same single pre-existing deprecation.

- [ ] **Step 7: Commit**

```bash
git add scripts/addon-setting-names.php tests/AddonSettingNamesSweepTest.php
git commit -m "test(converter): sweep for setting names add-ons never define

Every converter read of a setting name the add-on doesn't define is content
a conversion drops without an error or an unsupported flag. This sweep found
the names fixed on this branch; the test keeps Essential Addons and Header
Footer Elementor at zero, and the script sweeps any other add-on zip.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 8: Final verification

**Files:** none changed.

- [ ] **Step 1: Lint every changed or new PHP file**

```bash
git diff --name-only 21f94c4..HEAD -- '*.php' | xargs -n1 php -l
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 2: Run the full suite one last time**

Run: `vendor/bin/phpunit`
Expected: OK, 695 tests, and the only issue is the single pre-existing PHPUnit deprecation.

- [ ] **Step 3: Confirm the branch history**

Run: `git log --oneline 21f94c4..HEAD`
Expected: eight commits, the plan followed by one commit per Task 1–7, in order.

- [ ] **Step 4: Report**

Report to the user:
- the 21 widgets fixed, grouped as in the tasks (Contact Form 7's class-loading fix called out on its own)
- the ElementsKit Lite sweep output from Task 7 Step 5
- follow-ups outside this worktree: remove the "ElementsKit video converts to an empty video module" section from `docs/known-issues.md` in the main checkout once this branch merges, and rebase `demo-site` onto this branch before building the demo
