<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tx_hash' => $this->tx_hash,
            'from_address' => $this->from_address,
            'to_address' => $this->to_address,
            'amount' => $this->amount,
            'chain_type' => $this->chain_type,
            'status' => $this->status,
            'gas_used' => $this->gas_used,
            'gas_price_gwei' => $this->gas_price_gwei,
            'gas_limit' => $this->gas_limit,
            'max_fee_per_gas' => $this->max_fee_per_gas,
            'max_priority_fee_per_gas' => $this->max_priority_fee_per_gas,
            'fee_usd' => $this->fee_usd,
            'block_number' => $this->block_number,
            'confirmations_count' => $this->confirmations_count,
            'error_message' => $this->error_message,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
            'token' => new TokenResource($this->whenLoaded('token')),
            'contract_address' => $this->contract_address,
            'method_signature' => $this->method_signature,
            'method_params' => $this->method_params,
            'signing_method' => $this->signing_method,
        ];
    }
}