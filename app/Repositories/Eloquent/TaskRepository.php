<?php

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskRepository implements TaskRepositoryInterface
{
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $query = Task::query()->where('user_id', $user->id);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('due_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('due_date', '<=', $filters['to_date']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function createForUser(User $user, array $data): Task
    {
        return $user->tasks()->create($data);
    }

    public function update(Task $task, array $data): Task
    {
        $task->fill($data);

        if (($data['status'] ?? null) === Task::STATUS_COMPLETED && ! $task->completed_at) {
            $task->completed_at = now();
        }

        if (($data['status'] ?? null) !== Task::STATUS_COMPLETED) {
            $task->completed_at = null;
        }

        $task->save();

        return $task->refresh();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function markCompleted(Task $task): Task
    {
        $task->forceFill([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        return $task->refresh();
    }
}
