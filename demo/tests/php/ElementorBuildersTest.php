<?php

use PHPUnit\Framework\TestCase;
use function Ferncourt\Demo\{band, col, finalize, heading, load_document, media_files, row};

final class ElementorBuildersTest extends TestCase {

    private function tree(): array {
        return [
            band( [ row( [ col( [ heading( 'A' ), heading( 'B' ) ], 50 ), col( [ heading( 'C' ) ], 50 ) ] ) ] ),
            band( [ heading( 'D' ) ] ),
        ];
    }

    /** @return string[] */
    private function ids( array $elements ): array {
        $ids = [];
        foreach ( $elements as $element ) {
            $ids[] = $element['id'];
            array_push( $ids, ...$this->ids( $element['elements'] ) );
        }
        return $ids;
    }

    public function test_finalize_gives_every_element_a_unique_seven_character_hex_id(): void {
        $ids = $this->ids( finalize( 'home', $this->tree() ) );

        $this->assertCount( 9, $ids );
        $this->assertSame( $ids, array_values( array_unique( $ids ) ) );
        foreach ( $ids as $id ) {
            $this->assertMatchesRegularExpression( '/^[0-9a-f]{7}$/', $id );
        }
    }

    public function test_ids_are_stable_between_builds_and_differ_between_pages(): void {
        $this->assertSame( finalize( 'home', $this->tree() ), finalize( 'home', $this->tree() ) );
        $this->assertNotSame( $this->ids( finalize( 'home', $this->tree() ) ), $this->ids( finalize( 'about', $this->tree() ) ) );
    }

    public function test_only_top_level_containers_are_not_inner(): void {
        $tree = finalize( 'home', $this->tree() );

        $this->assertFalse( $tree[0]['isInner'] );
        $this->assertTrue( $tree[0]['elements'][0]['isInner'] );
        $this->assertTrue( $tree[0]['elements'][0]['elements'][0]['isInner'] );
        $this->assertArrayNotHasKey( 'isInner', $tree[0]['elements'][0]['elements'][0]['elements'][0] );
    }

    public function test_load_document_finalizes_elements_and_fills_defaults(): void {
        $file = tempnam( sys_get_temp_dir(), 'doc' ) . '.php';
        file_put_contents( $file, '<?php return static fn ( $ctx ) => [ "title" => "T", "slug" => "t", "elements" => [ \Ferncourt\Demo\heading( "Hi" ) ] ];' );

        $doc = load_document( $file, demo_test_context() );
        unlink( $file );

        $this->assertSame( 'T', $doc['title'] );
        $this->assertSame( '', $doc['template'] );
        $this->assertFalse( $doc['front_page'] );
        $this->assertSame( [], $doc['survive'] );
        $this->assertSame( [], $doc['survive_exact'] );
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{7}$/', $doc['elements'][0]['id'] );
    }

    public function test_context_image_carries_alt_text_and_rejects_unknown_files(): void {
        $ctx   = demo_test_context();
        $image = $ctx->image( 'hero-lounge.jpg' );

        $this->assertSame( 'Members working on sofas in a bright lounge with plants', $image['alt'] );
        $this->assertSame( 'library', $image['source'] );
        $this->assertIsInt( $image['id'] );

        $this->expectException( InvalidArgumentException::class );
        $ctx->image( 'nope.jpg' );
    }

    public function test_context_dates_count_from_the_build_time(): void {
        $this->assertSame( '2026-10-06 18:00', demo_test_context()->date( '+21 days 18:00' ) );
    }

    public function test_media_files_cover_every_shot_plus_logo_and_tour(): void {
        $files = media_files( __DIR__ . '/../../content' );

        $this->assertCount( 33, $files );
        $this->assertArrayHasKey( 'logo.png', $files );
        $this->assertArrayHasKey( 'tour-poster.jpg', $files );
        $this->assertArrayHasKey( 'tour.webm', $files );
        foreach ( $files as $file => $media ) {
            $this->assertFileExists( $media['path'], "{$file} is listed but missing" );
        }
    }
}
