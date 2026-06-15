=== JHMG Converter For Elementor to Divi ===
Contributors: lucaslopvet
Tags: divi migration, elementor export, page builder converter, elementor to divi, essential addons
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 2.0.0
Requires PHP: 7.4
Requires Plugins: elementor
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Convert and export your Elementor-built pages to Divi 5 with precision and ease. Save hours of rebuilding work with this migration tool.

== Description ==

Convert and import your Elementor-built pages into Divi 5 with precision and ease. This powerful migration tool transforms Elementor JSON exports into native Divi 5 block structure, saving you hours of manual rebuilding work.

Version 2.0 is a full rewrite targeting Divi 5 and its native block format. Upload an Elementor JSON or Kit ZIP file and the plugin creates converted pages directly in your WordPress site — no live Elementor installation required on the destination.

### Why Choose This Converter Tool

* **No Elementor Required on Destination**: Convert from an exported file — Elementor does not need to be active on the target site
* **Divi 5 Native Output**: Pages are written in the Divi 5 block format, not legacy shortcodes
* **Batch Import**: Convert an entire Elementor Kit ZIP in one upload
* **Save Countless Hours**: Avoid manually rebuilding each page from scratch
* **Smooth Migration Path**: Create a stress-free transition from Elementor to Divi 5
* **Preserve Your Design Work**: Maintain layout structure, content placement, and design elements
* **Theme Builder Headers & Footers**: Elementor header and footer templates are automatically converted and registered in the Divi Theme Builder — not just as regular pages
* **Precision Mapping**: Accurate conversion of Elementor widgets to their Divi 5 equivalents

### Theme Builder: Headers & Footers

If your Elementor export includes a custom header or footer template, the plugin will automatically create them as **Divi Theme Builder** entries — not as regular pages. After importing, go to **Divi → Theme Builder** in the WordPress admin to find them already in place.

From there you can:

* Set display conditions (e.g. show on all pages, or specific post types)
* Enable the header or footer to go live across your site
* Edit the converted design directly in the Divi 5 visual builder

This means your entire site structure — header, content pages, and footer — can come across in a single import. It is one of the most time-saving features of this plugin, so look for your templates in the Theme Builder after every Kit import.

### Complete Migration Solution

1. Export your pages from Elementor (single page JSON or full Kit ZIP)
2. Install and activate this plugin on your Divi 5 site
3. Go to Tools → Elementor → Divi 5 in the WordPress admin
4. Upload the JSON or ZIP file and click Import and Convert
5. Pages and Theme Builder templates are created — review and publish when ready
6. For headers and footers: go to Divi → Theme Builder to set display conditions and activate them

### Supported Elementor Components

The plugin supports conversion of these essential Elementor elements:

* **Layout Structures**: Sections, columns, inner sections, containers
* **Basic Elements**: Heading, text editor, image, video, button, spacer, divider
* **Media Elements**: Image galleries, carousels, audio players
* **Advanced Components**: Tabs, accordions, testimonials, pricing tables
* **Interactive Elements**: Maps, icons, counters, progress bars, forms
* **Dynamic Content**: Posts, social media feeds, sidebars

### Elementor Addon Plugin Support

The converter includes specialized handlers for the most popular Elementor addon plugins — not just the core widgets.

**Essential Addons for Elementor (EAEL)**

One of the most widely used addon suites is fully covered, including: advanced accordion, advanced tabs, countdown timer, team member, testimonial, info box, flip box, pricing table, post grid, creative button, call-to-action box, filterable gallery, progress bar, fancy text, content ticker, data table, tooltip, image accordion, login/register, event calendar, post timeline, and more. Form widgets (Contact Form 7, WPForms, Gravity Forms, Ninja Forms, Fluent Forms) are converted to their shortcode equivalents. WooCommerce product widgets are also handled.

**Header Footer Elementor (HFE)**

HFE widgets used in header and footer templates are converted to their Divi equivalents: site logo, site title, site tagline, navigation menu, copyright, page title, search, breadcrumbs, counter, post info, info card, and basic posts.

**ElementsKit**

ElementsKit widgets are converted where a Divi equivalent exists: testimonial, heading, video, dual button, and accordion.

**Premium Addons for Elementor**

