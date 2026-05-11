<?php

namespace App\Models;

use App\Enums\ChainType;
use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'chain_type',
        'wallet_type',
        'address',
        'label',
        'metamask_nonce',
        'is_active',
        'last_synced_block',
    ];

    protected $hidden = ['metamask_nonce'];

    protected function casts(): array
    {
        return [
            'chain_type'  => ChainType::class,
            'wallet_type' => WalletType::class,
            'is_active'   => 'boolean',
            'deleted_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $wallet): void {
            if ($wallet->isForceDeleting()) {
                return;
            }

            if ($wallet->is_active) {
                $wallet->forceFill(['is_active' => false])->saveQuietly();
            }

            $wallet->transactions()->get()->each->delete();
        });

        static::restoring(function (self $wallet): void {
            $wallet->transactions()->onlyTrashed()->get()->each->restore();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
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
