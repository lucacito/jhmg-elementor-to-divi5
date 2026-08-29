=== JHMG Converter For Elementor to Divi 5 ===
Contributors: lucaslopvet
Tags: divi migration, elementor export, page builder converter, elementor to divi, divi 5
Requires at least: 5.9
Tested up to: 7.0
Stable tag: 2.3.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Migrate Elementor pages and templates to Divi 5. Unlimited single-page JSON imports for free; the Pro add-on adds kit imports, global headers, and footers.

== Description ==

Convert Elementor-built pages and templates into native Divi 5 blocks with precision and ease. Upload an Elementor page JSON export and the plugin creates a converted page directly in your WordPress site — no live Elementor installation required on the destination. (Full Kit ZIP import for whole-site migrations is available in the [Pro add-on](https://divi5lab.com/plugins/elementor-to-divi-5).)

Version 2.0 is a full rewrite targeting Divi 5's native block format, not legacy shortcodes. All conversion happens from the exported file, so your live Elementor site is never touched.

### Free vs Pro

**Free — Convert single pages at no cost:**

* Upload any Elementor page JSON export
* Get a fully converted Divi 5 page instantly
* Full layout, content, and style preservation
* Core Elementor widgets and most popular addons supported
* No Elementor required on the destination site
* Unlimited conversions — one page at a time
* 140+ widget mappings and a detailed per-page conversion report

**[Pro add-on](https://divi5lab.com/plugins/elementor-to-divi-5) — The full migration toolkit:**

The Pro add-on is a separate plugin, purchased and installed alongside this one, that unlocks:

* **Full Kit import (ZIP)**: upload your Elementor Export Kit and convert every page in one batch
* **Global Header**: upload a single Elementor header template JSON and register it directly as a Divi Theme Builder global header
* **Global Footer**: upload a single Elementor footer template JSON and register it directly as a Divi Theme Builder global footer
* **Global styles**: extract your kit's exact colors and typography and apply them across all conversions
* Priority support and regular updates

Get the Pro add-on at [divi5lab.com/plugins/elementor-to-divi-5](https://divi5lab.com/plugins/elementor-to-divi-5).

### Why Choose This Converter

* **No Elementor Required on Destination**: Convert from an exported file — Elementor does not need to be active on the target site
* **Divi 5 Native Output**: Pages are written in the Divi 5 block format, not legacy shortcodes
* **Theme Builder Headers & Footers** *(Pro add-on)*: Elementor header and footer templates become Divi Theme Builder global layouts — not just regular pages
* **Bulk Import** *(Pro add-on)*: Convert an entire Elementor Kit ZIP in one upload
* **Global Styles** *(Pro add-on)*: Colors and typography from your kit are preserved and applied to every conversion
* **Save Countless Hours**: Avoid manually rebuilding pages from scratch
* **Precision Mapping**: Accurate widget-by-widget conversion from Elementor to Divi 5

### Theme Builder: Headers & Footers *(Pro add-on)*

The [Pro add-on](https://divi5lab.com/plugins/elementor-to-divi-5) brings your Elementor header and footer templates directly into the Divi Theme Builder — two ways:

**From a Kit ZIP**: when running a kit page conversion, the converter detects header and footer templates and registers them in the Divi Theme Builder automatically.

**From a single JSON file**: upload a header or footer template JSON directly and it's created as a Divi Theme Builder layout immediately, ready to configure.

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

**For a full site (Pro add-on):**

Whole-site migration — kit ZIP import, global header/footer conversion, and global style extraction — requires the separate [Pro add-on](https://divi5lab.com/plugins/elementor-to-divi-5). Purchase and install it alongside this free plugin, then follow its own setup guide to import your Elementor kit and register your header and footer in the Divi Theme Builder.

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

= What is free and what requires the Pro add-on? =

**Free**: convert single Elementor pages via JSON — unlimited, one page at a time.

**Pro add-on** (a separate plugin — [divi5lab.com/plugins/elementor-to-divi-5](https://divi5lab.com/plugins/elementor-to-divi-5)): import full Elementor Kit ZIPs to convert entire sites in one batch; upload a header template JSON to set it as a Divi Theme Builder global header; upload a footer template JSON to set it as a Divi Theme Builder global footer; extract and apply global colors and typography from your kit.

= What's the conversion accuracy rate? =

Most standard layouts convert with 85–95% accuracy. Complex or custom-coded Elementor pages may require some manual adjustments in Divi Builder.

= Will I lose any content during conversion? =

The plugin preserves all text, images, videos, and basic styling. Some advanced Elementor-specific animations or effects might need recreation in Divi 5.

= Will this affect my live Elementor pages? =

No. The plugin works from an exported file and creates new posts. Your original Elementor pages remain untouched.

= What about custom CSS from Elementor? =

Basic inline styles are converted. Custom CSS classes and complex styling may need manual recreation in Divi 5 after conversion.

= Can I convert my entire site at once? =

Yes, with the [Pro add-on](https://divi5lab.com/plugins/elementor-to-divi-5). Export a full Elementor Kit ZIP and upload it there — Pro converts all pages in the ZIP in one batch. You can then separately upload your header and footer JSON templates to register them in the Divi Theme Builder.

= Where do my converted header and footer templates end up? =

With the Pro add-on, they are created directly inside the Divi Theme Builder — not as regular pages. After importing, go to **Divi → Theme Builder** in your WordPress admin. Set display conditions (e.g. "All Pages") and save the Theme Builder to make them live across your site.

= What if something doesn't convert properly? =

Unsupported elements are listed in the conversion report shown after each import, so you know exactly what to manually recreate.

= What changed from version 1.x? =

Version 2.0 is a full rewrite. The key differences are: Divi 5 block output (not legacy shortcodes) and file-based import (no live Elementor required). Batch Kit ZIP import is available via the separate Pro add-on. If you are still using Divi 4, continue using version 1.1.

== Screenshots ==

1. Migration dashboard — landing page showing the free single-page importer and a link to the Pro add-on
2. Import form — single page JSON conversion (Free)
3. Conversion report — converted elements, warnings, and unsupported widgets after an import

== External services ==

This plugin can optionally send a short report to divi5lab.com so that the most
commonly missing Elementor widgets get built first.

* **Service:** divi5lab.com coverage endpoint — https://divi5lab.com/api/plugin/coverage
* **What is sent:** two fields — `widget_types` (the names of Elementor widget
  types your imports could not convert, for example `lottie`) and `product` (a
  fixed identifier indicating this plugin sent the report). Nothing else — no
  site address, no page content, no personal data, no license or account
  information.
* **When:** at most once a week, and only after you explicitly turn sharing on
  from the Conversion coverage panel. Sharing is off by default and nothing is
  sent until you enable it.
* **Turning it off:** use "Stop sharing" on the same panel at any time.
* Terms: https://divi5lab.com/terms — Privacy policy: https://divi5lab.com/privacy

== Changelog ==

= 2.3.0 =
* New: Undo an import — one click moves the pages an import created to the trash, so trying a conversion is no longer a one-way door. Pages that are no longer linked to that import (already gone, or replaced) are skipped; note that editing a page does not exempt it from Undo
* New: Conversion coverage — see every Elementor widget your imports could not convert, ranked by how many imports each affected, so you know exactly what still needs rebuilding by hand
* New: Optional, opt-in sharing of unsupported widget names with divi5lab so the most-needed widgets get built first. Off by default; sends only widget names and a plugin identifier
* Pro is now $25/yr, reduced from $49/yr — same unlimited-sites license
* Fixed: the upgrade screen quoted the old $49/yr price; the price is now single-sourced so it cannot drift again
* New: a dismissible notice announcing the new price, shown on the Plugins screen and this plugin's own pages (dismissed per user, and it stops showing after 2026-10-27)
* New: after three successful conversions, the results screen asks once whether you'd like to review the plugin on WordPress.org — dismissible, snoozeable, per user, and never shown after a run that had any failures

= 2.1.0 =
* Premium features (kit ZIP import, Theme Builder headers/footers, global styles) now live in the separate Pro add-on, available at https://divi5lab.com/plugins/elementor-to-divi-5
* New: extension hooks for companion plugins (edc_loaded, edc_kit_globals, edc_theme_builder_exporter, edc_pro_active)
* The in-plugin "premium preview" toggle has been removed

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

= 2.3.0 =
Adds one-click Undo for an import, so trying a conversion is no longer a one-way door, plus a coverage report showing which Elementor widgets could not be converted. Pro also dropped to $25/yr from $49/yr — the same unlimited-sites license. The free plugin still converts unlimited single pages.

= 2.1.0 =
Premium features (kit ZIP import, Theme Builder headers/footers, global styles) have moved to the separate Pro add-on at https://divi5lab.com/plugins/elementor-to-divi-5. This free plugin still handles unlimited single-page JSON conversion at no cost. If you were using an older in-plugin premium unlock, install the Pro add-on to keep that functionality.

= 2.0.0 =
This is a full rewrite for Divi 5. The workflow has changed: instead of exporting from within WordPress, you now upload an Elementor export file. If you are still on Divi 4, do not upgrade — stay on version 1.1.

== Additional Notes ==

This plugin is specifically designed for migrating content from Elementor to Divi 5. It provides a bridge between these two popular page builders to facilitate website migrations or design system changes.

For best results, convert simpler pages first to familiarise yourself with the conversion patterns and any adjustments that may be needed before tackling complex layouts.
