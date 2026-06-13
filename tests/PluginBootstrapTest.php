<?php

use PHPUnit\Framework\TestCase;
use ElementorDivi5Converter\Plugin;

final class PluginBootstrapTest extends TestCase {
    public function test_plugin_class_is_available(): void {
        $this->assertTrue( class_exists( Plugin::class ) );
    }
}
