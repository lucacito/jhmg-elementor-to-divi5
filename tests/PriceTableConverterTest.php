<?php
// tests/PriceTableConverterTest.php
use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Converter\ConverterEngine;

/**
 * Elementor's native "Price Table" widget ('price-table'), converted by
 * PriceTableConverter to divi/pricing-tables + divi/pricing-table.
 *
 * PriceTableConverter previously wrote to 'priceText'/'perText'/'bulletItems'/
 * 'buttonText'/'buttonUrl' under module.advanced — none of which are declared
 * attributes of divi/pricing-table (PricingTableModule.php,
 * fixtures/divi-schema/modules.json). The real attributes are plain
 * title/subtitle/price fields, a structured
 * currencyFrequency.innerContent.value.{currency,per}, a free-form 'content'
 * body for the feature list, and a 'button' field — the same shape
 * WcfAdvancePricingTableConverter (Animation Addons' pricing table widget)
 * was built against and verified live. No test exercised
 * DiviModuleSchema::assertBlocksValid() against this converter's output
 * before, so the bug shipped silently: a converted price table rendered with
 * no visible title, price, features or button.
 */
final class PriceTableConverterTest extends TestCase {

    protected function setUp(): void {
        edc_test_reset_hooks();
    }

    private function convert( array $settings ): array {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ [
            'id'         => 'w1',
            'elType'     => 'widget',
            'widgetType' => 'price-table',
            'settings'   => $settings,
            'elements'   => [],
        ] ] );

        DiviModuleSchema::assertBlocksValid( $result['divi']['elements'], 'widget price-table' );

        return [ $result['divi']['elements'][0], $result ];
    }

    public function test_price_table_writes_to_the_real_schema_fields(): void {
        [ $block, $result ] = $this->convert( [
            'title'           => 'Pro Plan',
            'price'           => '9.99',
            'currency_symbol' => '$',
            'period'          => 'month',
            'features_list'   => [
                [ 'item_text' => 'Starter Pack Included' ],
                [ 'item_text' => 'Venue Booking' ],
            ],
            'button_text' => 'Choose Plan',
            'button_url'  => [ 'url' => 'https://x.test/choose' ],
        ] );

        $this->assertSame( 'divi/pricing-tables', $block['name'] );
        $table = $block['elements'][0];
        $this->assertSame( 'divi/pricing-table', $table['name'] );
        $s = $table['settings'];

        $this->assertSame( 'Pro Plan', $s['title']['innerContent']['desktop']['value'] );
        $this->assertSame( '9.99', $s['price']['innerContent']['desktop']['value'] );
        $this->assertSame( [ 'currency' => '$', 'per' => 'month' ], $s['currencyFrequency']['innerContent']['desktop']['value'] );
        $this->assertSame( "Starter Pack Included\nVenue Booking", $s['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 'Choose Plan', $s['button']['innerContent']['desktop']['value']['text'] );
        $this->assertSame( 'https://x.test/choose', $s['button']['innerContent']['desktop']['value']['linkUrl'] );
    }

    public function test_price_table_with_no_features_omits_content(): void {
        [ $block ] = $this->convert( [ 'title' => 'Basic' ] );

        $this->assertArrayNotHasKey( 'content', $block['elements'][0]['settings'] );
    }
}
