# Conversion Map

This mapping describes how Elementor page structure and widgets should convert into Divi 5 modules.

| Elementor source | Divi 5 target | Notes |
| --- | --- | --- |
| `section` | `divi/section` | Section becomes top-level Divi section container |
| `column` | `divi/column` | Elementor column wrappers become Divi columns |
| `container` | `divi/section` / `divi/row` | Elementor container structure maps into Divi section/row nesting |
| `widget` + `e-heading` | `divi/text` or `divi/richtext` | Heading content maps to Divi text module; preserve heading level in settings |
| `widget` + `e-paragraph` | `divi/text` | Paragraph/text maps to Divi text module |
| `widget` + `e-image` | `divi/image` | Image module with `src`, `alt`, and image size settings |
| `widget` + `e-button` | `divi/button` | Button module with label, link, and button settings |

## Mapping Notes

- Elementor page structure should be converted from nested sections/columns to Divi section/row/column trees.
- Elementor widget `settings` should be translated into Divi module `settings` fields rather than rendered HTML.
- Text-based widgets should be converted into Divi text/richtext modules, preserving tag level and inline content values.
- Image widgets should map into Divi image modules using the source URL and alt text from the Elementor image prop.
- Button widgets should map into Divi button modules with link destinations, text label, and optionally HTML tag.

## Example Mappings

### Heading
Elementor heading:
- `widgetType`: `e-heading`
- `settings.title`
- `settings.tag`

Divi target:
- `name`: `divi/text`
- `settings.innerContent` or `settings.content`
- preserve heading level in Divi text settings or richtext formatting

### Text
Elementor paragraph:
- `widgetType`: `e-paragraph`
- `settings.paragraph`
- `settings.tag`

Divi target:
- `name`: `divi/text`
- `settings.innerContent`
- map paragraph text and tag to Divi text module content and element type if available

### Image
Elementor image:
- `widgetType`: `e-image`
- `settings.image.src.url`
- `settings.image.src.alt`
- `settings.image.size`

Divi target:
- `name`: `divi/image`
- `settings.image` or `settings.src`
- `settings.link` for image links

### Button
Elementor button:
- `widgetType`: `e-button`
- `settings.text`
- `settings.link`
- `settings.tag`

Divi target:
- `name`: `divi/button`
- `settings.text` or `settings.buttonText`
- `settings.link` / `settings.url`
- preserve button label and URL target attributes
