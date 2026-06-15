# Elementor to Divi Exporter

A WordPress plugin that exports Elementor page data to a Divi-compatible JSON file for seamless migration between page builders.

## Description

Elementor to Divi Exporter is a powerful tool that helps WordPress developers and site owners migrate their content from Elementor to Divi Builder without losing their designs. This plugin analyzes your Elementor pages and converts them to the Divi format, maintaining layout structure, content, and design elements wherever possible.

### Features

- Exports any Elementor page to a Divi-compatible JSON format
- Preserves page layout structure (sections, columns, etc.)
- Maps Elementor widgets to equivalent Divi modules
- Converts basic styling attributes
- Maintains content and media elements
- Handles responsive designs
- Works with complex and nested structures
- Simple and user-friendly interface
- Detailed conversion logs for debugging

### Supported Elementor Widgets

The plugin now supports conversion of these Elementor widgets to their Divi equivalents:

| Elementor Widget | Divi Module |
|------------------|-------------|
| Heading | Text Module (formatted) |
| Text Editor | Text Module |
| Image | Image Module |
| Video | Video Module |
| Button | Button Module |
| Spacer | Divider Module (blank) |
| Divider | Divider Module |
| Google Maps | Map Module |
| Icon | Blurb Module |
| Tabs | Tabs Module |
| Accordion | Accordion Module |
| Testimonial | Testimonial Module |
| HTML | Code Module |
| Image Box | Blurb Module |
| Icon Box | Blurb Module |
| Counter | Number Counter Module |
| Progress | Bar Counters Module |
| Social Icons | Social Media Follow Module |
| Image Carousel | Gallery Module (slider) |
| Image Gallery | Gallery Module (grid) |
| Icon List | Text Module (formatted) |
| Alert | Text Module (formatted) |
| Audio | Audio Module |
| Sidebar | Sidebar Module |
| Slides | Slider Module |
| Price Table | Pricing Table Module |
| Contact Form | Contact Form Module |
| Toggle | Toggle Module |
| Countdown | Countdown Timer Module |
| Posts | Blog Module or Post Slider |
| Call to Action | Call To Action Module |
| Star Rating | Text Module (formatted) |

## Installation

1. Upload the plugin files to the `/wp-content/plugins/elementor-to-divi-exporter` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to 'ED Exporter' in the admin menu to use the plugin.

## Usage

1. Navigate to the 'ED Exporter' page in your WordPress admin dashboard.
2. Select the Elementor page you want to export from the dropdown.
3. Click the 'Export to Divi JSON' button.
4. Save the downloaded JSON file to your computer.
5. Import the JSON file in Divi Builder:
   - Edit a page with Divi Builder.
   - Click the 'Import & Export' button (↔) in the Divi Builder interface.
   - Choose 'Import' and upload your JSON file.

## Frequently Asked Questions

### Will this plugin convert everything perfectly?

While the plugin aims to convert as much as possible, there may be some elements that don't convert perfectly due to differences between Elementor and Divi. Complex layouts, custom CSS, and unique Elementor features might require some manual adjustments after importing into Divi.

### What happens to custom CSS from Elementor?

Basic inline styles are converted where possible, but custom CSS classes and complex styling may need to be manually recreated in Divi.

### Does the plugin delete or modify my Elementor pages?

No, the plugin only reads your Elementor data and creates a new export file. It doesn't modify your existing pages in any way.

### Can I convert multiple pages at once?

Currently, the plugin supports exporting one page at a time. This allows for better control and troubleshooting during the migration process.

## Limitations

- Some advanced Elementor features might not have direct equivalents in Divi
- Custom CSS may need manual adjustments
- Third-party Elementor add-ons may not convert fully
- Some animations and effects might need to be recreated in Divi
- Nested structures might be flattened in some cases

## Troubleshooting

If you encounter issues with your exported layouts:

1. Enable WP_DEBUG in your wp-config.php file to view detailed logs
2. Check the original Elementor page structure for complex or unusual elements
3. Try simplifying complex layouts before exporting
4. Compare the exported JSON with Divi's expected format
5. For widgets that don't convert properly, they will appear with a notice in Divi

## Contributing

Contributions are welcome! If you'd like to improve the conversion process or add support for additional Elementor widgets, please feel free to submit pull requests or open issues on the GitHub repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.0
* Initial release

### 1.1
* Added support for more Elementor widgets
* Improved container and section handling
* Enhanced error logging and debugging
* Fixed issues with nested structures

### 1.2
* Added support for image carousels, galleries, and tabbed content
* Improved handling of Elementor column widths
* Enhanced conversion of complex multi-column layouts
* Added support for contact forms and pricing tables
* Fixed issues with text formatting and media elements