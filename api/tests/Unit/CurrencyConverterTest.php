<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalogue\Currency\CurrencyConverter;
use App\Catalogue\Data\Currency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CurrencyConverterTest extends TestCase
{
    private static function currency(float $rate): Currency
    {
        return new Currency('XTS', 'Test', '¤', $rate, true, false, null);
    }

    /** @return array<string, array{0: float, 1: float, 2: float}> amount, rate, expected */
    public static function cases(): array
    {
        return [
            'rate 1 keeps the price' => [109.95, 1.0, 109.95],
            'EUR' => [109.95, 0.91003, 100.06],
            'INR' => [109.95, 92.93, 10217.65],
            'half rounds up (float 1.005 is 1.00499…)' => [1.005, 1.0, 1.01],
            'half rounds up (0.285)' => [0.285, 1.0, 0.29],
            'half rounds up after multiplying' => [1.0, 0.765, 0.77],
            'just below half rounds down' => [1.0, 0.76499999, 0.76],
            'tiny amounts' => [0.5, 0.01, 0.01],
            'zero' => [0.0, 0.91, 0.0],
            'large rate' => [999.99, 25000.12345678, 24999873.46],
        ];
    }

    #[DataProvider('cases')]
    public function test_converts_from_base_and_rounds_half_up_to_cents(float $amount, float $rate, float $expected): void
    {
        $this->assertSame($expected, (new CurrencyConverter)->fromBase($amount, self::currency($rate)));
    }
}
