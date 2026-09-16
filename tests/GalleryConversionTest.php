<?php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;
use ElementorDivi5Converter\Helpers\AttachmentResolver;

/**
 * divi/gallery lists the attachments in image.advanced.galleryIds
 * (GalleryModule.php:754, get_posts include/post__in); with none it lists the
 * whole media library. Divi paginates at module.advanced.postsNumber, default 4.
 */
final class GalleryConversionTest extends TestCase {
    private function convert( string $type, array $settings ): array {
        return ( new ConverterEngine() )->convert( [ [ 'id' => 'g1', 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ] ] );
    }

    private function images( int $n ): array {
        $out = [];
        for ( $i = 1; $i <= $n; $i++ ) {
            $out[] = [ 'id' => 100 + $i, 'url' => "https://x.test/img-{$i}.jpg" ];
        }
        return $out;
    }

    public function test_image_carousel_with_several_slides_becomes_a_gallery_grid(): void {
        $block = $this->convert( 'image-carousel', [ 'carousel' => $this->images( 4 ), 'slides_to_show' => '4', 'thumbnail_size' => 'full' ] )['divi']['elements'][0];
        $this->assertSame( 'divi/gallery', $block['name'] );
        $this->assertSame( [ 101, 102, 103, 104 ], $block['settings']['image']['advanced']['galleryIds']['desktop']['value'] );
        $this->assertSame( '4', $block['settings']['module']['advanced']['postsNumber']['desktop']['value'] );
        $this->assertSame( '4', $block['settings']['galleryGrid']['decoration']['layout']['desktop']['value']['gridColumnCount'] );
        $this->assertSame( 'off', $block['settings']['module']['advanced']['showTitleAndCaption']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'fullwidth', $block['settings']['module']['advanced'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'carousel' );
    }

    public function test_single_slide_carousel_becomes_divis_slider_layout_with_autoplay(): void {
        $block = $this->convert( 'image-carousel', [ 'carousel' => $this->images( 3 ), 'slides_to_show' => '1', 'autoplay' => 'yes', 'autoplay_speed' => 4000 ] )['divi']['elements'][0];
        $this->assertSame( 'on', $block['settings']['module']['advanced']['fullwidth']['desktop']['value'] );
        $this->assertSame( 'on', $block['settings']['module']['advanced']['auto']['desktop']['value'] );
        $this->assertSame( '4000', $block['settings']['module']['advanced']['autoSpeed']['desktop']['value'] );
        $this->assertArrayNotHasKey( 'galleryGrid', $block['settings'] );
    }

    public function test_carousel_without_attachments_falls_back_to_inline_images_and_warns(): void {
        $result = $this->convert( 'image-carousel', [ 'carousel' => [ [ 'url' => 'https://x.test/a.jpg' ], [ 'url' => 'https://x.test/b.jpg' ] ] ] );
        $block  = $result['divi']['elements'][0];
        $this->assertSame( 'divi/text', $block['name'] );
        $this->assertStringContainsString( 'src="https://x.test/a.jpg"', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertStringNotContainsString( 'max-height:60px', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertNotEmpty( $result['report']['warnings'] );
    }

    public function test_filterable_gallery_uses_ids_and_reports_filters_names_and_captions(): void {
        $result = $this->convert( 'eael-filterable-gallery', [
            'columns' => '3',
            'eael_fg_controls' => [ [ 'eael_fg_control' => 'Rooms' ], [ 'eael_fg_control' => 'Desks' ] ],
            'eael_fg_gallery_items' => [
                [ 'eael_fg_gallery_item_name' => 'Lounge', 'eael_fg_gallery_item_content' => 'Sofas', 'fg_item_cat' => 'Rooms', 'eael_fg_gallery_img' => [ 'id' => 201, 'url' => 'https://x.test/1.jpg' ] ],
                [ 'eael_fg_gallery_item_name' => 'Desk', 'fg_item_cat' => 'Desks', 'eael_fg_gallery_img' => [ 'id' => 202, 'url' => 'https://x.test/2.jpg' ] ],
            ],
        ] );
        $block = $result['divi']['elements'][0];
        $this->assertSame( [ 201, 202 ], $block['settings']['image']['advanced']['galleryIds']['desktop']['value'] );
        $this->assertSame( '2', $block['settings']['module']['advanced']['postsNumber']['desktop']['value'] );
        $this->assertSame( '3', $block['settings']['galleryGrid']['decoration']['layout']['desktop']['value']['gridColumnCount'] );
        $this->assertArrayNotHasKey( 'innerContent', $block['settings']['galleryGrid'] );
        $kinds = array_column( $result['report']['not_carried_over'], 'detail', 'kind' );
        $this->assertStringContainsString( 'Rooms, Desks', implode( ' | ', array_column( $result['report']['not_carried_over'], 'detail' ) ) );
        $this->assertSame( 'gallery_extras', $result['report']['not_carried_over'][0]['kind'] );
        DiviModuleSchema::assertBlocksValid( [ $block ], 'filterable gallery' );
    }

    public function test_core_gallery_lists_every_image(): void {
        $block = $this->convert( 'image-gallery', [ 'wp_gallery' => $this->images( 6 ), 'gallery_columns' => 3 ] )['divi']['elements'][0];
        $this->assertSame( '6', $block['settings']['module']['advanced']['postsNumber']['desktop']['value'] );
        $this->assertSame( '3', $block['settings']['galleryGrid']['decoration']['layout']['desktop']['value']['gridColumnCount'] );
    }

    public function test_resolver_keeps_ids_outside_wordpress(): void {
        $this->assertSame( 7, AttachmentResolver::idFor( 7, 'https://x.test/a.jpg' ) );
        $this->assertSame( 0, AttachmentResolver::idFor( 0, 'https://x.test/a.jpg' ) );
        $this->assertSame( [ 1, 3 ], AttachmentResolver::ids( [ [ 'id' => 1 ], [ 'url' => 'u' ], [ 'id' => '3', 'url' => 'v' ] ] ) );
    }
}
