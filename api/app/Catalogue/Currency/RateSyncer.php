<?php

declare(strict_types=1);

namespace App\Catalogue\Currency;

use App\Catalogue\Contracts\CurrencyRateSource;
use App\Catalogue\Contracts\CurrencyRepository;
use App\Catalogue\Data\Currency;
use App\Catalogue\Data\RateSyncResult;
use App\Catalogue\Exceptions\RatesUnavailable;
use Carbon\CarbonImmutable;

/**
 * Refreshes the rates of enabled, non-base currencies from the live source in one transaction.
 * Everything is fetched and checked before the database is touched, so a failure changes nothing.
 */
final readonly class RateSyncer
{
    /** The rates column is decimal(18,8). */
    private const float MAX_RATE = 9_999_999_999.0;

    public function __construct(
        private CurrencyRateSource $source,
        private CurrencyRepository $currencies,
        private string $baseCode,
    ) {}

    /** @throws RatesUnavailable */
    public function sync(): RateSyncResult
    {
        $rates = $this->source->latest($this->baseCode);

        $targets = array_values(array_map(
            fn (Currency $c): string => $c->code,
            array_filter($this->currencies->enabled(), fn (Currency $c): bool => $c->code !== $this->baseCode),
        ));

        $updates = [];
        foreach ($targets as $code) {
            $rate = isset($rates[$code]) ? round($rates[$code], 8) : 0.0;
            if ($rate > 0 && $rate <= self::MAX_RATE) {
                $updates[$code] = $rate;
            }
        }

        $updated = $updates === [] ? 0 : $this->currencies->updateRates($updates, CarbonImmutable::now());

        return new RateSyncResult($updated, array_values(array_diff($targets, array_keys($updates))));
    }

    public function sourceName(): string
    {
        return $this->source->name();
    }
}
