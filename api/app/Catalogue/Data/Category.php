<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

final readonly class Category
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {}
}
