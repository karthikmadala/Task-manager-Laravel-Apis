<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcoUsedNonce extends Model
{
    protected $table = 'ico_used_nonces';

    protected $fillable = [
        'nonce',
        'user_address',
        'user_id',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function isConsumed(string $nonce, string $userAddress): bool
    {
        return static::where('nonce', $nonce)
            ->where('user_address', strtolower($userAddress))
            ->where('expires_at', '>', now())
            ->exists();
    }

    public static function consume(string $nonce, string $userAddress, ?int $userId, Carbon $expiresAt): void
    {
        static::create([
            'nonce'        => $nonce,
            'user_address' => strtolower($userAddress),
            'user_id'      => $userId,
            'expires_at'   => $expiresAt,
            'used_at'      => now(),
        ]);
    }

    public static function pruneExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
