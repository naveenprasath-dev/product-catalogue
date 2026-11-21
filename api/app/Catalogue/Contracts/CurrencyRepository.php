<?php

declare(strict_types=1);

namespace App\Catalogue\Contracts;

use App\Catalogue\Data\Currency;
use App\Catalogue\Data\CurrencyInput;
use Carbon\CarbonImmutable;

/** Stored currencies. Lists are ordered default first, then by code. */
interface CurrencyRepository
{
    /** @return list<Currency> */
    public function all(): array;

    /** @return list<Currency> */
    public function enabled(): array;

    public function find(string $code): ?Currency;

    /** The one default currency (the migration guarantees it exists). */
    public function default(): Currency;

    /** The latest rate change (live sync or manual edit), or null while every rate is a seeded starting value. */
    public function ratesUpdatedAt(): ?CarbonImmutable;

    public function create(CurrencyInput $input, CarbonImmutable $now): Currency;

    /** Updates name/symbol/rate/enabled; `rate_updated_at` moves only when the rate changed. */
    public function update(string $code, CurrencyInput $input, CarbonImmutable $now): void;

    /** Makes `$code` the only default, in one transaction. */
    public function makeDefault(string $code): void;

    public function delete(string $code): void;

    /**
     * Sets the given rates in one transaction.
     *
     * @param  array<string, float>  $rates  code => rate
     * @return int rows updated
     */
    public function updateRates(array $rates, CarbonImmutable $now): int;
}
