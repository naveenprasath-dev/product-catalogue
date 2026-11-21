<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/** Edit a currency; the code (primary key) is taken from the route and can't change. */
final class UpdateCurrencyRequest extends StoreCurrencyRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['enabled' => $this->boolean('enabled')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->detailRules();
    }
}
