=== JHMG Converter For Elementor to Divi 5 ===
Contributors: lucaslopvet
Tags: divi migration, elementor export, page builder converter, elementor to divi, divi 5
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 3.0.2
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert Elementor pages to native Divi 5: pick a page from your site or upload an export, check the report, convert. Free, one page at a time.

== Description ==

Convert Elementor pages and templates into native Divi 5 blocks. If Elementor is installed on this site, pick the page from a list; otherwise upload an Elementor page JSON export. No Elementor is needed on the destination.

Click **Check this page** first: the report shows the structure the conversion will produce and names anything that cannot be carried over. Converting creates a new Divi draft, never touches the Elementor page, and can be undone from the import history.

Supported: Elementor's core widgets and layouts, plus Essential Addons, Header Footer Elementor, ElementsKit and Premium Addons widgets. Converted pages render as designed in Divi 5.7.4: backgrounds, typography, buttons, galleries, counters and add-on modules included.

The free plugin converts unlimited single pages. The [Pro add-on](https://divi5lab.com/plugins/elementor-to-divi-5) converts whole sites from a Kit ZIP, turns headers and footers into Divi Theme Builder layouts, and applies your kit's global colours, fonts and button styles.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/jhmg-converter-for-elementor-to-divi` directory, or install the plugin through the WordPress plugins screen
2. Activate the plugin through the Plugins screen in WordPress
3. Go to Tools → Elementor → Divi 5 in the WordPress admin to begin converting

== Frequently Asked Questions ==

= Do I need to export a JSON file? =

No, if Elementor is installed on the same site — pick the page from the list on **Tools → Elementor → Divi 5** and convert it directly. Yes, if you're converting from a different site: export the page in Elementor first, then upload that JSON file here.

= Do I need Elementor installed on the destination site? =

Only if you want to convert a page directly from that site's list. If you're uploading a JSON export instead, no — the converter works from the exported file directly, and you only need Divi 5 installed on the destination site.

= What is free and what requires the Pro add-on? =

**Free**: convert single Elementor pages — either picked directly from your installed Elementor site, or via a JSON upload — unlimited, one page at a time.

**Pro add-on** (a separate plugin — [divi5lab.com/plugins/elementor-to-divi-5](https://divi5lab.com/plugins/elementor-to-divi-5)): import full Elementor Kit ZIPs to convert entire sites in one batch; upload a header template JSON to set it as a Divi Theme Builder global header; upload a footer template JSON to set it as a Divi Theme Builder global footer; extract and apply global colors and typography from your kit.

= What's the conversion accuracy rate? =

Most standard layouts convert with 85–95% accuracy. Complex or custom-coded Elementor pages may require some manual adjustments in Divi Builder.

= Will I lose any content during conversion? =

The plugin preserves all text, images, videos, and basic styling. Some advanced Elementor-specific animations or effects might need recreation in Divi 5.

= Will this affect my live Elementor pages? =

No. Whether you pick a page from the list or upload a JSON export, the plugin never modifies the Elementor page — it always creates a new Divi draft. Your original Elementor pages remain untouched, and every conversion run can be undone with one click.

= What about custom CSS from Elementor? =

Basic inline styles are converted. Custom CSS classes and complex styling may need manual recreation in Divi 5 after conversion.

= Can I convert several pages at once? =

Yes, with the [Pro add-on](https://divi5lab.com/plugins/elementor-to-divi-5). Select as many installed pages as you like and convert them in one run, or export a full Elementor Kit ZIP and upload it — Pro converts every page in the ZIP in one batch. You can then separately upload your header and footer JSON templates to register them in the Divi Theme Builder.

= Where do my converted header and footer templates end up? =

With the Pro add-on, they are created directly inside the Divi Theme Builder — not as regular pages. After importing, go to **Divi → Theme Builder** in your WordPress admin. Set display conditions (e.g. "All Pages") and save the Theme Builder to make them live across your site.

= What if something doesn't convert properly? =

Click **Check this page** before converting to see the widgets that can't be converted, by name — nothing is written to your site yet at that point. The same list also appears in the conversion report shown after each import, so you always know exactly what to manually recreate.

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

= 3.0.2 =
* Fixed: converted pages now render as designed in Divi 5.7.4. Column background images fill their column instead of a thin strip, oversized display headings (Elementor's span, p and div heading tags) keep their size, weight and colour, buttons keep Elementor's default look and your kit's button style instead of Divi's blue outline, counters show the right number with a percent sign only where you had one, image carousels and galleries show their own images instead of the whole media library, social icons keep their network, and Header Footer Elementor menus no longer sit on a white bar
* Fixed: Essential Addons info boxes, flip boxes, pricing tables, team members, testimonials, countdowns, progress bars and feature lists rendered empty or partly empty in Divi. They now render with all their content, and FontAwesome icons are carried into Divi's icon font
* Fixed: content that was silently dropped is carried over: Call to Action body text, pricing table subtitles, ElementsKit heading subtitles, post grid category filters, image margins, video sources for the sticky video widget, and custom CSS (which Divi never read before)
* Fixed: Contact Form 7 and menu modules pointed at the selected form and menu by accident only; they now use the attributes Divi reads
* New: Header Footer Elementor templates appear in the page picker and convert as Divi Theme Builder headers and footers (with Pro)
* New: an admin notice warns when Header Footer Elementor is still active under a Divi Theme Builder header, which makes every page fail to load
* Changed: what Divi cannot express is listed in the conversion report instead of dropped: counter prefixes and suffixes, gallery filter buttons and captions, unsupported social networks, post grid post types and taxonomies
* Dev: every converted block is checked against Divi 5.7.4's module definitions in the test suite; the demo site gained a render check

= 3.0.1 =
* Fixed: pages containing an Essential Addons Contact Form 7 widget failed to convert at all. They now convert, and the form keeps the contact form you selected
* Fixed: several Essential Addons widgets converted with their content missing. Fancy text now keeps its rotating words, info boxes their description, pricing tables their price period and button link, and code snippets, image accordions, simple menus, sticky videos and tooltips their code, panels, menu, video and tooltip text
* Fixed: Essential Addons data tables converted as empty tables. Header, rows and cells now come through, including rich-text and merged cells
* Changed: Essential Addons advanced data tables now convert when their data is stored in the page (static or CSV). Tables loaded from a database or another plugin are listed in the report instead of converting empty
* Changed: the Essential Addons interactive circle now converts to Divi tabs, keeping each item's title and content
* Changed: an Essential Addons content ticker showing your latest posts keeps its label, and the report notes that the live post feed was not carried over
* Fixed: ElementsKit video widgets converted without a video. YouTube, Vimeo and self-hosted videos now come through
* Fixed: Header Footer Elementor widgets lost their content. The navigation menu, copyright line, site title and tagline text, counter number and retina logo now convert, and the copyright shows the year and site name instead of HFE's shortcodes

= 3.0.0 =
* New: convert directly from your installed Elementor site — pick a page from a list, no export or upload
* New: check any page before converting — see the converted structure and which widgets could not be converted, before anything is written to your site
* Converting never touches your Elementor page: it always creates a new Divi draft, and every run is undoable
* Pro: convert several pages from your site in one run
* Fixed: on a multi-page kit import the per-page conversion counts accumulated instead of being counted per page, so later pages reported inflated totals (Pro)
* Fixed: global colours and fonts that could not be resolved were filled in from a built-in palette, which repainted pages in colours that were never on your site. Unresolved globals are now left alone and listed in the report
* Fixed: Google Maps, Image Gallery and Progress Bar widgets were registered under names Elementor does not use, so they were dropped from every conversion
* New: support for Elementor's nested accordion and nested tabs — the default accordion and tabs since Elementor 3.15
* Fixed: a widget with no mapping vanished from the page. It now leaves a labelled placeholder holding its text, so you can see what was there and where
* New: a "Not carried over" section in both reports listing dynamic content bindings, entrance animations, motion effects and discarded form fields, each with its element id. These were previously dropped without appearing anywhere
* Changed: widgets matched by guesswork rather than a real mapping are now reported as approximate instead of being counted as converted
* Fixed: the free plugin's kit ZIP limit could be bypassed by renaming the file
* New: the converter now checks for Divi 5 and explains itself instead of writing pages that render blank on Divi 4 or with no Divi installed
* Fixed: the Pro add-on's admin screens rendered completely unstyled
* New: translation template (.pot) for both plugins

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

= 3.0.2 =
Converted pages now render as designed in Divi 5.7.4: column backgrounds, display headings, buttons, counters, galleries, social icons and the Essential Addons widgets that came through empty. Reconvert any page that looked wrong in Divi.

= 3.0.1 =
Fixes Essential Addons, ElementsKit and Header Footer Elementor widgets that converted with their content missing — including pricing tables, data tables, fancy text and navigation menus — and pages with a Contact Form 7 widget that failed to convert at all.

= 3.0.0 =
The export-and-upload step is gone: pick an installed Elementor page from a list and convert it directly, with a "Check this page" report before anything is written. This release also fixes several silent losses: unresolved global colours are no longer replaced with a built-in palette, three common widgets that were being dropped now convert, unmapped widgets leave a visible placeholder instead of disappearing, and dropped animations, dynamic bindings and form fields are now listed in the report instead of going unmentioned. The converter also now requires Divi 5 and says so, rather than producing pages that render blank.

= 2.3.0 =
Adds one-click Undo for an import, so trying a conversion is no longer a one-way door, plus a coverage report showing which Elementor widgets could not be converted. Pro also dropped to $25/yr from $49/yr — the same unlimited-sites license. The free plugin still converts unlimited single pages.

= 2.1.0 =
Premium features (kit ZIP import, Theme Builder headers/footers, global styles) have moved to the separate Pro add-on at https://divi5lab.com/plugins/elementor-to-divi-5. This free plugin still handles unlimited single-page JSON conversion at no cost. If you were using an older in-plugin premium unlock, install the Pro add-on to keep that functionality.

= 2.0.0 =
This is a full rewrite for Divi 5. The workflow has changed: instead of exporting from within WordPress, you now upload an Elementor export file. If you are still on Divi 4, do not upgrade — stay on version 1.1.

== Additional Notes ==

This plugin is specifically designed for migrating content from Elementor to Divi 5. It provides a bridge between these two popular page builders to facilitate website migrations or design system changes.

For best results, convert simpler pages first to familiarise yourself with the conversion patterns and any adjustments that may be needed before tackling complex layouts.
