# Known issues

## Header Footer Elementor templates never reach the Divi Theme Builder

Found 2026-09-15 by reading the code while planning the demo site. Not yet reproduced in a running WordPress.

**Symptom.** A site that builds its header and footer with Header Footer Elementor (HFE) has no way to turn them into Divi Theme Builder layouts, even with Pro. The readme lists HFE as a supported add-on.

**Why.**

- HFE stores templates as the `elementor-hf` post type, with `ehf_template_type` meta set to `type_header` or `type_footer`. The installed-page picker only queries `page`, `post` and `elementor_library` (`includes/admin/class-elementor-page-repository.php:37`), so HFE templates never appear.
- Elementor's template export only works on `elementor_library` posts. An HFE user has to "Save as template" first, and free Elementor saves it as type `page`. The exported JSON therefore carries `"type": "page"`.
- Pro's Header/Footer JSON upload (`class-kit-page.php`, `handle_upload_kit`) sets `convert_headers` / `convert_footers`, but `ConversionCommitter::commit()` only routes an item to the Theme Builder when its own `template_type` is `header` or `footer` (`includes/conversion/class-conversion-committer.php:49`). An upload typed `page` becomes a draft page.
- `ElementorImportParser::isHeaderTemplateType()` matches `hfe-template`, a value HFE does not use, and `isFooterTemplateType()` has no HFE entry at all.

**Suggested fix.**

1. List `elementor-hf` posts in the installed-page picker and map `ehf_template_type` to `header` / `footer` in `InstalledPostSource`.
2. Make Pro's Header/Footer upload apply the chosen slot as the item's `template_type` instead of relying on the JSON's `type`.
3. Replace `hfe-template` in the parser with the real HFE values, and add the footer case.

Cover with `InstalledPostSourceTest` and `HeaderTemplateConversionTest`.

## Essential Addons progress bar converts to "Array" or "0"

Found 2026-09-15 while planning the demo site. Reproduced against the converter with a scratch PHPUnit test.

**Symptom.** An EAEL Progress Bar converts to a Divi bar counter whose percentage is the literal text `Array` (and PHP logs "Array to string conversion"), or `0` when the bar was left at its default.

**Why.**

- EAEL 6.6.7 registers `progress_bar_value` as a `SLIDER` control (`includes/Elements/Progress_Bar.php:214`), so a saved value is stored as `{"unit":"%","size":72,"sizes":[]}`. EAEL itself renders `$settings['progress_bar_value']['size']`.
- `EaelProgressBarConverter::convert()` does `(string) ( $settings['progress_bar_value'] ?? $settings['progress_bar_value_dynamic'] ?? '0' )` (`includes/converter/handlers/class-eael-progress-bar-converter.php:23`). Casting the array yields `Array`.
- Elementor does not store default control values, so an untouched bar has no `progress_bar_value` key at all, and the converter falls back to `0` instead of EAEL's default of 50.

**Suggested fix.** Read `progress_bar_value['size']` when the value is an array, keep plain scalars as a legacy fallback, and default to `50` when the key is absent and `progress_bar_value_type` is `static` or unset. Use `progress_bar_value_dynamic` only when `progress_bar_value_type` is `dynamic`.

Cover in `AddonSettingNamesTest` next to the other EAEL cases: a saved slider, an absent key, and a legacy scalar.

## Essential Addons Call to Action loses its body text

Found 2026-09-15 while planning the demo site, by reading the converter. Not yet reproduced in a running WordPress.

**Symptom.** An EAEL Call to Action converts with its title, subtitle and button, but the paragraph of body text is gone.

**Why.** EAEL 6.6.7 defines and renders both `eael_cta_sub_title` and `eael_cta_content` (`includes/Elements/Cta_Box.php`, controls at lines 443 and 538). `EaelCtaBoxConverter::convert()` (`includes/converter/handlers/class-eael-cta-box-converter.php`) reads only `eael_cta_sub_title` into the Divi CTA's `content`. `eael_cta_content` is never read, so it only surfaces in the report's skipped settings, not as a warning.

**Suggested fix.** Build the Divi CTA body from the subtitle followed by `eael_cta_content`, and add `eael_cta_content` to the handled keys. Cover in `AddonSettingNamesTest`.

## Converted modules that keep their content but render blank or wrong in Divi 5.7.4

