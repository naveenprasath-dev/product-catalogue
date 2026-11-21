<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Catalogue\Currency\CurrencyService;
use App\Catalogue\Currency\SelectedCurrency;
use App\Catalogue\Data\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Every product endpoint: optional `?currency=EUR` (case-insensitive). Absent → the default currency;
 * unknown or disabled → 422 on `currency`. On success the currency is handed to SelectedCurrency,
 * which ProductResource uses to convert prices.
 */
class ProductQueryRequest extends FormRequest
{
    private ?Currency $currency = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'currency.string' => 'The currency must be a 3-letter currency code.',
            'currency.regex' => 'The currency must be a 3-letter currency code.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('currency')) {
                    return;
                }
                $code = $this->input('currency');
                $this->currency = $this->container->make(CurrencyService::class)
                    ->resolveForShop(is_string($code) ? $code : null);
                if ($this->currency === null) {
                    $validator->errors()->add('currency', 'The selected currency is not available.');
                }
            },
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->currency !== null) {
            $this->container->make(SelectedCurrency::class)->use($this->currency);
        }
    }
}
