<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Catalogue\Data\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A currency the shop can show prices in (PLAN.md "API contract"). `rate` is a JSON number: units per 1 USD.
 *
 * @property Currency $resource
 */
final class CurrencyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'code' => $c->code,
            'name' => $c->name,
            'symbol' => $c->symbol,
            'rate' => $c->rate,
            'is_default' => $c->isDefault,
        ];
    }
}
