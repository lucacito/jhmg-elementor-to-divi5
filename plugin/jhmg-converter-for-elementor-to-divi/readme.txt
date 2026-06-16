=== JHMG Converter For Elementor to Divi 5 ===
Contributors: lucaslopvet
Tags: divi migration, elementor export, page builder converter, elementor to divi, divi 5
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 2.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Migrate Elementor pages and templates to Divi 5. Free for single page imports. Upgrade to Premium for full kit imports, global headers, and global footers.

== Description ==

Convert Elementor-built pages and templates into native Divi 5 blocks with precision and ease. Upload an Elementor JSON export or a full Kit ZIP and the plugin creates converted pages directly in your WordPress site — no live Elementor installation required on the destination.

Version 2.0 is a full rewrite targeting Divi 5's native block format, not legacy shortcodes. All conversion happens from the exported file, so your live Elementor site is never touched.

### Free vs Premium

**Free — Convert single pages at no cost:**

* Upload any Elementor page JSON export
* Get a fully converted Divi 5 page instantly
* Full layout, content, and style preservation
* Core Elementor widgets and most popular addons supported
* No Elementor required on the destination site
* Unlimited conversions — one page at a time

**Premium — The full migration toolkit:**

* **Full Kit import (ZIP)**: upload your Elementor Export Kit and convert every page in one batch
* **Global Header**: upload a single Elementor header template JSON and register it directly as a Divi Theme Builder global header
* **Global Footer**: upload a single Elementor footer template JSON and register it directly as a Divi Theme Builder global footer
* **Global styles**: extract your kit's exact colors and typography and apply them across all conversions
* Priority support and regular updates

### Why Choose This Converter

* **No Elementor Required on Destination**: Convert from an exported file — Elementor does not need to be active on the target site
* **Divi 5 Native Output**: Pages are written in the Divi 5 block format, not legacy shortcodes
* **Theme Builder Headers & Footers** *(Premium)*: Elementor header and footer templates become Divi Theme Builder global layouts — not just regular pages
* **Bulk Import** *(Premium)*: Convert an entire Elementor Kit ZIP in one upload
* **Global Styles** *(Premium)*: Colors and typography from your kit are preserved and applied to every conversion
* **Save Countless Hours**: Avoid manually rebuilding pages from scratch
* **Precision Mapping**: Accurate widget-by-widget conversion from Elementor to Divi 5

### Theme Builder: Headers & Footers *(Premium)*

With Premium you can bring your Elementor header and footer templates directly into the Divi Theme Builder — two ways:

**From a Kit ZIP**: when running a kit page conversion, the converter detects header and footer templates and registers them in the Divi Theme Builder automatically.

**From a single JSON file**: go to the Global Kit tab and use the dedicated upload sections — **Set as Global Header** for a header JSON, or **Set as Global Footer** for a footer JSON. Each template is created as a Divi Theme Builder layout immediately, ready to configure.

After importing, go to **Divi → Theme Builder** in the WordPress admin to:

* Set display conditions (e.g. show on all pages, or specific post types)
* Enable the header or footer to go live across your site
* Edit the converted design directly in the Divi 5 visual builder

### Complete Migration: Step by Step

**For a single page (Free):**

1. In Elementor, export the page: Page → Export Template → download the JSON
2. Install and activate this plugin on your Divi 5 site
3. Go to **Tools → Elementor → Divi 5** in the WordPress admin
4. Upload the JSON file and click **Convert Now**
5. Review the converted draft in Divi Builder, then publish when ready

**For a full site (Premium):**

1. In Elementor, export your kit: Elementor → Tools → Export Kit → download the ZIP
2. Go to **Tools → Elementor → Divi 5 → Global Kit tab**
3. Upload the Kit ZIP under **Full Kit** and click **Upload Kit** — global colors and typography are extracted
4. Select the pages to convert and click **Convert Selected**
5. To convert your header: upload the header template JSON and click **Set as Global Header**
6. To convert your footer: upload the footer template JSON and click **Set as Global Footer**
7. Go to **Divi → Theme Builder** to set display conditions and activate your header and footer

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
2. Activate the plugin through the Plugins screen in WordPress
3. Go to Tools → Elementor → Divi 5 in the WordPress admin to begin converting

