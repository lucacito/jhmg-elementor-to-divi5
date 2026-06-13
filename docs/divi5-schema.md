# Divi 5 Schema

Divi 5 stores page layouts as a module tree with structured module metadata.
Each module is represented by a name such as `divi/section`, `divi/row`, `divi/column`, `divi/text`, `divi/image`, or `divi/button`.

## Divi 5 Layout Format

Divi pages are built from nested modules rather than raw HTML.
A typical tree looks like:

```json
{
  "id": "section-1",
  "name": "divi/section",
  "settings": { /* section-level settings */ },
  "elements": [
    {
      "id": "row-1",
      "name": "divi/row",
      "settings": { /* row-level settings */ },
      "elements": [
        {
          "id": "column-1",
          "name": "divi/column",
          "settings": { /* column settings */ },
          "elements": [
            {
              "id": "module-1",
              "name": "divi/text",
              "settings": { /* text module values */ }
            }
          ]
        }
      ]
    }
  ]
}
```

Divi metadata shows that modules are configured with:
- `name`: module identifier (`divi/section`, `divi/row`, `divi/column`, etc.)
- `settings`: content, design, advanced and layout grouping
- `attributes`: the module’s CSS, style, and structural attributes
- `childModuleName` / `childrenName`: the valid child module type(s)

## Container Hierarchy

The Divi structure is generally:
- `divi/section`
  - `divi/row`
    - `divi/column`
      - content modules (`divi/text`, `divi/image`, `divi/button`, etc.)

There are also inner variants such as `divi/row-inner` and `divi/column-inner`.

## Divi 5 Modules

Divi 5 metadata includes modules for:
- `divi/section` — top-level section container
- `divi/row` / `divi/row-inner` — layout rows inside sections
- `divi/column` / `divi/column-inner` — column wrappers inside rows
- `divi/text` / `divi/richtext` — text content modules
- `divi/image` / `divi/module-image` — image modules
- `divi/button` / `divi/module-button` — button modules

## Visible Module Data Patterns

Divi module metadata defines style groups for:
- layout
- spacing
- sizing
- decoration
- background
- border
- box shadow
- attributes
- advanced behavior

Content modules frequently use nested settings groups, such as `innerContent` for text modules or `link` for button/image modules.

## Notes from Source

The metadata file `_all_modules_metadata.php` shows that Divi modules are defined as objects with a `name`, `category`, `moduleIcon`, `attributes`, and `settings` schema.
`childModuleName` indicates valid nested modules for structure modules like `divi/section` and `divi/row`.
