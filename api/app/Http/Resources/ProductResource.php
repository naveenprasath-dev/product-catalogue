<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Catalogue\Currency\CurrencyConverter;
use App\Catalogue\Currency\SelectedCurrency;
use App\Catalogue\Data\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Our public product shape (PLAN.md "API contract"). Never exposes upstream fields or table columns
 * such as source/external_id. price and rating.rate are JSON numbers. `price` is converted from the
 * stored USD price into the request's currency (`?currency=`, see ProductQueryRequest), named in `currency`.
 *
 * @property Product $resource
 */
final class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $p = $this->resource;
        $currency = app(SelectedCurrency::class)->current();

        return [
            'id' => $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'price' => app(CurrencyConverter::class)->fromBase($p->price, $currency),
            'currency' => $currency->code,
            'image_url' => $p->imageUrl,
            'category' => [
                'slug' => $p->categorySlug,
                'name' => $p->categoryName,
            ],
            'rating' => [
                'rate' => $p->ratingRate,
                'count' => $p->ratingCount,
            ],
        ];
    }
}
