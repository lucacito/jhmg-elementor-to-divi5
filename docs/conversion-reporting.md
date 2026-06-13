# Conversion Reporting

## Overview

Every conversion produces a structured report stored as `_edc_conversion_report` post meta on the new Divi page. The report lets you audit what was converted, what was skipped, and what triggered warnings.

## Report Structure

```json
{
  "converted": {
    "section": 4,
    "column": 7,
    "heading": 6,
    "text": 3,
    "image": 2,
    "button": 3
  },
  "warnings": [
    "Empty column after conversion: stress-col-3",
    "Image missing alt text: stress-image-noalt"
  ],
  "skipped_settings": [
    "stress-section-1: background_color",
    "stress-section-1: background_image",
    "stress-section-1: custom_padding"
  ],
  "unsupported": [
    { "id": "stress-carousel", "elType": "widget", "widgetType": "e-carousel" },
    { "id": "stress-form",     "elType": "widget", "widgetType": "e-form" },
    { "id": "stress-video",    "elType": "widget", "widgetType": "e-video" }
  ]
}
```

## Fields

| Field | Description |
|---|---|
| `converted` | Count per converted element type (`heading`, `text`, `image`, `button`, `section`, `column`) |
| `warnings` | Non-fatal issues: empty containers, images without alt text |
| `skipped_settings` | Elementor settings that are preserved in `module` but not yet mapped to Divi attributes |
| `unsupported` | Elementor widget types with no registered converter; these elements are silently dropped |

## Skipped Settings

The following Elementor layout settings are stored in the converted structure but not yet serialized to Divi 5 block attributes:

| Setting | Notes |
|---|---|
| `background_color` | Section/column background colour |
| `background_image` | Section/column background image URL |
| `background_video_link` | Section background video |
| `custom_padding` | Section/column inner padding |
| `custom_margin` | Section/column outer margin |

These are logged in `skipped_settings` so they can be targeted in future conversion passes without losing the original data.

## Reading the Report

### Via WP-CLI

```bash
wp post meta get <page_id> _edc_conversion_report --allow-root
```

### Via PHP

```php
$raw    = get_post_meta( $page_id, '_edc_conversion_report', true );
$report = json_decode( $raw, true );

// Summary output
foreach ( $report['converted'] as $type => $count ) {
    echo "{$type}: {$count}\n";
}
foreach ( $report['unsupported'] as $item ) {
    echo "Unsupported: {$item['widgetType']}\n";
}
foreach ( $report['warnings'] as $warning ) {
    echo "Warning: {$warning}\n";
}
```

## Unsupported Widget Behaviour

Unsupported widgets are logged in the report and **silently dropped** from the Divi output. The surrounding layout (section, row, column) is still created. If a column contains _only_ unsupported widgets, it converts to an empty Divi column, which Divi renders as a blank column without breaking the page layout. A warning is added for each empty column so they can be reviewed.

## Running the Tests

```bash
# PHPUnit: unit tests for reporting logic
./vendor/bin/phpunit tests/ConversionReportTest.php

# Playwright: end-to-end validation with the stress-test fixture
npx playwright test tests/e2e/conversion-workflow.spec.ts
```
