<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Catalogue\Data\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Our public product shape (PLAN.md "API contract"). Never exposes upstream fields or table columns
 * such as source/external_id. price and rating.rate are JSON numbers.
 *
 * @property Product $resource
 */
final class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'id' => $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'price' => $p->price,
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
