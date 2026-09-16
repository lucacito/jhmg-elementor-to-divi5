<?php
// tests/InstalledPostSourceTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Conversion\ConversionSource;
use ElementorDivi5Converter\Conversion\InstalledPostSource;

class InstalledPostSourceTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    /** Seeds a post plus its Elementor data, and returns the post ID. */
    private function seed_elementor_post(
        int $id,
        string $title,
        string $post_type = 'page',
        $elementor_data = null,
        string $template_type = ''
    ): int {
        $GLOBALS['__test_posts'][ $id ] = (object) [
            'ID'         => $id,
            'post_title' => $title,
            'post_name'  => strtolower( str_replace( ' ', '-', $title ) ),
            'post_type'  => $post_type,
            'post_status' => 'publish',
        ];

        $data = $elementor_data ?? wp_json_encode( [
            [ 'elType' => 'section', 'elements' => [] ],
        ] );
        update_post_meta( $id, '_elementor_data', $data );
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        if ( $template_type !== '' ) {
            update_post_meta( $id, '_elementor_template_type', $template_type );
        }

        return $id;
    }

    public function test_it_is_a_conversion_source(): void {
        $this->assertInstanceOf( ConversionSource::class, new InstalledPostSource( [] ) );
    }

    public function test_it_reads_elements_off_an_installed_post(): void {
        $id = $this->seed_elementor_post( 501, 'Home' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertCount( 1, $items );
        $this->assertSame( 'Home', $items[0]['title'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertSame( 'home', $items[0]['post_name'] );
        $this->assertSame( '', $items[0]['error'] ?? '' );
        $this->assertNotEmpty( $items[0]['elements'] );
    }

    public function test_it_stamps_an_installed_source_ref_carrying_the_post_id(): void {
        $id = $this->seed_elementor_post( 502, 'About' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( 'installed', $items[0]['source_ref']['kind'] );
        $this->assertSame( 502, $items[0]['source_ref']['post_id'] );
        $this->assertNull( $items[0]['source_ref']['file'] );
    }

    public function test_a_blog_post_keeps_the_post_type_post(): void {
        $id = $this->seed_elementor_post( 503, 'News Item', 'post' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( 'post', $items[0]['post_type'] );
    }

    public function test_a_library_header_template_reports_its_template_type(): void {
        $id = $this->seed_elementor_post( 504, 'Site Header', 'elementor_library', null, 'header' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( 'header', $items[0]['template_type'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
    }

    public function test_an_unrecognised_library_template_type_is_blank(): void {
        $id = $this->seed_elementor_post( 505, 'A Popup', 'elementor_library', null, 'popup' );

        $items = ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( '', $items[0]['template_type'] );
    }

    public function test_a_missing_post_yields_an_item_carrying_an_error(): void {
        $items = ( new InstalledPostSource( [ 999999 ] ) )->items();

        $this->assertCount( 1, $items );
        $this->assertNotSame( '', $items[0]['error'] );
        $this->assertSame( [], $items[0]['elements'] );
    }

    public function test_a_post_without_elementor_data_yields_an_item_carrying_an_error(): void {
        $GLOBALS['__test_posts'][ 506 ] = (object) [
            'ID' => 506, 'post_title' => 'Plain', 'post_name' => 'plain',
            'post_type' => 'page', 'post_status' => 'publish',
        ];

        $items = ( new InstalledPostSource( [ 506 ] ) )->items();

        $this->assertNotSame( '', $items[0]['error'] );
    }

    public function test_one_bad_post_does_not_stop_the_others(): void {
        $good = $this->seed_elementor_post( 507, 'Good' );

        $items = ( new InstalledPostSource( [ 999999, $good ] ) )->items();

        $this->assertCount( 2, $items );
        $this->assertNotSame( '', $items[0]['error'] );
        $this->assertSame( '', $items[1]['error'] );
    }

    public function test_reading_a_source_writes_nothing(): void {
        $id = $this->seed_elementor_post( 508, 'Untouched' );

        // $GLOBALS['__test_posts'] holds stdClass objects: PHP copies the
        // array by value but the objects inside it by handle, so a naive
        // `$before = $GLOBALS['__test_posts']` snapshot would still point at
        // the SAME post object the harness's wp_update_post() mutates in
        // place. Casting to array copies the object's scalar properties by
        // value, so this snapshot is actually independent of what happens
        // to the live object afterward.
        $before_post = (array) $GLOBALS['__test_posts'][ $id ];
        $before_meta = $GLOBALS['__test_postmeta'];

        ( new InstalledPostSource( [ $id ] ) )->items();

        $this->assertSame( $before_post, (array) $GLOBALS['__test_posts'][ $id ] );
        $this->assertSame( $before_meta, $GLOBALS['__test_postmeta'] );
    }

    public function test_hfe_templates_carry_their_slot_as_template_type(): void {
        $this->seed_elementor_post( 61, 'Site header', 'elementor-hf' );
        update_post_meta( 61, 'ehf_template_type', 'type_header' );
        $this->seed_elementor_post( 62, 'Site footer', 'elementor-hf' );
        update_post_meta( 62, 'ehf_template_type', 'type_footer' );
        $this->seed_elementor_post( 63, 'Before footer', 'elementor-hf' );
        update_post_meta( 63, 'ehf_template_type', 'type_before_footer' );

        $items = ( new InstalledPostSource( [ 61, 62, 63 ] ) )->items();

        $this->assertSame( 'header', $items[0]['template_type'] );
        $this->assertSame( 'footer', $items[1]['template_type'] );
        $this->assertSame( '', $items[2]['template_type'], 'HFE "before footer" has no Divi area' );
        $this->assertSame( 'page', $items[0]['post_type'] );
    }
}
