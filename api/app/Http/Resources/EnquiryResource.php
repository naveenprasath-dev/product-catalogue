<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Catalogue\Data\EnquiryResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property EnquiryResult $resource */
final class EnquiryResource extends JsonResource
{
    /** @return array{id: int, status: string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status->value,
        ];
    }
}
