# Conversion Workflow

## Overview

This document describes the end-to-end workflow for converting an Elementor page to a new Divi 5 page within the same WordPress installation.

## Architecture

```
Elementor page (post with _elementor_data meta)
        ↓
  ConverterEngine  (PHP — reads elType/widgetType, dispatches to handlers)
        ↓
  DiviBlockSerializer  (PHP — produces <!-- wp:divi/... --> block strings)
        ↓
  DiviExporter  (PHP — writes post_content + Divi meta to WP database)
        ↓
New Divi 5 page (published page with Divi 5 block content)
```

## Source Requirements

The source WordPress page must have:
- `_elementor_data` post meta containing a valid Elementor JSON array

## Running a Conversion

### Manual (WP-CLI)

```bash
# 1. Copy the conversion script into the container once.
docker cp scripts/docker/convert-to-new-page.php <container_id>:/tmp/

# 2. Run the conversion — outputs the new page ID.
SOURCE_PAGE_ID=<id> wp eval-file /tmp/convert-to-new-page.php --allow-root
```

### From Playwright tests

The E2E spec `tests/e2e/conversion-workflow.spec.ts` automates this end-to-end:
it creates a source page, attaches fixture data, runs the conversion, then
validates the rendered Divi 5 output in the browser.

## Output Page

After conversion the new page has the following WordPress meta:

| Key | Value |
|---|---|
| `post_content` | Divi 5 block format (`<!-- wp:divi/section ... -->`) |
| `_et_pb_use_builder` | `on` |
| `_et_builder_version` | `VB\|Divi\|5.x.x` |
| `_et_pb_old_content` | Same as `post_content` |
| `_et_pb_use_divi_5` | `1` |
| `_edc_divi_data` | JSON of the intermediate converted structure |

## Supported Element Mappings

| Elementor type | Divi 5 block |
|---|---|
| `section` | `divi/section` + auto-wrapped `divi/row` |
| `column` | `divi/column` |
| `container` | `divi/section` |
| `e-heading` | `divi/text` (with `tagName`) |
| `e-paragraph` | `divi/text` |
| `e-image` | `divi/image` |
| `e-button` | `divi/button` |

Unsupported widget types are logged in `converted['unsupported']` and skipped during serialization.

## Divi 5 Block Format

Each leaf module is a self-closing WordPress block comment:

```html
<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<h1>Welcome</h1>"}}}} /-->
<!-- wp:divi/button {"button":{"innerContent":{"desktop":{"value":{"text":"Get Started","linkUrl":"https://example.com"}}}}} /-->
<!-- wp:divi/image {"image":{"innerContent":{"desktop":{"value":{"src":"https://...","alt":"Team"}}}}} /-->
```

Structural blocks wrap their children:

```html
<!-- wp:divi/section {} -->
  <!-- wp:divi/row {} -->
    <!-- wp:divi/column {} -->
      ...modules...
    <!-- /wp:divi/column -->
  <!-- /wp:divi/row -->
<!-- /wp:divi/section -->
```

## Test Fixtures

The realistic multi-section hero page fixture lives at:

```
fixtures/elementor/hero-page.json
```

It contains:
- **Hero section** — heading (`h1`) + paragraph + button
- **About section** — two columns: text left, image right
- **CTA section** — heading (`h2`) + button

## Running E2E Tests

```bash
# Full suite (fixture unit tests)
npx playwright test tests/e2e/divi-integration.spec.ts

# Conversion workflow test
npx playwright test tests/e2e/conversion-workflow.spec.ts
```
