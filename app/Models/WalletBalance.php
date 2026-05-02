<?php

namespace App\Models;

use App\Enums\ChainType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalance extends Model
{
    use HasUuids;

    protected $fillable = [
        'wallet_id',
        'token_id',
        'chain_type',
        'balance',
        'balance_usd',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'  => ChainType::class,
            'balance'     => 'decimal:18',
            'balance_usd' => 'decimal:8',
            'fetched_at'  => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }
}
