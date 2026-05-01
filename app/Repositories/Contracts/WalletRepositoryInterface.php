<?php

namespace App\Repositories\Contracts;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;

interface WalletRepositoryInterface
{
    public function allForUser(int $userId): Collection;

    public function findById(string $id): ?Wallet;

    public function findByAddress(string $address, ?string $chainType = null): ?Wallet;

    public function findByAddressAndUser(string $address, int $userId, ?string $chainType = null): ?Wallet;

    public function findActiveMetaMaskWalletByAddress(string $address): ?Wallet;

    public function create(array $data): Wallet;

    public function updateNonce(Wallet $wallet, string $nonce): void;

    public function delete(Wallet $wallet): void;
}
