<?php
use PHPUnit\Framework\TestCase;

/**
 * The committed schema extract must match the Divi checked into references/.
 * Regenerate with: php scripts/divi-module-schema.php
 */
final class DiviModuleSchemaFixtureTest extends TestCase {
    private const ROOT = __DIR__ . '/..';

    protected function setUp(): void {
        if ( ! is_dir( self::ROOT . '/references/Divi/includes/builder-5' ) ) {
            $this->markTestSkipped( 'references/Divi is not present; the committed extract cannot be checked.' );
        }
        require_once self::ROOT . '/scripts/divi-module-schema.php';
    }

    public function test_committed_module_schema_matches_references_divi(): void {
        $expected = edc_divi_schema_build(
            self::ROOT . '/references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components',
            self::ROOT . '/references/Divi/style.css'
        );
        $committed = json_decode( (string) file_get_contents( self::ROOT . '/fixtures/divi-schema/modules.json' ), true );

        $this->assertSame( $expected, $committed, 'fixtures/divi-schema/modules.json is stale: run php scripts/divi-module-schema.php' );
    }

    public function test_committed_fa_icons_match_references_divi(): void {
        $expected  = edc_fa_icons_build( self::ROOT . '/references/Divi/includes/builder/feature/icon-manager/full_icons_list.json' );
        $committed = require self::ROOT . '/plugin/jhmg-converter-for-elementor-to-divi/includes/data/fa-icons.php';

        $this->assertSame( $expected, $committed, 'includes/data/fa-icons.php is stale: run php scripts/divi-module-schema.php' );
        $this->assertSame( [ 'unicode' => '&#xf39e;', 'solid' => '400' ], $committed['facebook-f'] );
        $this->assertSame( [ 'unicode' => '&#xf017;', 'solid' => '900', 'line' => '400' ], $committed['clock'] );
    }

    public function test_schema_records_the_paths_this_work_depends_on(): void {
        $schema = json_decode( (string) file_get_contents( self::ROOT . '/fixtures/divi-schema/modules.json' ), true );
        $m      = $schema['modules'];

        $this->assertSame( '5.7.4', $schema['diviVersion'] );
        $this->assertContains( 'background', $m['divi/column']['attributes']['module']['decoration'] );
        $this->assertContains( 'dateTime', $m['divi/countdown-timer']['attributes']['content']['advanced'] );
        $this->assertContains( 'enablePercentSign', $m['divi/number-counter']['attributes']['number']['advanced'] );
        $this->assertContains( 'galleryIds', $m['divi/gallery']['attributes']['image']['advanced'] );
        $this->assertSame( [ 'currency', 'per' ], $m['divi/pricing-table']['attributes']['currencyFrequency']['innerContent'] );
        $this->assertContains( 'text', $m['divi/button']['attributes']['button']['innerContent'] );
        $this->assertContains( '*', $m['divi/button']['attributes']['button']['innerContent'] );
        $this->assertSame( [ 'src' ], $m['divi/testimonial']['attributes']['portrait']['innerContent'] );
        $this->assertSame( 'headingLink', $m['divi/blurb']['attributes']['title']['elementType'] );
        $this->assertSame( [ 'divi/pricing-table' ], $m['divi/pricing-tables']['childrenName'] );
        $this->assertSame( [ 'divi/social-media-follow-network' ], $m['divi/social-media-follow']['childrenName'] );
    }
}
