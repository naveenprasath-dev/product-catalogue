<?php

declare(strict_types=1);

namespace App\Catalogue\Currency;

use App\Catalogue\Contracts\CurrencyRepository;
use App\Catalogue\Data\Currency;
use App\Catalogue\Data\CurrencyInput;
use App\Catalogue\Data\RateSyncResult;
use App\Catalogue\Exceptions\CurrencyChangeRejected;
use App\Catalogue\Exceptions\CurrencyNotFound;
use App\Catalogue\Exceptions\RatesUnavailable;
use Carbon\CarbonImmutable;

/**
 * Currency use cases for the API and the admin panel, and the home of the currency rules:
 * the base currency (USD) keeps rate 1 and can't be disabled or deleted; exactly one default,
 * which must be enabled. Never calls the rate source during a shop request.
 */
final readonly class CurrencyService
{
    public function __construct(
        private CurrencyRepository $currencies,
        private RateSyncer $syncer,
        private string $baseCode,
    ) {}

    public function baseCode(): string
    {
        return $this->baseCode;
    }

    /** @return list<Currency> default first, then by code */
    public function enabled(): array
    {
        return $this->currencies->enabled();
    }

    /** @return list<Currency> default first, then by code */
    public function all(): array
    {
        return $this->currencies->all();
    }

    public function find(string $code): ?Currency
    {
        return $this->currencies->find(strtoupper($code));
    }

    public function ratesUpdatedAt(): ?CarbonImmutable
    {
        return $this->currencies->ratesUpdatedAt();
    }

    /** The currency a shop request asked for: null → the default; unknown or disabled → null. */
    public function resolveForShop(?string $code): ?Currency
    {
        if ($code === null) {
            return $this->currencies->default();
        }

        $currency = $this->currencies->find(strtoupper($code));

        return $currency !== null && $currency->enabled ? $currency : null;
    }

    /** @throws CurrencyChangeRejected */
    public function create(CurrencyInput $input): Currency
    {
        if ($this->currencies->find($input->code) !== null) {
            throw new CurrencyChangeRejected("{$input->code} already exists.", 'code');
        }

        return $this->currencies->create($input, CarbonImmutable::now());
    }

    /**
     * @throws CurrencyNotFound
     * @throws CurrencyChangeRejected
     */
    public function update(string $code, CurrencyInput $input): void
    {
        $current = $this->existing($code);

        if ($current->code === $this->baseCode && abs($input->rate - 1.0) > 0.0) {
            throw new CurrencyChangeRejected("The base currency ({$this->baseCode}) always has rate 1.", 'rate');
        }
        if (! $input->enabled && $current->code === $this->baseCode) {
            throw new CurrencyChangeRejected("The base currency ({$this->baseCode}) can't be disabled.", 'enabled');
        }
        if (! $input->enabled && $current->isDefault) {
            throw new CurrencyChangeRejected('The default currency must stay enabled. Make another currency the default first.', 'enabled');
        }

        $this->currencies->update($current->code, $input, CarbonImmutable::now());
    }

    /**
     * @throws CurrencyNotFound
     * @throws CurrencyChangeRejected
     */
    public function makeDefault(string $code): void
    {
        $currency = $this->existing($code);

        if (! $currency->enabled) {
            throw new CurrencyChangeRejected("Enable {$currency->code} before making it the default.");
        }

        $this->currencies->makeDefault($currency->code);
    }

    /**
     * @throws CurrencyNotFound
     * @throws CurrencyChangeRejected
     */
    public function delete(string $code): void
    {
        $currency = $this->existing($code);

        if ($currency->code === $this->baseCode) {
            throw new CurrencyChangeRejected("The base currency ({$this->baseCode}) can't be deleted.");
        }
        if ($currency->isDefault) {
            throw new CurrencyChangeRejected("{$currency->code} is the default currency. Make another currency the default first.");
        }

        $this->currencies->delete($currency->code);
    }

    /** @throws RatesUnavailable nothing was changed */
    public function syncRates(): RateSyncResult
    {
        return $this->syncer->sync();
    }

    public function rateSourceName(): string
    {
        return $this->syncer->sourceName();
    }

    private function existing(string $code): Currency
    {
        return $this->currencies->find(strtoupper($code)) ?? throw new CurrencyNotFound(strtoupper($code));
    }
}