Found 2026-09-15 by the demo site's before/after screenshots (`demo/output/screenshots/` on branch `demo-site`). Every item below passes the demo's content check — the text or value is present in the converted blocks — so the attributes are written, but Divi does not render them where the converter put them. Causes not yet investigated; each needs a look at the block attributes the handler writes against Divi 5.7.4's module definition.

| Elementor widget | What Divi shows | Page |
|---|---|---|
| `eael-info-box` | Nothing: the section is empty | Home |
| `eael-flip-box` | Nothing: the section is empty | Spaces |
| `eael-pricing-table` | Three empty grey boxes: no title, price, features or button | Memberships |
| `eael-team-member` | Name only: no photo, job title or description | About |
| `eael-testimonial` | Quote only: no photo, name or role | Home |
| `counter` | A `%` sign after every number (`240+` shows as `233%`, `58%` as `58%%`); titles near-invisible on a dark section | Home, About |
| `eael-countdown` | `000:00:00:00`: the due date is not applied | Events |
| `eael-filterable-gallery` | Every image in the media library, paginated, captioned with file names: the images are written as `src` only, and a Divi gallery with no image IDs falls back to all attachments | Spaces |
| `google_maps` | Blank space where the map embed should be | Contact |
| `social-icons` | Facebook icons in place of Instagram and LinkedIn; missing entirely on Contact | Header/footer, Contact |
| HFE `navigation-menu` (Theme Builder) | Menu on a white bar | Header, footer |
| `button` (Theme Builder header) | Blue text on the terracotta background | Header |

Also found the same day converting Elementor's free **Ceramic Studio** kit (Kit Library, core widgets only, sections and columns, built with Elementor 3.7.2). All five pages passed the dry run with no errors, unsupported widgets or warnings, yet:

| Elementor widget | What Divi shows |
|---|---|
| Column with a background image (`background_image`, `background_size: cover`) | A strip about 12 px tall. The converter moves the background onto an empty `divi/group` inside the Divi column instead of onto the column, and the empty group has no height; in Elementor the column stretched to the row's height |
| `heading` with large custom typography | Small default-size text. The size (`12vw`), weight, letter spacing and colour are written to `title.decoration.font`, but the element is emitted with `headingLevel: "span"` (Elementor's `header_size: span`), which Divi does not appear to style |
| `button` with a background colour | A white outline button with near-invisible text: background colour lost |
| `image-carousel` | A row of thumbnails about 40 px wide |

Rendered correctly in the same run: core heading, text editor, image, tabs, image box, button, ElementsKit dual button, heading, testimonial, video and accordion, EAEL data table, advanced accordion, advanced tabs, post grid, CTA box and Contact Form 7, and Premium Addons blog.

**Suggested next step.** Add a render-level check next to the content check — convert a fixture per widget, render it through Divi, and assert the text appears in the HTML — then fix the handlers it flags.

## Pro saves the header and footer as two default Theme Builder templates

Found 2026-09-15 while building the demo site. Reproduced in WordPress with Divi 5.7.4.

**Symptom.** After importing both a header and a footer, Divi shows only one of them. The body classes show the other area as disabled (`et-tb-footer-disabled`).

**Why.** `DiviThemeBuilderExporter::saveHeader()` and `saveFooter()` (Pro, `includes/exporters/class-divi-theme-builder-exporter.php`) each create their own `et_template` post marked `_et_default = 1`, one carrying only `_et_header_layout_*` meta and the other only `_et_footer_layout_*`. Divi resolves a request to a single template (`$request->get_template()` in `et_theme_builder_get_template_layouts()`, `theme-builder.php`), so two default templates never combine: whichever Divi picks leaves the other area disabled.

Worse, each template leaves the areas it does not set with no meta at all. Divi reads a missing `_et_<area>_layout_id` with a missing `_et_<area>_layout_enabled` as "override this area and hide it" (`'override' => 0 !== $id || false === $enabled` in `et_theme_builder_get_template()`), so after a header import the page body itself is hidden on every page.

**Suggested fix.** Keep one default template: the second save should find the existing default template (by `_edc_tb_source` or `_et_default`) and add its layout to it instead of creating a new one. Write `_et_body_layout_id = 0` and `_et_body_layout_enabled = '1'` (and the same for whichever of header or footer is not set) so untouched areas keep rendering. Cover with a Theme Builder exporter test that saves a header then a footer and asserts one template carries both layout IDs with the body enabled.

## Header Footer Elementor still active under Divi crashes every page once a Theme Builder header exists

Found 2026-09-15 while building the demo site. Reproduced in WordPress 6.9 with Divi 5.7.4 and HFE 2.8.8; not a converter bug, but converter users hit it.

**Symptom.** After converting a site that used Header Footer Elementor, switching to Divi and importing a header with Pro, every front-end page returns 500: `Uncaught Error: Call to a member function do_action() on array` in `wp-includes/plugin.php`, from `wp_head()` in Divi's `frontend-header-template.php`.

**Why.** Divi's `et_theme_builder_frontend_override_partial()` (`theme-builder/frontend.php`) takes `$wp_filter['wp_head']` out, buffers the theme's header, then puts it back. HFE's compatibility layer for unsupported themes also hooks `get_header` and calls `remove_all_actions( 'wp_head' )` inside that window, so Divi restores an empty array instead of a `WP_Hook` and `do_action( 'wp_head' )` fatals. With HFE deactivated the pages render.

**Suggested fix.** In the converter's post-conversion guidance (and in Pro's header import screen), tell users to deactivate Header Footer Elementor once their Divi Theme Builder header is in place, or detect HFE active with a Theme Builder header and show an admin notice.

