<?php
// tests/ThemeBuilderDedupeTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Exporters\DiviExporter;
use ElementorDivi5Converter\Pro\Exporters\DiviThemeBuilderExporter;

/**
 * Re-importing a header or footer used to stack duplicates instead of replacing
 * what was already there.
 *
 * fixtures/theme-builder/accumulated-templates.json is what that looked like on
 * a real site, captured from the e2e container before it was purged: five
 * published copies of one footer, three of one header, and three separate
 * et_template posts each marked _et_default. Divi picks among the defaults, so
 * the customer's global header starts changing for no visible reason — and
 * re-importing to fix it makes it worse. This is a paid feature failing in the
 * workflow that justifies the price, in a way the customer cannot diagnose.
 */
final class ThemeBuilderDedupeTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
        $GLOBALS['__test_posts']         = [];
        $GLOBALS['__test_postmeta']      = [];
        $GLOBALS['__test_next_post_id']  = 1000;
    }

    private function exporter(): DiviThemeBuilderExporter {
        return new DiviThemeBuilderExporter( new DiviExporter() );
    }

    private function diviData(): array {
        return [ 'divi' => [ 'elements' => [] ], 'report' => [], 'unsupported' => [] ];
    }

    /** @return int[] every post id of this type currently in the store */
    private function idsOfType( string $type ): array {
        $ids = [];
        foreach ( $GLOBALS['__test_posts'] as $id => $post ) {
            if ( ( $post->post_type ?? '' ) === $type ) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private function defaultTemplateCount(): int {
        $count = 0;
        foreach ( $this->idsOfType( 'et_template' ) as $id ) {
            if ( get_post_meta( $id, '_et_default', true ) === '1' ) {
                $count++;
            }
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // The reproduction, from the fixture
    // -------------------------------------------------------------------------

    /**
     * The fixture's five identical footers came from importing the same footer
     * five times. Doing that now must leave one.
     */
    public function test_reimporting_the_same_footer_five_times_leaves_one_layout(): void {
        $source = [ 'kind' => 'installed', 'post_id' => 487, 'file' => null ];

        for ( $i = 0; $i < 5; $i++ ) {
            $result = $this->exporter()->saveFooter( 'Default Website Template', $this->diviData(), $source );
            $this->assertTrue( $result['success'] );
        }

        $this->assertCount( 1, $this->idsOfType( 'et_footer_layout' ) );
        $this->assertCount( 1, $this->idsOfType( 'et_template' ) );
        $this->assertSame( 1, $this->defaultTemplateCount(), 'Only one template may claim to be the default' );
    }

    public function test_reimporting_the_same_header_three_times_leaves_one_layout(): void {
        $source = [ 'kind' => 'installed', 'post_id' => 55, 'file' => null ];

        for ( $i = 0; $i < 3; $i++ ) {
            $this->exporter()->saveHeader( 'Default Website Template Header Layout', $this->diviData(), $source );
        }

        $this->assertCount( 1, $this->idsOfType( 'et_header_layout' ) );
        $this->assertCount( 1, $this->idsOfType( 'et_template' ) );
        $this->assertSame( 1, $this->defaultTemplateCount() );
    }

    /** The same post id must come back, so the site keeps pointing at one layout. */
    public function test_a_reimport_returns_the_same_layout_post(): void {
        $source = [ 'kind' => 'installed', 'post_id' => 55, 'file' => null ];

        $first  = $this->exporter()->saveHeader( 'Header', $this->diviData(), $source );
        $second = $this->exporter()->saveHeader( 'Header', $this->diviData(), $source );

        $this->assertSame( $first['post_id'], $second['post_id'] );
        $this->assertSame( $first['template_id'], $second['template_id'] );
    }

    /**
     * The Theme Builder container lists one entry per rule set, so the same
     * template must not be attached twice.
     */
    public function test_the_container_lists_the_template_once(): void {
        $source = [ 'kind' => 'installed', 'post_id' => 55, 'file' => null ];

        $first = $this->exporter()->saveHeader( 'Header', $this->diviData(), $source );
        $this->exporter()->saveHeader( 'Header', $this->diviData(), $source );
        $this->exporter()->saveHeader( 'Header', $this->diviData(), $source );

        $attached = get_post_meta( (int) $first['theme_builder_id'], '_et_template', false );

        $this->assertCount( 1, $attached );
    }

    // -------------------------------------------------------------------------
    // Genuinely different imports still get their own posts
    // -------------------------------------------------------------------------

    public function test_two_different_headers_remain_two_layouts_on_the_one_default_template(): void {
        $this->exporter()->saveHeader( 'Main header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 55 ] );
        $second = $this->exporter()->saveHeader( 'Campaign header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 99 ] );

        // Both layouts stay in the library; the site's one global header is the latest import.
        $this->assertCount( 2, $this->idsOfType( 'et_header_layout' ) );
        $this->assertCount( 1, $this->idsOfType( 'et_template' ) );
        $this->assertSame( $second['post_id'], (int) get_post_meta( (int) $second['template_id'], '_et_header_layout_id', true ) );
    }

    /**
     * Divi applies one et_template per request and treats an area with no
     * `_et_<area>_layout_id` and no `_et_<area>_layout_enabled` as "override and
     * hide" (theme-builder.php et_theme_builder_get_template()). Two default
     * templates, each carrying only its own area, therefore hid the footer or
     * the header — and the page body — on a real site (docs/known-issues.md).
     */
    public function test_a_header_and_a_footer_share_one_default_template_with_the_body_enabled(): void {
        $header = $this->exporter()->saveHeader( 'Site chrome', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );
        $footer = $this->exporter()->saveFooter( 'Site chrome', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 8 ] );

        $this->assertCount( 1, $this->idsOfType( 'et_header_layout' ) );
        $this->assertCount( 1, $this->idsOfType( 'et_footer_layout' ) );
        $this->assertCount( 1, $this->idsOfType( 'et_template' ), 'one default template carries both areas' );
        $this->assertSame( $header['template_id'], $footer['template_id'] );

        $template = (int) $header['template_id'];
        $this->assertSame( $header['post_id'], (int) get_post_meta( $template, '_et_header_layout_id', true ) );
        $this->assertSame( $footer['post_id'], (int) get_post_meta( $template, '_et_footer_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_header_layout_enabled', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_footer_layout_enabled', true ) );
        $this->assertSame( 0, (int) get_post_meta( $template, '_et_body_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_body_layout_enabled', true ) );
    }

    public function test_a_header_alone_leaves_the_footer_and_body_to_the_theme(): void {
        $header   = $this->exporter()->saveHeader( 'Header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );
        $template = (int) $header['template_id'];

        $this->assertSame( 0, (int) get_post_meta( $template, '_et_footer_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_footer_layout_enabled', true ) );
        $this->assertSame( 0, (int) get_post_meta( $template, '_et_body_layout_id', true ) );
        $this->assertSame( '1', get_post_meta( $template, '_et_body_layout_enabled', true ) );
    }

    public function test_reimporting_the_header_keeps_the_footer_on_the_template(): void {
        $this->exporter()->saveHeader( 'Header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );
        $footer = $this->exporter()->saveFooter( 'Footer', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 8 ] );
        $header = $this->exporter()->saveHeader( 'Header', $this->diviData(), [ 'kind' => 'installed', 'post_id' => 7 ] );

        $this->assertSame( $footer['post_id'], (int) get_post_meta( (int) $header['template_id'], '_et_footer_layout_id', true ) );
        $this->assertSame( 1, $this->defaultTemplateCount() );
    }

    /**
     * An uploaded file carries no post id, so identity falls back to the title —
     * which is exactly what a re-upload of the same file reproduces.
     */
    public function test_an_uploaded_file_dedupes_on_its_title(): void {
        $this->exporter()->saveHeader( 'My Header', $this->diviData(), [ 'kind' => 'upload', 'file' => 'a.json' ] );
        $this->exporter()->saveHeader( 'My Header', $this->diviData(), [ 'kind' => 'upload', 'file' => 'a.json' ] );

        $this->assertCount( 1, $this->idsOfType( 'et_header_layout' ) );
    }

    public function test_two_uploads_with_different_titles_stay_separate(): void {
        $this->exporter()->saveHeader( 'Main header', $this->diviData(), [ 'kind' => 'upload' ] );
        $this->exporter()->saveHeader( 'Landing header', $this->diviData(), [ 'kind' => 'upload' ] );

        $this->assertCount( 2, $this->idsOfType( 'et_header_layout' ) );
    }

    /** Older callers pass no source at all and must keep working. */
    public function test_it_still_works_without_a_source_ref(): void {
        $result = $this->exporter()->saveHeader( 'Header', $this->diviData() );

        $this->assertTrue( $result['success'] );
        $this->assertGreaterThan( 0, $result['post_id'] );
    }

    // -------------------------------------------------------------------------
    // The fixture is the record of what this prevents
    // -------------------------------------------------------------------------

    public function test_the_captured_fixture_still_documents_the_duplication(): void {
        $path = __DIR__ . '/../fixtures/theme-builder/accumulated-templates.json';
        $this->assertFileExists( $path );

        $data = json_decode( (string) file_get_contents( $path ), true );
        $this->assertIsArray( $data );

        $footers = array_values( array_filter(
            $data['posts'],
            static fn( array $p ): bool => $p['post_type'] === 'et_footer_layout'
        ) );

        // Five footer layouts, all the same size: one footer, imported five times.
        $this->assertCount( 5, $footers );
        $this->assertCount(
            1,
            array_unique( array_column( $footers, 'content_length' ) ),
            'The captured footers are byte-identical copies of one footer'
        );

        $defaults = array_filter(
            $data['posts'],
            static fn( array $p ): bool =>
                $p['post_type'] === 'et_template' && ( $p['meta']['_et_default'] ?? '' ) === '1'
        );

        $this->assertGreaterThan(
            1,
            count( $defaults ),
            'More than one default template is what made Divi pick arbitrarily'
        );
    }
}
