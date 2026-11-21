<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Catalogue\Data\CurrencyInput;
use Illuminate\Foundation\Http\FormRequest;

/** Create a currency. Currency rules (base rate fixed, default enabled, …) live in CurrencyService. */
class StoreCurrencyRequest extends FormRequest
{
    /** Access is enforced by the route group (auth + can:admin). */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $this->merge([
            // A checkbox sends "on" or nothing.
            'enabled' => $this->boolean('enabled'),
            'code' => is_string($code) ? strtoupper(trim($code)) : $code,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z]{3}$/', 'unique:currencies,code'],
            ...$this->detailRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'The code must be a 3-letter ISO 4217 code, e.g. EUR.',
            'rate.gt' => 'The rate must be greater than 0.',
        ];
    }

    /** @return array<string, list<string>> */
    protected function detailRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            // Units per 1 USD; decimal(18,8) column.
            'rate' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,8'],
            'enabled' => ['boolean'],
        ];
    }

    public function toInput(?string $code = null): CurrencyInput
    {
        return new CurrencyInput(
            code: $code ?? (string) $this->validated('code'),
            name: (string) $this->validated('name'),
            symbol: (string) $this->validated('symbol'),
            rate: (float) $this->validated('rate'),
            enabled: (bool) $this->validated('enabled'),
        );
    }
}
