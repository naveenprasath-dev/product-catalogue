<?php

declare(strict_types=1);

namespace App\Catalogue\Contracts;

use App\Catalogue\Data\Category;
use App\Catalogue\Data\Product;
use Carbon\CarbonImmutable;

/** Read model for products. Backed by our database; never calls an upstream source. */
interface ProductRepository
{
    public function find(int $id): ?Product;

    /**
     * Products with the given source ids, in the given order; unknown ids are skipped.
     *
     * @param  list<string>  $externalIds
     * @return list<Product>
     */
    public function featured(array $externalIds): array;

    public function findCategory(string $slug): ?Category;

    /**
     * All products of a category, highest rated first.
     *
     * @return list<Product>
     */
    public function byCategory(string $slug): array;

    /** @return list<Category> sorted by name */
    public function categories(): array;

    /**
     * Case-insensitive substring match on title, description and category name.
     *
     * @return list<Product>
     */
    public function search(string $term): array;

    public function countProducts(): int;

    public function countCategories(): int;

    /** Latest imported_at of any product; null when the catalogue is empty. */
    public function lastImportedAt(): ?CarbonImmutable;
}
