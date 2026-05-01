<?php

namespace App\Http\Requests\Wallet;

use App\Enums\ChainType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chain_type' => ['required', new Enum(ChainType::class)],
            'address'    => ['required', 'string', 'max:255'],
            'label'      => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'address' => is_string($this->address) ? strtolower(trim($this->address)) : $this->address,
            'label' => is_string($this->label) ? trim($this->label) : $this->label,
        ]);
    }
}
