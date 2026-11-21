<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Catalogue\Currency\CurrencyService;
use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /** Enabled currencies, default first then by code. */
    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection($this->currencies->enabled())->additional(['meta' => [
            'base' => $this->currencies->baseCode(),
            'rates_updated_at' => $this->currencies->ratesUpdatedAt()?->format(DATE_ATOM),
        ]]);
    }
}
