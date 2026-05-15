<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'menu_restrictions',
        'role_id',
        'encryption_salt',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'deleted_at'         => 'datetime',
            'menu_restrictions'  => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $user): void {
            if ($user->isForceDeleting()) {
                return;
            }

            $user->wallets()->get()->each->delete();
            $user->transactions()->get()->each->delete();
        });

        static::restoring(function (self $user): void {
            $user->wallets()->onlyTrashed()->get()->each->restore();
            $user->transactions()->onlyTrashed()->get()->each->restore();
        });
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        $r = $this->role()->first();
        return $r && $r->is_super_admin;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasPermissionTo(string $permission): bool
    {
        $cacheKey = "auth:perms:{$this->id}";

        $cached = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () {
            $r = $this->role()->with('permissions')->first();
            if (! $r) {
                return ['is_super_admin' => false, 'permissions' => []];
            }
            return [
                'is_super_admin' => (bool) $r->is_super_admin,
                'permissions'    => $r->permissions->pluck('name')->toArray(),
            ];
        });

        if ($cached['is_super_admin'] || $this->role === 'super_admin') {
            return true;
        }

        return in_array($permission, $cached['permissions'], true);
    }

    public function clearPermissionCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget("auth:perms:{$this->id}");
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }
        return false;
    }
}
