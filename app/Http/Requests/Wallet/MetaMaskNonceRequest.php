<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class MetaMaskNonceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.regex' => 'Must be a valid Ethereum address (0x followed by 40 hex characters).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'address' => is_string($this->address) ? strtolower(trim($this->address)) : $this->address,
        ]);
    }
}
