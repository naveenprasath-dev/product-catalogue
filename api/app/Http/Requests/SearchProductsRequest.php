<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // Empty/whitespace-only terms become null (TrimStrings + ConvertEmptyStringsToNull) → 422.
        return [
            'search' => ['required', 'string', 'max:100'],
        ];
    }

    public function term(): string
    {
        return $this->string('search')->toString();
    }
}
