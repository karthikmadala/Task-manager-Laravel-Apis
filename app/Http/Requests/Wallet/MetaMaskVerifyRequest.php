<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class MetaMaskVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address'   => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'signature' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{130}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.regex'   => 'Must be a valid Ethereum address.',
            'signature.regex' => 'Must be a valid 65-byte hex signature (0x + 130 hex chars).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'address' => is_string($this->address) ? strtolower(trim($this->address)) : $this->address,
            'signature' => is_string($this->signature) ? strtolower(trim($this->signature)) : $this->signature,
        ]);
    }
}
