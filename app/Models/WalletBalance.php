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

    protected static function booted(): void
    {
        static::creating(function (self $walletBalance): void {
            if ($walletBalance->chain_type) {
                return;
            }

            $wallet = $walletBalance->relationLoaded('wallet')
                ? $walletBalance->wallet
                : ($walletBalance->wallet_id ? Wallet::query()->find($walletBalance->wallet_id) : null);

            if ($wallet) {
                $walletBalance->chain_type = $wallet->chain_type;
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class)->withTrashed();
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class)->withTrashed();
    }
}
