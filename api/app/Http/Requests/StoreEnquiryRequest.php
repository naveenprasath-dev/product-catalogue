<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Catalogue\Data\EnquiryData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // bail + bounds: never send a non-integer or out-of-range value to the exists query.
            'product_id' => ['bail', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, 'exists:products,id'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:2000'],
            // Honeypot: hidden in the form, so a human leaves it empty. Not rejected, just ignored (see service).
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): EnquiryData
    {
        return new EnquiryData(
            productId: $this->integer('product_id'),
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            phone: $this->filled('phone') ? $this->string('phone')->toString() : null,
            message: $this->filled('message') ? $this->string('message')->toString() : null,
            honeypotFilled: $this->filled('website'),
            // From the session only (never the body): a logged-in shop customer, or null for a guest.
            userId: $this->user() === null ? null : (int) $this->user()->getAuthIdentifier(),
        );
    }
}
