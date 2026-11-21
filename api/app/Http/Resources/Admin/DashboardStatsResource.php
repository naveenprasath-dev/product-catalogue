<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Catalogue\Data\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property DashboardStats $resource */
final class DashboardStatsResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $stats = $this->resource;

        return [
            'products' => $stats->products,
            'categories' => $stats->categories,
            'enquiries' => [
                'pending' => $stats->enquiriesPending,
                'sent' => $stats->enquiriesSent,
                'failed' => $stats->enquiriesFailed,
            ],
            'last_import_at' => $stats->lastImportAt?->format(DATE_ATOM),
        ];
    }
}
