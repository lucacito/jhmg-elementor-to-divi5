<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Helpers\FontAwesomeIcons;

/**
 * Divi 5 renders an icon from {type, unicode, weight} (IconModule.php:344-350:
 * type fa → FontAwesome font). includes/data/fa-icons.php is generated from Divi's
 * own icon list, so every mapped icon exists in the font Divi ships.
 */
final class FontAwesomeIconsTest extends TestCase {
    public function test_brand_icon(): void {
        $this->assertSame( [ 'type' => 'fa', 'unicode' => '&#xf39e;', 'weight' => '400' ], FontAwesomeIcons::diviIcon( 'fab fa-facebook-f' ) );
    }

    public function test_solid_and_regular_weights(): void {
        $this->assertSame( '900', FontAwesomeIcons::diviIcon( 'fas fa-clock' )['weight'] );
        $this->assertSame( '400', FontAwesomeIcons::diviIcon( 'far fa-clock' )['weight'] );
        $this->assertSame( '&#xf017;', FontAwesomeIcons::diviIcon( 'far fa-clock' )['unicode'] );
    }

    public function test_control_value_and_unknowns(): void {
        $this->assertSame( '&#xf0e0;', FontAwesomeIcons::fromControl( [ 'value' => 'fas fa-envelope', 'library' => 'fa-solid' ] )['unicode'] );
        $this->assertNull( FontAwesomeIcons::fromControl( [ 'value' => [ 'id' => 5, 'url' => 'x.svg' ], 'library' => 'svg' ] ) );
        $this->assertNull( FontAwesomeIcons::diviIcon( 'fas fa-no-such-icon' ) );
        $this->assertNull( FontAwesomeIcons::diviIcon( '' ) );
    }
}
