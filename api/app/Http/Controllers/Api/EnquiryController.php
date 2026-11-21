<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Catalogue\CatalogueService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Http\Resources\EnquiryResource;
use Illuminate\Http\JsonResponse;

final class EnquiryController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    /** Email failure surfaces as EnquiryNotSent, rendered as 502 in bootstrap/app.php. */
    public function store(StoreEnquiryRequest $request): JsonResponse
    {
        return (new EnquiryResource($this->catalogue->submitEnquiry($request->toData())))
            ->response()
            ->setStatusCode(201);
    }
}
