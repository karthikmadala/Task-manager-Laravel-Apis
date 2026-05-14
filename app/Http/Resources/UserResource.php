<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'role'               => $this->role,
            'menu_restrictions'  => $this->menu_restrictions ?? [],
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
        ];

        if (isset($this->wallets_count)) {
            $data['wallet_count'] = $this->wallets_count;
        }

        return $data;
    }
}
