<?php

namespace App\Models;

use App\Enums\ChainType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Token extends Model
{
    use HasUuids;

    protected $fillable = [
        'symbol',
        'name',
        'chain_type',
        'coingecko_id',
        'contract_address',
        'decimals',
        'current_price_usd',
        'price_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'        => ChainType::class,
            'decimals'          => 'integer',
            'current_price_usd' => 'float',
            'price_updated_at'  => 'datetime',
        ];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(WalletBalance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isNative(): bool
    {
        return $this->contract_address === null;
    }
}
