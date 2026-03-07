<?php

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TaskRepositoryInterface
{
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator;

    public function createForUser(User $user, array $data): Task;

    public function update(Task $task, array $data): Task;

    public function delete(Task $task): void;

    public function markCompleted(Task $task): Task;
}
