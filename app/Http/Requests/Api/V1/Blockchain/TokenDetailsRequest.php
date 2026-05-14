<?php

namespace App\Http\Requests\Api\V1\Blockchain;

use Illuminate\Foundation\Http\FormRequest;

class TokenDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'chain'            => ['required', 'string', 'in:eth,bnb,polygon'],
        ];
    }
}
