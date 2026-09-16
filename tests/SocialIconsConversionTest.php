<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor 4.1.3 social-icons.php stores each item's icon under `social_icon`
 * as {value: "fab fa-instagram", library: "fa-brands"} (ICONS control,
 * fa4compatibility "social" keeps the legacy `social` string). The converter
 * read `social_icon` as a string, found nothing, and fell back to Facebook.
 * Divi's network slugs: SocialMediaFollowItemModule::get_social_networks().
 */
final class SocialIconsConversionTest extends TestCase {
    private function convert( array $items ): array {
        return ( new ConverterEngine() )->convert( [ [ 'id' => 's1', 'elType' => 'widget', 'widgetType' => 'social-icons', 'settings' => [ 'social_icon_list' => $items ], 'elements' => [] ] ] );
    }

    private function networks( array $result ): array {
        return array_map( static fn( array $c ): string => $c['settings']['socialNetwork']['innerContent']['desktop']['value']['title'], $result['divi']['elements'][0]['elements'] );
    }

    public function test_icons_control_values_map_to_their_networks(): void {
        $result = $this->convert( [
            [ '_id' => 'a', 'social_icon' => [ 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.instagram.com/' ] ],
            [ '_id' => 'b', 'social_icon' => [ 'value' => 'fab fa-linkedin-in', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.linkedin.com/' ] ],
            [ '_id' => 'c', 'social_icon' => [ 'value' => 'fab fa-facebook-f', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.facebook.com/' ] ],
            [ '_id' => 'd', 'social_icon' => [ 'value' => 'fab fa-x-twitter', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://x.com/' ] ],
        ] );
        $this->assertSame( [ 'instagram', 'linkedin', 'facebook', 'twitter' ], $this->networks( $result ) );
        $this->assertSame( 'https://www.instagram.com/', $result['divi']['elements'][0]['elements'][0]['settings']['socialNetwork']['innerContent']['desktop']['value']['link'] );
        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], 'social icons' );
    }

    public function test_legacy_social_string_still_maps(): void {
        $this->assertSame( [ 'youtube' ], $this->networks( $this->convert( [ [ 'social' => 'fa fa-youtube', 'link' => [ 'url' => 'https://youtube.com/' ] ] ] ) ) );
    }

    public function test_unknown_network_is_skipped_and_reported(): void {
        $result = $this->convert( [
            [ 'social_icon' => [ 'value' => 'fab fa-mastodon', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://m.test/' ] ],
            [ 'social_icon' => [ 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ], 'link' => [ 'url' => 'https://www.instagram.com/' ] ],
        ] );
        $this->assertSame( [ 'instagram' ], $this->networks( $result ) );
        $this->assertSame( 'social_network', $result['report']['not_carried_over'][0]['kind'] );
        $this->assertStringContainsString( 'mastodon', $result['report']['not_carried_over'][0]['detail'] );
    }
}
