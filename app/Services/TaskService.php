<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAlertNotification;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskService
{
    public function __construct(private readonly TaskRepositoryInterface $taskRepository)
    {
    }

    public function list(User $user, array $filters): LengthAwarePaginator
    {
        return $this->taskRepository->paginateForUser($user, $filters);
    }

    public function create(User $user, array $data): Task
    {
        $task = $this->taskRepository->createForUser($user, $data);
        $this->sendTaskDueAlerts($user, $task);

        return $task;
    }

    public function update(Task $task, array $data): Task
    {
        $updatedTask = $this->taskRepository->update($task, $data);
        $this->sendStatusAlerts($updatedTask);
        $this->sendTaskDueAlerts($updatedTask->user, $updatedTask);

        return $updatedTask;
    }

    public function delete(Task $task): void
    {
        $this->taskRepository->delete($task);
    }

    public function markCompleted(Task $task): Task
    {
        $completedTask = $this->taskRepository->markCompleted($task);
        $this->sendStatusAlerts($completedTask);

        return $completedTask;
    }

    private function sendTaskDueAlerts(User $user, Task $task): void
    {
        if (! $task->due_date) {
            return;
        }

        if ($task->status !== Task::STATUS_COMPLETED && $task->due_date->isPast()) {
            $user->notify(new TaskAlertNotification($task, 'task_overdue', 'Task is overdue.'));
            return;
        }

        if ($task->status !== Task::STATUS_COMPLETED && $task->due_date->diffInHours(now(), false) <= 24 && $task->due_date->isFuture()) {
            $user->notify(new TaskAlertNotification($task, 'task_due_soon', 'Task is due within 24 hours.'));
        }
    }

    private function sendStatusAlerts(Task $task): void
    {
        if ($task->status === Task::STATUS_COMPLETED) {
            $task->user->notify(new TaskAlertNotification($task, 'task_completed', 'Task has been marked as completed.'));
            return;
        }

        if ($task->due_date && $task->due_date->isPast()) {
            $task->user->notify(new TaskAlertNotification($task, 'task_overdue', 'Task is overdue.'));
        }
    }
}
