<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalogue\CatalogueService;
use App\Catalogue\Data\Product;
use PHPUnit\Framework\TestCase;

final class CatalogueServiceTest extends TestCase
{
    private function product(int $id, float $rate): Product
    {
        return new Product($id, 'fakestore', (string) $id, "p{$id}", 'd', 1.0, 'https://x.test/i.png', 'c', 'c', $rate, 1);
    }

    /** @param  list<Product>  $products
     *  @return list<int|null> */
    private function ids(array $products): array
    {
        return array_map(fn (Product $p): ?int => $p->id, $products);
    }

    public function test_splits_top_n_by_rating_and_keeps_the_rest(): void
    {
        [$top, $others] = CatalogueService::splitTopRated([
            $this->product(1, 2.0), $this->product(2, 4.5), $this->product(3, 3.0),
            $this->product(4, 4.9), $this->product(5, 1.0),
        ], 3);

        $this->assertSame([4, 2, 3], $this->ids($top));
        $this->assertSame([1, 5], $this->ids($others));
    }

    public function test_ties_are_broken_by_id(): void
    {
        [$top, $others] = CatalogueService::splitTopRated([
            $this->product(9, 4.0), $this->product(3, 4.0), $this->product(5, 4.0), $this->product(1, 4.0),
        ], 3);

        $this->assertSame([1, 3, 5], $this->ids($top));
        $this->assertSame([9], $this->ids($others));
    }

    public function test_fewer_products_than_n_gives_empty_others(): void
    {
        [$top, $others] = CatalogueService::splitTopRated([$this->product(1, 1.0), $this->product(2, 2.0)], 3);

        $this->assertSame([2, 1], $this->ids($top));
        $this->assertSame([], $others);
    }

    public function test_empty_category(): void
    {
        $this->assertSame([[], []], CatalogueService::splitTopRated([], 3));
    }
}
