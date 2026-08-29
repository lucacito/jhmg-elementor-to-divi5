<?php
// tests/BootstrapStubsTest.php
use PHPUnit\Framework\TestCase;

class BootstrapStubsTest extends TestCase {

    public function test_sanitize_text_field_strips_tags_and_trims(): void {
        $this->assertSame( 'hello', sanitize_text_field( "  <b>hello</b>\n" ) );
    }

    public function test_absint_coerces_to_non_negative_int(): void {
        $this->assertSame( 12, absint( '12abc' ) );
        $this->assertSame( 5, absint( -5 ) );
    }

    public function test_get_post_types_returns_registered_names(): void {
        $types = get_post_types();
        $this->assertContains( 'page', $types );
        $this->assertContains( 'post', $types );
    }

    public function test_checked_returns_attribute_when_values_match(): void {
        $this->assertSame( " checked='checked'", checked( 'a', 'a', false ) );
        $this->assertSame( '', checked( 'a', 'b', false ) );
    }

    public function test_selected_returns_attribute_when_values_match(): void {
        $this->assertSame( " selected='selected'", selected( 'a', 'a', false ) );
        $this->assertSame( '', selected( 'a', 'b', false ) );
    }
}
