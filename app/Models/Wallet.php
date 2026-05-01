<?php

namespace App\Models;

use App\Enums\ChainType;
use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'chain_type',
        'wallet_type',
        'address',
        'label',
        'metamask_nonce',
        'is_active',
    ];

    protected $hidden = ['metamask_nonce'];

    protected function casts(): array
    {
        return [
            'chain_type'  => ChainType::class,
            'wallet_type' => WalletType::class,
            'is_active'   => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(WalletBalance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
