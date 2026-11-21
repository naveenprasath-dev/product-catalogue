<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

/** A category page: its top rated products and the rest. */
final readonly class CategoryListing
{
    /**
     * @param  list<Product>  $topRated
     * @param  list<Product>  $others
     */
    public function __construct(
        public Category $category,
        public array $topRated,
        public array $others,
    ) {}
}
