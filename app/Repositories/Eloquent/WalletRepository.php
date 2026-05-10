<?php

namespace App\Repositories\Eloquent;

use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class WalletRepository implements WalletRepositoryInterface
{
    public function allForUser(int $userId): Collection
    {
        return Wallet::where('user_id', $userId)
            ->with(['balances.token'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(string $id): ?Wallet
    {
        return Wallet::find($id);
    }

    public function findByAddress(string $address, ?string $chainType = null): ?Wallet
    {
        return Wallet::query()
            ->when($chainType, fn ($query) => $query->where('chain_type', $chainType))
            ->where('address', strtolower($address))
            ->first();
    }

    public function findByAddressAndUser(string $address, int $userId, ?string $chainType = null): ?Wallet
    {
        return Wallet::query()
            ->when($chainType, fn ($query) => $query->where('chain_type', $chainType))
            ->where('address', strtolower($address))
            ->where('user_id', $userId)
            ->first();
    }

    public function findByAddressAndUserIncludingTrashed(string $address, int $userId, ?string $chainType = null): ?Wallet
    {
        return Wallet::withTrashed()
            ->when($chainType, fn ($query) => $query->where('chain_type', $chainType))
            ->where('address', strtolower($address))
            ->where('user_id', $userId)
            ->first();
    }

    public function findActiveMetaMaskWalletByAddress(string $address): ?Wallet
    {
        return Wallet::query()
            ->where('address', strtolower($address))
            ->where('wallet_type', 'metamask')
            ->where('is_active', true)
            ->first();
    }

    public function create(array $data): Wallet
    {
        return Wallet::create($data);
    }

    public function restore(Wallet $wallet, array $attributes = []): Wallet
    {
        if ($attributes !== []) {
            $wallet->fill($attributes);
        }

        if ($wallet->trashed()) {
            $wallet->restore();
        }

        $wallet->save();

        return $wallet->fresh();
    }

    public function updateNonce(Wallet $wallet, string $nonce): void
    {
        $wallet->forceFill(['metamask_nonce' => $nonce])->save();
    }

    public function delete(Wallet $wallet): void
    {
        $wallet->forceFill(['is_active' => false])->save();
        $wallet->delete();
    }
}
