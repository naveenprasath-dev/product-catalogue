<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Catalogue\Data\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Category $resource */
final class CategoryResource extends JsonResource
{
    /** @return array{slug: string, name: string} */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
        ];
    }
}
