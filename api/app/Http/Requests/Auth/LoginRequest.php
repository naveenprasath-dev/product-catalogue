<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /** Cookie auth only works on a session, i.e. from the shop origin (Sanctum stateful); others get 403. */
    public function authorize(): bool
    {
        return $this->hasSession();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
