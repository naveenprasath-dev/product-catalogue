<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalogue\Exceptions\CatalogueUnavailable;
use App\Catalogue\Sources\FakeStoreProductMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FakeStoreProductMapperTest extends TestCase
{
    /** @return array<string, mixed> */
    private function item(array $overrides = []): array
    {
        return array_replace([
            'id' => 1,
            'title' => 'Fjallraven - Foldsack No. 1 Backpack',
            'price' => 109.95,
            'description' => 'Your perfect pack.',
            'category' => "men's clothing",
            'image' => 'https://fakestoreapi.com/img/1.png',
            'rating' => ['rate' => 3.9, 'count' => 120],
        ], $overrides);
    }

    public function test_maps_a_fake_store_item_to_a_product(): void
    {
        $product = (new FakeStoreProductMapper)->map($this->item());

        $this->assertNull($product->id);
        $this->assertSame('fakestore', $product->source);
        $this->assertSame('1', $product->externalId);
        $this->assertSame('Fjallraven - Foldsack No. 1 Backpack', $product->title);
        $this->assertSame(109.95, $product->price);
        $this->assertSame('https://fakestoreapi.com/img/1.png', $product->imageUrl);
        $this->assertSame('mens-clothing', $product->categorySlug);
        $this->assertSame("men's clothing", $product->categoryName);
        $this->assertSame(3.9, $product->ratingRate);
        $this->assertSame(120, $product->ratingCount);
    }

    public function test_accepts_numeric_strings_for_price_and_rating(): void
    {
        $product = (new FakeStoreProductMapper)->map($this->item(['price' => '15.5', 'rating' => ['rate' => '4', 'count' => '7']]));

        $this->assertSame(15.5, $product->price);
        $this->assertSame(4.0, $product->ratingRate);
        $this->assertSame(7, $product->ratingCount);
    }

    public function test_maps_the_whole_snapshot(): void
    {
        $payload = json_decode((string) file_get_contents(__DIR__.'/../../database/data/fakestore-products.json'), true);

        $products = (new FakeStoreProductMapper)->mapAll($payload);

        $this->assertCount(20, $products);
    }

    /** @return iterable<string, array{mixed}> */
    public static function malformedItems(): iterable
    {
        yield 'not an object' => ['nope'];
        yield 'missing title' => [['id' => 1, 'price' => 1, 'description' => '', 'category' => 'a', 'image' => '', 'rating' => ['rate' => 1, 'count' => 1]]];
        yield 'price not numeric' => [['id' => 1, 'title' => 't', 'price' => 'free', 'description' => '', 'category' => 'a', 'image' => '', 'rating' => ['rate' => 1, 'count' => 1]]];
        yield 'missing rating' => [['id' => 1, 'title' => 't', 'price' => 1, 'description' => '', 'category' => 'a', 'image' => '']];
        yield 'empty category' => [['id' => 1, 'title' => 't', 'price' => 1, 'description' => '', 'category' => ' ', 'image' => '', 'rating' => ['rate' => 1, 'count' => 1]]];
    }

    #[DataProvider('malformedItems')]
    public function test_malformed_item_throws_catalogue_unavailable(mixed $item): void
    {
        $this->expectException(CatalogueUnavailable::class);

        (new FakeStoreProductMapper)->map($item);
    }

    public function test_payload_that_is_not_a_list_or_is_empty_throws(): void
    {
        foreach ([['message' => 'error'], [], 'string'] as $payload) {
            try {
                (new FakeStoreProductMapper)->mapAll($payload);
                $this->fail('Expected CatalogueUnavailable for '.json_encode($payload));
            } catch (CatalogueUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
