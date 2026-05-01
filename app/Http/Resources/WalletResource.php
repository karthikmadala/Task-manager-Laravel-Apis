<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'chain_type'  => $this->chain_type->value,
            'chain_label' => $this->chain_type->label(),
            'wallet_type' => $this->wallet_type->value,
            'address'     => $this->address,
            'label'       => $this->label,
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
