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

## Widget mappings changed in free 3.0.2

Every attribute path below is declared in the module's `module.json` or `conversion-outline.json` under `references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/` and read by the server renderer named; `tests/support/DiviModuleSchema.php` checks every emitted block against that extract.

| Elementor source | Divi 5 target | Notes |
| --- | --- | --- |
| `column` with a background | `divi/column` | Background, overlay and padding stay on the column (`module.decoration.background`, overlay as `css.*.before`); the section's `column_position` sets the row's `alignItems` |
| `heading` with `header_size` `span`, `p` or `div` | `divi/text` | Content wrapped in the same tag; typography on `content.decoration.bodyFont.body.font` |
| `button` | `divi/button` | Elementor's defaults (accent background, white 15px text, 12px 24px padding, 3px radius) and the kit's Theme Style → Buttons fill whatever the widget left unset |
| `counter` | `divi/number-counter` | Bare number in `number.innerContent`; `number.advanced.enablePercentSign` on only for a `%` suffix; other affixes reported |
| `image-carousel` | `divi/gallery` | `image.advanced.galleryIds`, `module.advanced.postsNumber` = image count, a grid of `slides_to_show` columns or `module.advanced.fullwidth` for one slide; inline images when no attachment resolves |
| `image-gallery`, `eael-filterable-gallery` | `divi/gallery` | Attachments by ID, every image on one page, EAEL's column count; filters, names and captions reported |
| `social-icons` | `divi/social-media-follow` | Network from the `social_icon` class; brand variants mapped; unknown networks reported |
| `navigation-menu` (HFE), `eael-simple-menu` | `divi/menu` | `menu.advanced.menuId`; transparent background |
| `eael-info-box`, `eael-flip-box`, `icon-box` | `divi/blurb` | `title.innerContent.text`, `content.innerContent`, `imageIcon.innerContent` `{useIcon, icon{type,unicode,weight}}` or `{src}` |
| `icon` | `divi/icon` | `icon.innerContent` is the icon object |
| `eael-pricing-table` | `divi/pricing-tables` > `divi/pricing-table` | `title`, `subtitle`, `currencyFrequency{currency,per}`, `price`, `content` (one feature per line, `-` = excluded), `button{text,linkUrl}`, `module.advanced.featured` |
| `eael-team-member` | `divi/team-member` | `name`, `position`, `content`, `image.innerContent.url`, `social.innerContent.{facebook,twitter,google,linkedin}Url` |
| `eael-testimonial`, `testimonial` | `divi/testimonial` | `content`, `author`, `jobTitle`, `portrait.innerContent.src` |
| `eael-countdown` | `divi/countdown-timer` | `content.advanced.dateTime` in `Y-m-d H:i` |
| `eael-progress-bar` | `divi/counters` > `divi/counter` | The slider's `size`, default 50 |
| `eael-cta-box` | `divi/cta` | Body = subtitle + `eael_cta_content` |
| `elementskit-heading` | `divi/text` + `divi/heading` | The subtitle precedes the heading when shown |
| `eael-post-grid`, `posts`, `hfe-basic-posts`, `eael-post-timeline` | `divi/blog` | `post.advanced.number`, `.type`, `.categories`; other filters reported |
| `eael-feature-list`, `price-list`, `eael-content-ticker` | `divi/icon-list` > `divi/icon-list-item` | `content.innerContent`, icon object in `icon.innerContent`, `module.advanced.link` |
| `video`, `eael-sticky-video` | `divi/video` | `video.innerContent.src`; poster in `thumbnail.innerContent.src` |
| `image` spacing | `divi/image` | Margins and padding under `module.advanced.spacing` |
| `eael-contact-form-7` | `divi/contact-form-7` | `form.advanced.formId` |
| custom CSS (all modules) | `css.*.mainElement` | Divi reads `mainElement`, `before`, `after`, `freeForm`; `main` was never read |
