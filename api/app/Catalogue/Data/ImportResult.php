<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

final readonly class ImportResult
{
    public function __construct(
        public int $products,
        public int $categories,
        public string $origin,
    ) {}
}
