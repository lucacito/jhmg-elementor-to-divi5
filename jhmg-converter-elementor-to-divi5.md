    # Elementor to Divi 5 Converter Project Instructions

    ## Project Goal

    Build a WordPress plugin that converts Elementor-built pages into Divi 5 pages.

    The converter must transform Elementor's internal data structure into Divi 5's internal structure.

    The goal is NOT HTML conversion.

    The conversion pipeline is:

    Elementor JSON Tree
            |
            |
    Converter Engine
            |
            |
    Divi 5 JSON Structure
            |
            |
    Rendered Divi Page


    ---

    # Core Development Rules

    ## Never convert HTML

    Do not scrape rendered HTML.

    Always work with:

    Elementor:
    - post meta
    - Elementor document JSON
    - widget structures
    - settings arrays

    Divi:
    - module data
    - layout structures
    - attributes
    - design settings


    ---

    # Project Structure

    Maintain this structure:

plugin/ └── elementor-divi5-converter/ ├── elementor-divi5-converter.php ├── includes/ │ ├── converter/ │ ├── parsers/ │ ├── exporters/ │ └── helpers/ │ ├── mappings/ │ ├── heading.php │ ├── text.php │ ├── image.php │ └── button.php │ └── tests/\
docs/\
fixtures/ ├── elementor/ └── divi/

\
\-\--\
\
\# Elementor Research\
\
The Elementor source code is available locally.\
\
Analyze:\

wp-content/plugins/elementor/

Important areas:\

includes/documents/ includes/base/ modules/ widgets/

Understand:\
\
- Elementor document model\
- sections\
- containers\
- columns\
- widgets\
- controls\
- settings storage\
\
\
The main source data is usually:\

\_elementor_data

stored in WordPress post meta.\
\
\
Example structure:\

Section \| └── Container \| └── Widget

The converter must preserve this hierarchy.\
\
\
\-\--\
\
\# Divi 5 Research\
\
The Divi 5 theme/plugin source is available locally.\
\
Analyze:\

wp-content/themes/Divi/

Understand:\
\
- module registration\
- module attributes\
- design settings\
- responsive settings\
- presets\
- layout storage\
\
\
Create documentation:\

docs/divi5-schema.md

before writing conversion logic.\
\
\
\-\--\
\
\# First Development Task\
\
Before coding:\
\
Create:\

docs/elementor-schema.md

Document:\
\
- Elementor JSON format\
- available element types\
- widget structures\
- important settings\
\
\
Create:\

docs/divi5-schema.md

Document:\
\
- Divi 5 layout format\
- modules\
- attributes\
- settings\
\
\
Create:\

docs/conversion-map.md

Example:\
\
\| Elementor \| Divi 5 \|\
\|\-\--\|\-\--\|\
\| Heading Widget \| Text Module \|\
\| Text Editor \| Text Module \|\
\| Image Widget \| Image Module \|\
\| Button Widget \| Button Module \|\
\| Container \| Section / Row \|\
\
\-\--\
\
\# Coding Architecture\
\
Do not create one large converter file.\
\
Use independent converters.\
\
Example:\

mappings/\
heading.php image.php button.php text.php

Each Elementor widget should have its own conversion handler.\
\
\
Avoid:\

if widget == heading if widget == image if widget == button

Use a registry system:\

Elementor Widget \| \| Converter Handler \| \| Divi Module

\
\-\--\
\
\# Local WordPress Test Environment\
\
Use a local WordPress installation.\
\
Preferred:\
\
LocalWP\
\
Install:\
\
- WordPress latest\
- Elementor\
- Divi 5\
- Converter plugin\
\
\
The plugin must be symlinked.\
\
Development files:\

\~/Projects/converter/plugin/

\
WordPress loads:\

wp-content/plugins/

\
Use:\

ln -s source-folder wordpress-plugin-folder

\
Changes made in VS Code must immediately appear in WordPress.\
\
\
\-\--\
\
\# Automated Testing\
\
Do not rely only on manual testing.\
\
\
Create:\

fixtures/\
elementor/ heading.json image.json hero.json\
divi/

    expected-heading.json
    expected-image.json

    Tests should:

    1. Load Elementor JSON
    2. Run converter
    3. Generate Divi output
    4. Compare result


    Use PHPUnit.

    Every new converter needs tests.


    ---

    # Visual Testing

    Add screenshot testing.

    Recommended:

    Playwright

    Workflow:

    1. Create Elementor test page
    2. Convert page
    3. Render Divi page
    4. Screenshot
    5. Compare output


    Visual differences should help improve mappings.


    ---

    # Development Roadmap


    ## Phase 1

    Support:

    - Sections
    - Containers
    - Columns
    - Heading
    - Text
    - Image
    - Button


    ## Phase 2

    Support:

    - typography
    - colors
    - spacing
    - backgrounds
    - responsive settings


    ## Phase 3

    Support:

    - forms
    - sliders
    - dynamic content
    - WooCommerce
    - advanced widgets


    ---

    # Conversion Principles


    Always preserve:

    - text content
    - images
    - links
    - CSS values
    - responsive settings
    - spacing
    - typography


    Never destroy:

    - original Elementor page


    Conversion should create:

    Original Elementor page

    +

    New Divi converted page


    ---

    # Logging

    Every conversion should generate a report.

    Example:

Converted:\
Heading Widget Image Widget\
Warnings:\
Unsupported widget: Elementor Form Widget\
Ignored: Custom CSS

\-\--\
\
\# Claude Working Rules\
\
Before making code changes:\
\
1. Inspect existing architecture\
2. Explain proposed changes\
3. Create tests\
4. Implement\
5. Run tests\
\
\
Do not make large rewrites without confirmation.\
\
\
\-\--\
\
\# Quality Standard\
\
The plugin should eventually be production-ready.\
\
Prioritize:\
\
- clean architecture\
- extensibility\
- test coverage\
- predictable conversions\
- safe WordPress practices\

# Autonomous Testing Workflow

Claude must test every important change automatically.

Do not assume the code works after editing.

For every converter feature:

1. Create or update a test fixture
2. Run the converter
3. Verify generated Divi output
4. Report failures
5. Fix issues before moving on


## WordPress Test Environment

The project uses a local WordPress installation.

Available environment:

- WordPress
- Elementor
- Divi 5
- Converter plugin


Claude should use WP CLI when possible.

Examples:

Activate plugin:

wp plugin activate elementor-divi5-converter


Create test content:

wp post create


Reset test environment:

wp db reset --yes


## Conversion Tests

Every Elementor widget converter needs:

Input:

fixtures/elementor/example.json


Expected:

fixtures/divi/example.json


Test:

Elementor JSON
        |
        |
Converter
        |
        |
Compare with expected Divi JSON


A conversion is successful when:

- correct Divi module created
- text preserved
- images preserved
- links preserved
- styles mapped
- responsive settings preserved


## Browser Testing

Use Playwright for visual testing.

Workflow:

1. Import Elementor test page
2. Convert page
3. Open converted Divi page
4. Take screenshot
5. Compare screenshot


If visual differences exist:

- identify the cause
- update converter
- rerun test


## Before finishing any task

Always provide:

- files changed
- tests executed
- test results
- remaining warnings

## Testing Commands

Before declaring any task complete, run:

./test.sh

If tests fail:
- inspect error
- fix code
- rerun tests


