<?php

namespace App\Models;

use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasUuids;
    use SoftDeletes;

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
        'signing_method',
        'gas_used',
        'gas_price_gwei',
        'gas_limit',
        'max_fee_per_gas',
        'max_priority_fee_per_gas',
        'fee_usd',
        'block_number',
        'confirmations_count',
        'retry_count',
        'broadcast_attempts',
        'contract_address',
        'method_signature',
        'method_params',
        'error_message',
        'submitted_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'               => ChainType::class,
            'status'                   => TransactionStatus::class,
            'method_params'            => 'array',
            'amount'                   => 'decimal:18',
            'gas_used'                 => 'decimal:8',
            'gas_price_gwei'           => 'decimal:8',
            'gas_limit'                => 'decimal:8',
            'max_fee_per_gas'          => 'decimal:8',
            'max_priority_fee_per_gas' => 'decimal:8',
            'fee_usd'                  => 'decimal:8',
            'submitted_at'             => 'datetime',
            'confirmed_at'             => 'datetime',
            'deleted_at'               => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class)->withTrashed();
    }
}
