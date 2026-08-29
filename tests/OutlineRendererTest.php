<?php
// tests/OutlineRendererTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\OutlineRenderer;
use ElementorDivi5Converter\Conversion\ConversionOutline;

class OutlineRendererTest extends TestCase {

    public function test_an_empty_outline_explains_itself_rather_than_rendering_an_empty_list(): void {
        $html = OutlineRenderer::render( [] );

        $this->assertStringNotContainsString( '<ul', $html );
        $this->assertStringContainsString( 'Nothing', $html );
    }

    public function test_it_renders_a_node_with_its_label(): void {
        $html = OutlineRenderer::render( ConversionOutline::build( [ [ 'name' => 'divi/heading' ] ] ) );

        $this->assertStringContainsString( '<ul class="edc-outline">', $html );
        $this->assertStringContainsString( 'edc-outline-node--module', $html );
        $this->assertStringContainsString( 'Heading', $html );
    }

    public function test_it_nests_children_inside_their_parent(): void {
        $outline = ConversionOutline::build( [ [
            'name'     => 'divi/section',
            'elements' => [ [ 'name' => 'divi/row' ] ],
        ] ] );

        $html = OutlineRenderer::render( $outline );

        $this->assertStringContainsString( 'edc-outline-node--section', $html );
        $this->assertStringContainsString( 'edc-outline-node--row', $html );
        // The row's <li> must appear after the section's opening <li>.
        $this->assertLessThan(
            strpos( $html, 'edc-outline-node--row' ),
            strpos( $html, 'edc-outline-node--section' )
        );
    }

    public function test_it_marks_unsupported_nodes(): void {
        $html = OutlineRenderer::render( [ [
            'type' => 'module', 'name' => 'divi/code', 'label' => 'Code',
            'unsupported' => true, 'children' => [],
        ] ] );

        $this->assertStringContainsString( 'edc-outline-node--unsupported', $html );
    }

    public function test_it_does_not_mark_supported_nodes_as_unsupported(): void {
        $html = OutlineRenderer::render( [ [
            'type' => 'module', 'name' => 'divi/text', 'label' => 'Text',
            'unsupported' => false, 'children' => [],
        ] ] );

        $this->assertStringNotContainsString( 'edc-outline-node--unsupported', $html );
    }

    public function test_it_escapes_labels(): void {
        $html = OutlineRenderer::render( [ [
            'type' => 'module', 'name' => 'divi/x', 'label' => '<script>alert(1)</script>',
            'unsupported' => false, 'children' => [],
        ] ] );

        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }

    public function test_every_list_it_opens_it_also_closes(): void {
        $outline = ConversionOutline::build( [ [
            'name'     => 'divi/section',
            'elements' => [ [
                'name'     => 'divi/row',
                'elements' => [ [ 'name' => 'divi/column', 'elements' => [ [ 'name' => 'divi/text' ] ] ] ],
            ] ],
        ] ] );

        $html = OutlineRenderer::render( $outline );

        $this->assertSame( substr_count( $html, '<ul' ), substr_count( $html, '</ul>' ) );
        $this->assertSame( substr_count( $html, '<li' ), substr_count( $html, '</li>' ) );
    }

    public function test_render_returns_a_string_and_never_echoes(): void {
        ob_start();
        $result = OutlineRenderer::render( ConversionOutline::build( [ [ 'name' => 'divi/heading' ] ] ) );
        $echoed = ob_get_clean();

        $this->assertIsString( $result );
        $this->assertSame( '', $echoed );
        $this->assertNotSame( '', $result );
    }

    public function test_it_renders_multiple_sibling_nodes_each_only_once(): void {
        $outline = ConversionOutline::build( [
            [ 'name' => 'divi/heading' ],
            [ 'name' => 'divi/text' ],
        ] );

        $html = OutlineRenderer::render( $outline );

        $this->assertSame( 1, substr_count( $html, 'Heading' ) );
        $this->assertSame( 1, substr_count( $html, '>Text<' ) );
    }
}
