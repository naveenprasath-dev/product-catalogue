<?php

declare(strict_types=1);

namespace App\Catalogue\Currency;

use App\Catalogue\Data\Currency;

/** The ONE place prices are converted from the base currency (USD) into a display currency. */
final readonly class CurrencyConverter
{
    /**
     * Base-currency amount → `$to`, rounded half-up to 2 decimals.
     *
     * The exact product of a 2-decimal price and an 8-decimal rate has at most 10 decimals, so rounding
     * to 8 first removes float noise (1.005 is stored as 1.00499…) before the half-up rounding to cents.
     * (No bcmath in the image; PHP 8.4's round() is exact for the pre-rounded value.)
     */
    public function fromBase(float $amount, Currency $to): float
    {
        return round(round($amount * $to->rate, 8), 2, PHP_ROUND_HALF_UP);
    }
}
