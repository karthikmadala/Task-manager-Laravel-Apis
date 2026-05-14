<?php

namespace App\Http\Requests\Api\V1\ICO;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the self-service ICO buy endpoint.
 * Unlike BuyTokensRequest, signature is NOT required here — it is auto-generated server-side.
 */
class SelfServiceBuyRequest extends FormRequest
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
        ];
    }
}
