<?php

namespace App\Models;

use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'token_id',
        'tx_hash',
        'from_address',
        'to_address',
        'amount',
        'chain_type',
        'status',
        'gas_used',
        'gas_price_gwei',
        'fee_usd',
        'block_number',
        'error_message',
        'submitted_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'     => ChainType::class,
            'status'         => TransactionStatus::class,
            'amount'         => 'decimal:18',
            'gas_used'       => 'decimal:8',
            'gas_price_gwei' => 'decimal:8',
            'fee_usd'        => 'decimal:8',
            'submitted_at'   => 'datetime',
            'confirmed_at'   => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }
}
