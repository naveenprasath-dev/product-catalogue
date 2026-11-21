<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

use Carbon\CarbonImmutable;

/** Counts for the admin dashboard. */
final readonly class DashboardStats
{
    public function __construct(
        public int $products,
        public int $categories,
        public int $enquiriesPending,
        public int $enquiriesSent,
        public int $enquiriesFailed,
        /** When the catalogue was last imported (latest products.imported_at); null before the first import. */
        public ?CarbonImmutable $lastImportAt,
    ) {}

    public function enquiriesTotal(): int
    {
        return $this->enquiriesPending + $this->enquiriesSent + $this->enquiriesFailed;
    }
}
