<?php

declare(strict_types=1);

namespace App\Catalogue\Sources;

use App\Catalogue\Data\Product;
use App\Catalogue\Exceptions\CatalogueUnavailable;
use Illuminate\Support\Str;

/**
 * Maps the Fake Store `GET /products` item shape to our Product DTO.
 * Shared by the live and snapshot sources so both produce identical rows (same source + external_id).
 */
final readonly class FakeStoreProductMapper
{
    public const SOURCE = 'fakestore';

    /**
     * @param  mixed  $payload  decoded `GET /products` body
     * @return list<Product>
     *
     * @throws CatalogueUnavailable
     */
    public function mapAll(mixed $payload): array
    {
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw CatalogueUnavailable::because('Fake Store payload is not a list of products.');
        }

        // An empty catalogue is treated as a failure, so the fallback is used instead of wiping the shop.
        if ($payload === []) {
            throw CatalogueUnavailable::because('Fake Store returned no products.');
        }

        return array_map(fn (mixed $item): Product => $this->map($item), $payload);
    }

    /** @throws CatalogueUnavailable */
    public function map(mixed $item): Product
    {
        if (! is_array($item)) {
            throw CatalogueUnavailable::because('Fake Store product is not an object.');
        }

        $rating = $item['rating'] ?? null;

        if (
            ! (is_int($item['id'] ?? null) || is_string($item['id'] ?? null))
            || ! $this->isNonEmptyString($item['title'] ?? null)
            || ! is_string($item['description'] ?? null)
            || ! is_numeric($item['price'] ?? null)
            || ! $this->isNonEmptyString($item['category'] ?? null)
            || ! is_string($item['image'] ?? null)
            || ! is_array($rating)
            || ! is_numeric($rating['rate'] ?? null)
            || ! is_numeric($rating['count'] ?? null)
        ) {
            $id = is_scalar($item['id'] ?? null) ? (string) $item['id'] : '?';

            throw CatalogueUnavailable::because("Fake Store product {$id} is missing or has invalid fields.");
        }

        $categoryName = trim($item['category']);

        return new Product(
            id: null,
            source: self::SOURCE,
            externalId: (string) $item['id'],
            title: trim($item['title']),
            description: $item['description'],
            price: round((float) $item['price'], 2),
            imageUrl: $item['image'],
            categorySlug: Str::slug($categoryName),
            categoryName: $categoryName,
            ratingRate: round((float) $rating['rate'], 2),
            ratingCount: (int) $rating['count'],
        );
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
