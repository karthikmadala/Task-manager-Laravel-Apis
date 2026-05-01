<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $user->id === $wallet->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Wallet $wallet): bool
    {
        return $user->id === $wallet->user_id;
    }
}
