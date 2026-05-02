<?php

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    public function create(array $data): Transaction;

    public function update(Transaction $transaction, array $data): Transaction;

    public function findByHash(string $hash, string $chainType): ?Transaction;

    public function getPendingForWallet(Wallet $wallet): Collection;

    public function getByUserWithFilters(User $user, array $filters = []): LengthAwarePaginator;

    public function getById(string $id): ?Transaction;

    public function delete(Transaction $transaction): bool;
}