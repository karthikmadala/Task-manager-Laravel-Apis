<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'chain_type'     => $this->chain_type?->value,
            'symbol'         => $this->token->symbol,
            'name'           => $this->token->name,
            'contract'       => $this->token->contract_address,
            'balance'        => $this->balance,
            'price_usd'      => $this->token->current_price_usd,
            'value_usd'      => $this->balance_usd,
            'last_synced_at' => $this->fetched_at?->toISOString(),
        ];
    }
}
