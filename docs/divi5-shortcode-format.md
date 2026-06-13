# Divi 5 Block Format

This document describes the WordPress block format produced by `DiviBlockSerializer`.

## Overview

Divi 5 stores page content as **WordPress blocks** (Gutenberg comment-delimiter format), NOT the classic Divi 4 shortcodes. The `DiviBlockSerializer` class converts our internal Divi structure into this format.

The old `DiviShortcodeSerializer` (Divi 4 shortcodes) has been superseded. `DiviExporter` now uses `DiviBlockSerializer`.

## Block format

Each block uses the standard WordPress block comment syntax:

```
<!-- wp:divi/BLOCK_TYPE { JSON_ATTRS } -->...children...<!-- /wp:divi/BLOCK_TYPE -->
```

Self-closing (leaf) modules omit the inner content delimiter:

```
<!-- wp:divi/BLOCK_TYPE { JSON_ATTRS } /-->
```

### Attribute pattern

All responsive attribute values use the `{"desktop":{"value":...}}` pattern. Content attributes are nested one level deeper under `innerContent`:

```json
{
  "content": {
    "innerContent": {
      "desktop": {
        "value": "<p>Hello World</p>"
      }
    }
  }
}
```

## Supported modules

| Internal name   | Block name        | Key attribute path                              |
|-----------------|-------------------|-------------------------------------------------|
| `divi/section`  | `divi/section`    | `{}` (empty JSON, structural wrapper)           |
| `divi/row`      | `divi/row`        | `{}` (empty JSON, structural wrapper)           |
| `divi/column`   | `divi/column`     | `{}` (empty JSON, structural wrapper)           |
| `divi/text`     | `divi/text`       | `content.innerContent.desktop.value` (HTML)     |
| `divi/button`   | `divi/button`     | `button.innerContent.text/linkUrl.desktop.value` |
| `divi/image`    | `divi/image`      | `image.innerContent.src.desktop.value`          |

## Example output

```
<!-- wp:divi/section {} -->
<!-- wp:divi/row {} -->
<!-- wp:divi/column {} -->
<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<h2>Hello World</h2>"}}}} /-->
<!-- wp:divi/button {"button":{"innerContent":{"text":{"desktop":{"value":"Click Here"}},"linkUrl":{"desktop":{"value":"https://example.com"}}}}} /-->
<!-- wp:divi/image {"image":{"innerContent":{"src":{"desktop":{"value":"https://example.com/img.jpg"}}}}} /-->
<!-- /wp:divi/column -->
<!-- /wp:divi/row -->
<!-- /wp:divi/section -->
```

## Detection

Divi 5's `BlockParser::has_any_divi_block()` detects Divi content by checking for:
- `"desktop":{"value"` pattern in the JSON attributes, **or**
- `<!-- wp:divi` prefix

Both are present in the output produced by `DiviBlockSerializer`.

## Usage

`DiviExporter` uses `DiviBlockSerializer` to populate:

- `post_content` (the canonical WordPress content field — Divi 5 renders from here)
- `_et_pb_old_content` (kept in sync with `post_content`)
- `_et_pb_use_builder` = `on`
- `_et_builder_version` = `VB|Divi|x.y.z`
