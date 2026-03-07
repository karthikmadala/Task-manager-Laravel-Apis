<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAlertNotification;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->chunkById(50, function ($users): void {
            foreach ($users as $user) {
                $tasks = Task::factory()->count(random_int(8, 20))->create([
                    'user_id' => $user->id,
                ]);

                $completedTask = $tasks->firstWhere('status', 'completed');
                $overdueTask = $tasks->first(function (Task $task) {
                    return $task->status !== 'completed' && $task->due_date && $task->due_date->isPast();
                });

                if ($completedTask) {
                    $user->notify(new TaskAlertNotification($completedTask, 'task_completed', 'Task has been marked as completed.'));
                }

                if ($overdueTask) {
                    $user->notify(new TaskAlertNotification($overdueTask, 'task_overdue', 'Task is overdue.'));
                }
            }
        });
    }
}
