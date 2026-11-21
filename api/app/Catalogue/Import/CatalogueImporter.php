<?php

declare(strict_types=1);

namespace App\Catalogue\Import;

use App\Catalogue\Contracts\ProductSource;
use App\Catalogue\Data\ImportResult;
use App\Catalogue\Data\Product;
use App\Catalogue\Exceptions\CatalogueUnavailable;
use App\Models\Category;
use App\Models\Product as ProductModel;
use Illuminate\Database\ConnectionInterface;

/**
 * Copies products from a ProductSource into our database in one transaction.
 * Idempotent: categories are upserted by slug, products by (source, external_id).
 * Products missing upstream are never deleted (enquiries reference them).
 */
final readonly class CatalogueImporter
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /** @throws CatalogueUnavailable */
    public function import(ProductSource $source): ImportResult
    {
        // Fetch everything before touching the database, so a source failure writes nothing.
        $products = [];
        foreach ($source->fetchProducts() as $product) {
            // Key by (source, external_id): a duplicated upstream item must not break the upsert.
            $products[$product->source.'|'.$product->externalId] = $product;
        }

        if ($products === []) {
            throw CatalogueUnavailable::because("{$source->origin()} returned no products.");
        }

        $categories = [];
        foreach ($products as $product) {
            $categories[$product->categorySlug] = $product->categoryName;
        }

        $this->db->transaction(function () use ($products, $categories): void {
            $now = now();

            Category::query()->upsert(
                array_map(
                    fn (string $slug, string $name): array => ['slug' => $slug, 'name' => $name, 'created_at' => $now, 'updated_at' => $now],
                    array_keys($categories),
                    array_values($categories),
                ),
                ['slug'],
                ['name', 'updated_at'],
            );

            /** @var array<string, int> $categoryIds */
            $categoryIds = Category::query()
                ->whereIn('slug', array_keys($categories))
                ->pluck('id', 'slug')
                ->all();

            ProductModel::query()->upsert(
                array_map(fn (Product $p): array => [
                    'source' => $p->source,
                    'external_id' => $p->externalId,
                    'category_id' => $categoryIds[$p->categorySlug],
                    'title' => $p->title,
                    'description' => $p->description,
                    'price' => $p->price,
                    'image_url' => $p->imageUrl,
                    'rating_rate' => $p->ratingRate,
                    'rating_count' => $p->ratingCount,
                    'imported_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], array_values($products)),
                ['source', 'external_id'],
                ['category_id', 'title', 'description', 'price', 'image_url', 'rating_rate', 'rating_count', 'imported_at', 'updated_at'],
            );
        });

        return new ImportResult(
            products: count($products),
            categories: count($categories),
            origin: $source->origin(),
        );
    }
}
