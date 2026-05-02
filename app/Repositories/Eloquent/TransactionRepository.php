<?php

namespace App\Repositories\Eloquent;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update($data);
        return $transaction->fresh();
    }

    public function findByHash(string $hash, string $chainType): ?Transaction
    {
        return Transaction::where('tx_hash', $hash)
            ->where('chain_type', $chainType)
            ->first();
    }

    public function getPendingForWallet(Wallet $wallet): Collection
    {
        return Transaction::where('wallet_id', $wallet->id)
            ->where('status', 'pending')
            ->orWhere('status', 'submitted')
            ->get();
    }

    public function getByUserWithFilters(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::where('user_id', $user->id);

        if (isset($filters['chain_type'])) {
            $query->where('chain_type', $filters['chain_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['wallet_id'])) {
            $query->where('wallet_id', $filters['wallet_id']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $query->with(['wallet', 'token', 'user'])
            ->orderBy('created_at', 'desc');

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    public function getById(string $id): ?Transaction
    {
        return Transaction::with(['wallet', 'token', 'user'])->find($id);
    }

    public function delete(Transaction $transaction): bool
    {
        return $transaction->delete();
    }
}