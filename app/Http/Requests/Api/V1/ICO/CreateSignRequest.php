<?php

namespace App\Http\Requests\Api\V1\ICO;

use Illuminate\Foundation\Http\FormRequest;

class CreateSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'index'        => ['required', 'integer', 'min:0'],
            'address'      => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'caller'       => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'crypto_value' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'chain'        => ['required', 'string', 'in:eth,bnb,polygon'],
        ];
    }
}
