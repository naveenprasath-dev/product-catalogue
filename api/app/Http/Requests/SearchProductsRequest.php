<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class SearchProductsRequest extends ProductQueryRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // Empty/whitespace-only terms become null (TrimStrings + ConvertEmptyStringsToNull) → 422.
        return [
            ...parent::rules(),
            'search' => ['required', 'string', 'max:100'],
        ];
    }

    public function term(): string
    {
        return $this->string('search')->toString();
    }
}
