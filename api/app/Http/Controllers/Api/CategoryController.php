<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Catalogue\CatalogueService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductQueryRequest;
use App\Http\Resources\CategoryListingResource;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->catalogue->categories());
    }

    public function products(ProductQueryRequest $request, string $slug): CategoryListingResource
    {
        $listing = $this->catalogue->categoryListing($slug) ?? abort(404);

        return new CategoryListingResource($listing);
    }
}
