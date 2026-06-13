# Elementor Schema

Elementor page structure is stored as JSON in the WordPress post meta key `_elementor_data`.
This data is the canonical source for the builder tree and is loaded by `Elementor\Core\Base\Document::get_elements_data()`.

## Page JSON Structure

The `_elementor_data` meta value is a JSON-encoded array of top-level elements.
Each element has a structure similar to:

```json
{
  "id": "element-1",
  "elType": "section",
  "settings": { /* element settings */ },
  "elements": [ /* child elements */ ]
}
```

Top-level elements are typically:
- `section` (default layout)
- `container` (when the container experiment is active)

## Element Structure

Every element saved by Elementor includes:
- `id`: unique element identifier
- `elType`: element type, such as `section`, `column`, `container`, or `widget`
- `settings`: widget/container configuration values
- `elements`: nested children
- `isInner`: internal editor flag for inner elements
- `widgetType`: present on widgets and identifies the widget class

The Elementor base element saves raw data via `get_raw_data()` and save-ready data via `get_data_for_save()`.
Widgets extend element behavior and add the `widgetType` field.

## Containers, Sections, and Columns

Elementor uses two main tree shapes:

1. Section-based layout

```json
[
  {
    "id": "section-1",
    "elType": "section",
    "elements": [
      {
        "id": "column-1",
        "elType": "column",
        "elements": [
          { "id": "widget-1", "elType": "widget", "widgetType": "e-heading" }
        ]
      }
    ]
  }
]
```

2. Container-based layout

```json
[
  {
    "id": "container-1",
    "elType": "container",
    "elements": [
      { "id": "widget-1", "elType": "widget", "widgetType": "e-heading" }
    ]
  }
]
```

Columns are child wrappers inside sections or nested rows.

## Widget Storage Format

Widget elements are stored as JSON elements with a `widgetType` property.
For example, a heading widget may look like:

```json
{
  "id": "widget-1",
  "elType": "widget",
  "widgetType": "e-heading",
  "settings": {
    "title": {
      "$$type": "string",
      "value": "Hello World"
    },
    "tag": {
      "$$type": "string",
      "value": "h2"
    }
  },
  "elements": []
}
```

Widget settings are often prop objects with `$$type` and `value`.
Common widget settings shapes include:
- `title` / `paragraph` / `text`: textual content fields
- `tag`: HTML tag selection
- `link`: link object with `destination`, `isTargetBlank`, and `tag`
- `image`: image object with `src`, `size`, and nested `alt`

## Common Elementor Widgets

### Heading
- `widgetType`: `e-heading`
- key settings: `title`, `tag`, `link`

### Paragraph / Text
- `widgetType`: `e-paragraph`
- key settings: `paragraph`, `tag`, `link`

### Image
- `widgetType`: `e-image`
- key settings: `image` (with `src` and `size`), `link`

### Button
- `widgetType`: `e-button`
- key settings: `text`, `link`, `tag`

## Notes from Source

Elementor stores page layout as a nested tree, not HTML.
Widgets are represented as JSON objects in the tree, and styles or advanced props are stored inside `settings` objects.
The core Elementor document loader reconstructs this tree from `_elementor_data` and renders it using widget definitions.
