<?php

declare(strict_types=1);

namespace App\Catalogue\Currency;

use App\Catalogue\Contracts\CurrencyRepository;
use App\Catalogue\Data\Currency;

/**
 * The currency the current request shows prices in (a scoped binding: one per request).
 * Set by ProductQueryRequest from `?currency=`; read by ProductResource. Unset → the default currency.
 */
final class SelectedCurrency
{
    private ?Currency $currency = null;

    public function __construct(private readonly CurrencyRepository $currencies) {}

    public function use(Currency $currency): void
    {
        $this->currency = $currency;
    }

    public function current(): Currency
    {
        return $this->currency ??= $this->currencies->default();
    }
}
