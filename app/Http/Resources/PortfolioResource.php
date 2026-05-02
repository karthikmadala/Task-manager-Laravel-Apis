<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortfolioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_value_usd' => $this->resource['total_value_usd'],
            'wallet_count'    => $this->resource['wallet_count'],
            'grouped_wallet_count' => $this->resource['grouped_wallet_count'] ?? $this->resource['wallet_count'],
            'wallets'         => $this->resource['wallets'],
            'grouped_wallets' => $this->resource['grouped_wallets'] ?? $this->resource['wallets'],
            'chain_totals'    => $this->resource['chain_totals'] ?? [],
        ];
    }
}
