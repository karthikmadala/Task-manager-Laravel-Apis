<?php

namespace App\Http\Requests\Api\V1\ICO;

use Illuminate\Foundation\Http\FormRequest;

class BuyTokensRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address'       => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'payment_index' => ['required', 'integer', 'min:0'],
            'amount'        => ['required', 'string', 'regex:/^[0-9]+$/'],
            'chain'         => ['required', 'string', 'in:eth,bnb,polygon'],
            'eth_value'     => ['required', 'string', 'regex:/^[0-9]+$/'],
            // Signature fields from createSign
            'signature'           => ['required', 'array'],
            'signature.v'         => ['required', 'integer', 'in:27,28'],
            'signature.r'         => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{64}$/'],
            'signature.s'         => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{64}$/'],
            'signature.nonce'     => ['required', 'integer', 'min:1'],
        ];
    }
}
