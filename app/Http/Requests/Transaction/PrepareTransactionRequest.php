<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PrepareTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'from_address' => 'required|string',
            'to_address' => 'required|string',
            'chain_type' => 'required|string|in:eth,bnb,polygon',
            'amount' => 'required|string|regex:/^\d+(\.\d+)?$/',
            'token_address' => 'nullable|string',
            'contract_address' => 'nullable|string',
            'method' => 'nullable|string',
            'method_params' => 'nullable|array',
            'gas_params' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'from_address.required' => 'From address is required',
            'to_address.required' => 'To address is required',
            'chain_type.required' => 'Chain type is required',
            'chain_type.in' => 'Chain type must be one of: eth, bnb, polygon',
            'amount.required' => 'Amount is required',
            'amount.regex' => 'Amount must be a valid number',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'data' => null,
            ], 422)
        );
    }
}