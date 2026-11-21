<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Catalogue\Data\CategoryListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property CategoryListing $resource */
final class CategoryListingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'category' => new CategoryResource($this->resource->category),
            'top_rated' => ProductResource::collection($this->resource->topRated),
            'others' => ProductResource::collection($this->resource->others),
        ];
    }
}
