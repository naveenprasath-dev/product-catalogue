<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Catalogue\Data\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A currency as the admin panel sees it.
 *
 * @property Currency $resource
 */
final class CurrencyResource extends JsonResource
{
    public function __construct(Currency $resource, private readonly string $baseCode = 'USD')
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'code' => $c->code,
            'name' => $c->name,
            'symbol' => $c->symbol,
            'rate' => $c->rate,
            'enabled' => $c->enabled,
            'is_default' => $c->isDefault,
            'is_base' => $c->code === $this->baseCode,
            'rate_updated_at' => $c->rateUpdatedAt?->format(DATE_ATOM),
        ];
    }
}
