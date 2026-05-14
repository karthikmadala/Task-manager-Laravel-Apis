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
        'encrypted_private_key',
        'private_key_salt',
        'wallet_origin',
        'is_active',
        'last_synced_block',
    ];

    protected $hidden = ['metamask_nonce', 'encrypted_private_key', 'private_key_salt'];

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

    public function scopeInternal($query)
    {
        return $query->where('wallet_origin', 'internal');
    }

    public function hasPrivateKey(): bool
    {
        return ! empty($this->encrypted_private_key);
    }

    public function decryptPrivateKey(string $password): ?string
    {
        if (! $this->hasPrivateKey()) {
            return null;
        }

        $key = $this->getMasterKey($password);
        if (! $key) {
            return null;
        }

        try {
            $decrypted = sodium_crypto_secretbox_open(
                base64_decode($this->encrypted_private_key),
                base64_decode($this->private_key_salt),
                $key,
            );

            return $decrypted !== false ? $decrypted : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function setEncryptedPrivateKey(string $privateKey, string $userPassword): void
    {
        // Ensure user has an encryption_salt (16 bytes for sodium_crypto_pwhash)
        if (! $this->user?->encryption_salt) {
            $this->user->update([
                'encryption_salt' => base64_encode(random_bytes(16)),
            ]);
            $this->user->refresh();
        }

        $salt = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key  = $this->getMasterKey($userPassword);
        if (! $key) {
            throw new \RuntimeException('Failed to derive encryption key.');
        }

        $encrypted = sodium_crypto_secretbox($privateKey, $salt, $key);

        $this->encrypted_private_key = base64_encode($encrypted);
        $this->private_key_salt      = base64_encode($salt);
        $this->wallet_origin        = 'internal';
    }

    private function getMasterKey(string $password): string|false
    {
        $pepper = config('app.key');
        $salt   = $this->user?->encryption_salt;
        if (! $pepper || ! $salt) {
            return false;
        }

        // Ensure salt is exactly 16 bytes (re-generate if invalid)
        $decoded = base64_decode($salt, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_PWHASH_SALTBYTES) {
            $this->user->update([
                'encryption_salt' => base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)),
            ]);
            $this->user->refresh();
            $decoded = base64_decode($this->user->encryption_salt, true);
        }

        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password . $pepper,
            $decoded,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
        );
    }
}
