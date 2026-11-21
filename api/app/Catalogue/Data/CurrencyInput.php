<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

/** Admin input for creating or editing a currency (already validated by the FormRequest). */
final readonly class CurrencyInput
{
    public function __construct(
        public string $code,
        public string $name,
        public string $symbol,
        public float $rate,
        public bool $enabled,
    ) {}
}
