<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalance extends Model
{
    use HasUuids;

    protected $fillable = [
        'wallet_id',
        'token_id',
        'balance',
        'balance_usd',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
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