## Essential Addons pricing table drops its subtitle

Found 2026-09-15 while building the demo site: the demo's offline conversion harness reported the subtitle as lost.

**Symptom.** An EAEL Pricing Table converts with its title, price, features and button, but the subtitle under the title is gone.

**Why.** EAEL 6.6.7 defines and renders `eael_pricing_table_sub_title`. `EaelPricingTableConverter::convert()` (`includes/converter/handlers/class-eael-pricing-table-converter.php`) never reads it, so it only surfaces in the report's skipped settings.

**Suggested fix.** Carry the subtitle into the Divi pricing table (its subheading field, or prepended to the body if there is none), add it to the handled keys, and cover it in `AddonSettingNamesTest`.

## ElementsKit heading drops its subtitle

Found 2026-09-15 while building the demo site: the demo's offline conversion harness reported the subtitle as lost.

**Symptom.** An ElementsKit Heading with a subtitle (`ekit_heading_sub_title_show` on) converts to a Divi heading, plus a text block for the extra title when there is one, but the subtitle text is gone.

**Why.** `ElementskitHeadingConverter::convert()` (`includes/converter/handlers/class-elementskit-heading-converter.php`) reads `ekit_heading_sub_title` into `$sub_title` (line 25) and lists it as handled, but never writes it into any block. Because it is marked handled, it does not even appear in the report's skipped settings.

**Suggested fix.** When `ekit_heading_sub_title_show` is `yes`, emit the subtitle as its own block above the heading (ElementsKit renders it above the title by default), or include it in the extra-title text block. Cover in `AddonSettingNamesTest`.

## Essential Addons filterable gallery loses its filters, names and captions

Found 2026-09-15 while planning the demo site, by reading the converter. Not yet reproduced in a running WordPress.

**Symptom.** An EAEL Filterable Gallery converts to a Divi gallery with the right images, but the filter buttons, each item's name and each item's caption are gone, with nothing in the report beyond skipped settings.

**Why.** `EaelFilterableGalleryConverter::convert()` (`includes/converter/handlers/class-eael-filterable-gallery-converter.php`) reads only `eael_fg_gallery_img` from each `eael_fg_gallery_items` entry. `eael_fg_controls[].eael_fg_control`, `eael_fg_gallery_item_name`, `fg_item_cat` and `eael_fg_gallery_item_content` are never read.

**Suggested fix.** Carry each item's name and caption onto its Divi gallery image (title and caption). Divi 5's gallery has no filter bar, so add a report warning or approximate-match entry saying the filter buttons were not carried, rather than dropping them silently.

## Essential Addons post grid ignores its query filters

Found 2026-09-15 while planning the demo site, by reading the converter. Not yet reproduced in a running WordPress.

**Symptom.** An EAEL Post Grid limited to a category (or another post type) converts to a Divi blog module that lists the latest posts of every category.

**Why.** `EaelPostGridConverter::convert()` (`includes/converter/handlers/class-eael-post-grid-converter.php`) writes only `posts_per_page` to the Divi blog block. It reads `post_type` but never writes it, and never reads EAEL's taxonomy filters (`category_ids`, and `<taxonomy>_ids` in general).

**Suggested fix.** Map `category_ids` to the Divi 5.7.4 blog block's category filter if it has one, and report any post type or taxonomy filter that cannot be carried. Cover with a converter test that sets `category_ids`.
