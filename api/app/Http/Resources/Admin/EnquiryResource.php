<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Catalogue\Data\StoredEnquiry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The only enquiry fields that reach the admin browser (no raw model columns).
 *
 * @property StoredEnquiry $resource
 */
final class EnquiryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $enquiry = $this->resource;

        return [
            'id' => $enquiry->id,
            'product_id' => $enquiry->productId,
            'product_title' => $enquiry->productTitle,
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'phone' => $enquiry->phone,
            'message' => $enquiry->message,
            'status' => $enquiry->status->value,
            'customer_account' => $enquiry->userId !== null,
            'created_at' => $enquiry->createdAt->format(DATE_ATOM),
            'mailed_at' => $enquiry->mailedAt?->format(DATE_ATOM),
            'resendable' => $enquiry->canBeResent(CarbonImmutable::now()),
        ];
    }
}
