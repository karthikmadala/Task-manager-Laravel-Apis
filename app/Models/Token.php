<?php

namespace App\Models;

use App\Enums\ChainType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Token extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'symbol',
        'name',
        'chain_type',
        'coingecko_id',
        'contract_address',
        'decimals',
        'chain_id',
        'current_price_usd',
        'price_updated_at',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'        => ChainType::class,
            'decimals'          => 'integer',
            'chain_id'          => 'integer',
            'current_price_usd' => 'float',
            'price_updated_at'  => 'datetime',
            'enabled'           => 'boolean',
            'deleted_at'        => 'datetime',
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
