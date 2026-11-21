<?php

declare(strict_types=1);

namespace App\Catalogue\Contracts;

use App\Catalogue\Exceptions\RatesUnavailable;

/** Where live exchange rates come from. Used only by RateSyncer (command / admin button), never per shop request. */
interface CurrencyRateSource
{
    /**
     * Latest rates for 1 unit of `$base`.
     *
     * @return array<string, float> upper-case currency code => rate (> 0)
     *
     * @throws RatesUnavailable network error, HTTP error, or an unusable payload
     */
    public function latest(string $base): array;

    /** A label for messages, e.g. "Frankfurter". */
    public function name(): string;
}