The Premium Addons blog listing widget is converted to the Divi Blog module, preserving post count, excerpt length, pagination, and read more settings.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/jhmg-converter-for-elementor-to-divi` directory, or install the plugin through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Tools → Elementor → Divi 5 in the WordPress admin to begin converting

== Migration Process ==

1. **Export**: In Elementor, export the page (Page → Export Template) or export a full Kit (Elementor → Tools → Export Kit)
2. **Upload**: In the converter admin page, upload the `.json` or `.zip` file
3. **Configure**: Choose post type (Page/Post) and initial status (Draft recommended)
4. **Convert**: Click Import and Convert — pages are created instantly
5. **Review**: Check each converted page in Divi Builder and make any necessary adjustments
6. **Publish**: Update your pages to make them live with Divi 5

== Frequently Asked Questions ==

= Do I need Elementor installed on the destination site? =

No. Version 2.0 works from the exported file directly. You only need Divi 5 installed on the destination site.

= What's the conversion accuracy rate? =

Most standard layouts convert with 85-95% accuracy. Complex or custom-coded Elementor pages may require some manual adjustments in Divi Builder.

= Will I lose any content during conversion? =

The plugin preserves all text, images, videos, and basic styling. Some advanced Elementor-specific animations or effects might need recreation in Divi 5.

= Will this affect my live Elementor pages? =

No. The plugin works from an exported file and creates new posts. Your original Elementor pages remain untouched.

= What about custom CSS from Elementor? =

Basic inline styles are converted. Custom CSS classes and complex styling may need manual recreation in Divi 5 after conversion.

= Can I convert my entire site at once? =

Yes. Export a full Elementor Kit ZIP and upload it — the plugin will process all pages in the ZIP in one batch.

= Where do my converted header and footer templates end up? =

They are created directly inside the Divi Theme Builder — not as regular pages. After importing, go to **Divi → Theme Builder** in your WordPress admin. You will see the converted header and footer templates there. Set display conditions (e.g. "All Pages") and save the Theme Builder to make them live across your site.

= What if something doesn't convert properly? =

Unsupported elements are listed in the conversion report shown after import, so you know exactly what to manually recreate.

= What changed from version 1.x? =

Version 2.0 is a full rewrite. The key differences are: Divi 5 block output (not legacy shortcodes), file-based import (no live Elementor required), and batch ZIP support. If you are still using Divi 4, continue using version 1.1.

== Screenshots ==

1. Migration dashboard showing the import form and options

== Changelog ==

= 2.0.0 =
* Full rewrite targeting Divi 5 native block format
* New file-based import workflow — no live Elementor plugin required on the destination site
* Batch import from Elementor Kit ZIP files
* Support for Essential Addons for Elementor (EAEL) widget set
* Support for Header Footer Elementor (HFE) widget set
* Divi Theme Builder header template creation from Elementor header templates
* Detailed per-page conversion report showing converted elements, warnings, and unsupported widgets

= 1.1 =
* Improved background color handling to properly apply colors to sections
* Enhanced color extraction from Elementor global colors
* Changed default fallback color from white to transparent for better layout fidelity
* Improved placeholder text for heading and text modules with more realistic content
* Updated container and section processing for more accurate layout conversion
* Enhanced style processing to maintain visual consistency between Elementor and Divi
* Added detailed diagnostic logging for color mapping issues

= 1.0 =
* Initial release with support for core Elementor widgets
* ElementsKit integration for specialized widget conversion
* Divi JSON export functionality
* Migration statistics dashboard
* Detailed conversion logging

== Upgrade Notice ==

= 2.0.0 =
This is a full rewrite for Divi 5. The workflow has changed: instead of exporting from within WordPress, you now upload an Elementor export file. If you are still on Divi 4, do not upgrade — stay on version 1.1.

= 1.0 =
First public release of the JHMG Converter For Elementor to Divi with full conversion capabilities.

== Additional Notes ==

This plugin is specifically designed for migrating content from Elementor to Divi 5. It provides a bridge between these two popular page builders to facilitate website migrations or design system changes.

For best results, convert simpler pages first to familiarise yourself with the conversion patterns and any adjustments that may be needed before tackling complex layouts.