== Frequently Asked Questions ==

= Do I need Elementor installed on the destination site? =

No. The converter works from the exported file directly. You only need Divi 5 installed on the destination site.

= What is free and what requires Premium? =

**Free**: convert single Elementor pages via JSON — unlimited, one page at a time.

**Premium**: import full Elementor Kit ZIPs to convert entire sites in one batch; upload a header template JSON to set it as a Divi Theme Builder global header; upload a footer template JSON to set it as a Divi Theme Builder global footer; extract and apply global colors and typography from your kit.

= What's the conversion accuracy rate? =

Most standard layouts convert with 85–95% accuracy. Complex or custom-coded Elementor pages may require some manual adjustments in Divi Builder.

= Will I lose any content during conversion? =

The plugin preserves all text, images, videos, and basic styling. Some advanced Elementor-specific animations or effects might need recreation in Divi 5.

= Will this affect my live Elementor pages? =

No. The plugin works from an exported file and creates new posts. Your original Elementor pages remain untouched.

= What about custom CSS from Elementor? =

Basic inline styles are converted. Custom CSS classes and complex styling may need manual recreation in Divi 5 after conversion.

= Can I convert my entire site at once? =

Yes, with Premium. Export a full Elementor Kit ZIP and upload it — the plugin converts all pages in the ZIP in one batch. You can then separately upload your header and footer JSON templates to register them in the Divi Theme Builder.

= Where do my converted header and footer templates end up? =

With Premium, they are created directly inside the Divi Theme Builder — not as regular pages. After importing, go to **Divi → Theme Builder** in your WordPress admin. Set display conditions (e.g. "All Pages") and save the Theme Builder to make them live across your site.

= What if something doesn't convert properly? =

Unsupported elements are listed in the conversion report shown after each import, so you know exactly what to manually recreate.

= What changed from version 1.x? =

Version 2.0 is a full rewrite. The key differences are: Divi 5 block output (not legacy shortcodes), file-based import (no live Elementor required), and batch ZIP support. If you are still using Divi 4, continue using version 1.1.

== Screenshots ==

1. Migration dashboard — landing page showing Free and Premium plans side by side
2. Import form — single page JSON conversion (Free)
3. Global Kit tab — full kit upload, global header and footer template import (Premium)

== Changelog ==

= 2.0.0 =
* Full rewrite targeting Divi 5 native block format
* New file-based import workflow — no live Elementor plugin required on the destination site
* **Free**: unlimited single-page JSON conversion
* **Premium**: bulk import from Elementor Kit ZIP files
* **Premium**: dedicated header template upload — set a JSON header as a Divi Theme Builder global header
* **Premium**: dedicated footer template upload — set a JSON footer as a Divi Theme Builder global footer
* **Premium**: Global Kit tab extracts and applies colors and typography from your Elementor kit
* Support for Essential Addons for Elementor (EAEL) widget set
* Support for Header Footer Elementor (HFE) widget set
* Detailed per-page conversion report showing converted elements, warnings, and unsupported widgets

= 1.1 =
* Improved background color handling to properly apply colors to sections
* Enhanced color extraction from Elementor global colors
* Changed default fallback color from white to transparent for better layout fidelity
* Improved placeholder text for heading and text modules with more realistic content
* Updated container and section processing for more accurate layout conversion
* Enhanced style processing to maintain visual consistency between Elementor and Divi

= 1.0 =
* Initial release with support for core Elementor widgets
* ElementsKit integration for specialized widget conversion
* Divi JSON export functionality
* Migration statistics dashboard
* Detailed conversion logging

== Upgrade Notice ==

= 2.0.0 =
This is a full rewrite for Divi 5. The workflow has changed: instead of exporting from within WordPress, you now upload an Elementor export file. If you are still on Divi 4, do not upgrade — stay on version 1.1.

== Additional Notes ==

This plugin is specifically designed for migrating content from Elementor to Divi 5. It provides a bridge between these two popular page builders to facilitate website migrations or design system changes.

For best results, convert simpler pages first to familiarise yourself with the conversion patterns and any adjustments that may be needed before tackling complex layouts.
