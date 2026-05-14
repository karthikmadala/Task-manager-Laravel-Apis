<?php

namespace App\Http\Requests\Api\V1\Staking;

use Illuminate\Foundation\Http\FormRequest;

class StakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'amount_wei'   => ['required', 'string', 'regex:/^[0-9]+$/'],
            'level'        => ['required', 'integer', 'min:0', 'max:10'],
            'chain'        => ['required', 'string', 'in:eth,bnb,polygon'],
        ];
    }
}
