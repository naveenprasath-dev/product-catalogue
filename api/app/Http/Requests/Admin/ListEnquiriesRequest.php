<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EnquiryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListEnquiriesRequest extends FormRequest
{
    /** Access is enforced by the route group (auth + can:admin). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(EnquiryStatus::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function status(): ?EnquiryStatus
    {
        return EnquiryStatus::tryFrom((string) $this->validated('status'));
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
