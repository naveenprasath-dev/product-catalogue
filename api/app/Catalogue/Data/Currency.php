<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

use Carbon\CarbonImmutable;

/** A currency as the rest of the app sees it. `rate` = units of this currency per 1 unit of the base currency. */
final readonly class Currency
{
    public function __construct(
        public string $code,
        public string $name,
        public string $symbol,
        public float $rate,
        public bool $enabled,
        public bool $isDefault,
        public ?CarbonImmutable $rateUpdatedAt,
    ) {}
}
