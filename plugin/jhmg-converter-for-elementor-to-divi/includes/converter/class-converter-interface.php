<?php

namespace ElementorDivi5Converter\Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ConverterInterface {
    public function convert( array $element ): array;
}
