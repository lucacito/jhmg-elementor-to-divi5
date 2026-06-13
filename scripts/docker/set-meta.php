<?php
// Set the _elementor_data meta from fixture file inside WP container
$page_id = 6;
$file = ABSPATH . 'fixtures/elementor/simple-container.json';
if (! file_exists($file)) {
    echo "Fixture file not found: $file\n";
    exit(1);
}
$content = file_get_contents($file);
update_post_meta($page_id, '_elementor_data', $content);
echo "_elementor_data meta updated for post $page_id\n";
