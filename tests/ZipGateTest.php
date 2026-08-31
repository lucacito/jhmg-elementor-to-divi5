<?php
// tests/ZipGateTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Admin\AdminPage;

/**
 * The free plugin withholds kit ZIP import. That gate used to read the uploaded
 * filename's extension, while ElementorImportParser detects a ZIP by its PK
 * magic bytes regardless of extension — so renaming kit.zip to kit.json walked
 * straight past the gate and the parser unpacked the kit anyway.
 */
final class ZipGateTest extends TestCase {

    private array $tempFiles = [];

    protected function tearDown(): void {
        foreach ( $this->tempFiles as $file ) {
            if ( file_exists( $file ) ) {
                unlink( $file );
            }
        }
        $this->tempFiles = [];
    }

    private function upload( string $name, string $bytes ): array {
        $path = tempnam( sys_get_temp_dir(), 'edczip' );
        file_put_contents( $path, $bytes );
        $this->tempFiles[] = $path;

        return [ 'name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK ];
    }

    /** is_zip_upload() is protected; the gate is what is under test, not its visibility. */
    private function isZip( array $upload ): bool {
        return ( new ReflectionMethod( AdminPage::class, 'is_zip_upload' ) )
            ->invoke( new AdminPage(), $upload );
    }

    public function test_a_real_zip_is_detected_by_its_bytes(): void {
        $this->assertTrue( $this->isZip( $this->upload( 'kit.zip', "PK\x03\x04rest" ) ) );
    }

    /** The bypass: a kit ZIP renamed to .json. */
    public function test_a_zip_renamed_to_json_is_still_detected(): void {
        $this->assertTrue(
            $this->isZip( $this->upload( 'kit.json', "PK\x03\x04rest" ) ),
            'A renamed ZIP must not walk past the gate'
        );
    }

    public function test_a_real_json_file_is_not_a_zip(): void {
        $this->assertFalse( $this->isZip( $this->upload( 'page.json', '[{"elType":"section"}]' ) ) );
    }

    public function test_a_json_file_named_zip_is_judged_by_its_bytes(): void {
        $this->assertFalse(
            $this->isZip( $this->upload( 'page.zip', '[{"elType":"section"}]' ) ),
            'The bytes decide, so a misnamed JSON file is still importable'
        );
    }

    public function test_it_falls_back_to_the_extension_when_the_file_is_unreadable(): void {
        $this->assertTrue( $this->isZip( [ 'name' => 'kit.zip', 'tmp_name' => '/nonexistent/path' ] ) );
        $this->assertFalse( $this->isZip( [ 'name' => 'page.json', 'tmp_name' => '/nonexistent/path' ] ) );
    }

    public function test_an_empty_file_falls_back_to_the_extension(): void {
        $this->assertTrue( $this->isZip( $this->upload( 'kit.zip', '' ) ) );
        $this->assertFalse( $this->isZip( $this->upload( 'page.json', '' ) ) );
    }

    private function parserSaysZip( array $upload ): bool {
        $parser = new \ElementorDivi5Converter\Parsers\ElementorImportParser();

        return ( new ReflectionMethod( $parser::class, 'isZipFile' ) )
            ->invoke( $parser, $upload['tmp_name'], $upload['name'] );
    }

    /**
     * Whenever the file really is an archive, the gate and the parser must agree.
     * A file the parser would unpack as a kit but the gate would wave through is
     * exactly the bypass this change closes.
     */
    public function test_the_gate_agrees_with_the_parser_on_every_real_archive(): void {
        foreach ( [
            [ 'kit.zip',   "PK\x03\x04rest" ],
            [ 'kit.json',  "PK\x03\x04rest" ],
            [ 'page.json', '[{"elType":"section"}]' ],
        ] as [ $name, $bytes ] ) {
            $upload = $this->upload( $name, $bytes );

            $this->assertSame(
                $this->parserSaysZip( $upload ),
                $this->isZip( $upload ),
                "Gate and parser disagree about {$name}"
            );
        }
    }

    /**
     * One case where they still differ, recorded rather than smoothed over.
     *
     * A JSON file named .zip: the gate reads the bytes and lets it through,
     * while ElementorImportParser::isZipFile() applies its extension fallback
     * even though it could read the bytes, and then fails to open it as an
     * archive. The user sees "Could not open ZIP file" instead of the Pro
     * upsell.
     *
     * Not a bypass in either direction — nothing gets imported that should not
     * — so the parser is left alone here rather than widening this change. The
     * fix belongs in isZipFile(): the extension is only worth consulting when
     * the bytes cannot be read.
     */
    public function test_known_divergence_json_named_zip(): void {
        $upload = $this->upload( 'page.zip', '[{"elType":"section"}]' );

        $this->assertFalse( $this->isZip( $upload ), 'The gate trusts the bytes' );
        $this->assertTrue( $this->parserSaysZip( $upload ), 'The parser still trusts the extension' );
    }
}
