<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Catalogue\CatalogueService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchProductsRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function featured(): AnonymousResourceCollection
    {
        return ProductResource::collection($this->catalogue->featured());
    }

    public function index(SearchProductsRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->catalogue->search($request->term()));
    }

    public function show(int $id): ProductResource
    {
        $product = $this->catalogue->product($id) ?? abort(404);

        return new ProductResource($product);
    }
}
