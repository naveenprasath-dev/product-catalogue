<?php

declare(strict_types=1);

namespace App\Catalogue\Contracts;

use App\Catalogue\Data\Product;
use App\Catalogue\Exceptions\CatalogueUnavailable;

/** Where products come from upstream. Used only by the import, never during an API request. */
interface ProductSource
{
    /**
     * @return iterable<Product>
     *
     * @throws CatalogueUnavailable
     */
    public function fetchProducts(): iterable;

    /** Short label for logs and the import summary, e.g. "fakestore" or "snapshot". */
    public function origin(): string;
}
