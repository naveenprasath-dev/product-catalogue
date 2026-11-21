<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

/**
 * A product as the rest of the app sees it, independent of the upstream source or our table columns.
 * `id` is our database id; it is null for products coming from a source before they are imported.
 */
final readonly class Product
{
    public function __construct(
        public ?int $id,
        public string $source,
        public string $externalId,
        public string $title,
        public string $description,
        public float $price,
        public string $imageUrl,
        public string $categorySlug,
        public string $categoryName,
        public float $ratingRate,
        public int $ratingCount,
    ) {}
}
