<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

class DashboardService
{
    public function getStats(User $user): array
    {
        $tasksQuery = Task::query()->where('user_id', $user->id);

        $total = (clone $tasksQuery)->count();
        $completed = (clone $tasksQuery)->where('status', Task::STATUS_COMPLETED)->count();
        $pending = (clone $tasksQuery)->where('status', Task::STATUS_PENDING)->count();
        $overdue = (clone $tasksQuery)
            ->where('status', '!=', Task::STATUS_COMPLETED)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        $latestActivities = $user->notifications()->latest()->limit(10)->get()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->data['alert_type'] ?? 'activity',
                'message' => $notification->data['message'] ?? 'Activity generated.',
                'created_at' => $notification->created_at?->toISOString(),
            ];
        })->values();

        return [
            'task_counts' => [
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'overdue' => $overdue,
            ],
            'user_statistics' => [
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'open_tasks' => $total - $completed,
            ],
            'latest_activities' => $latestActivities,
        ];
    }
}
