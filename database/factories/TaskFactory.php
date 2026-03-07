<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $dueDate = fake()->dateTimeBetween('-7 days', '+20 days');
        $status = fake()->randomElement(['pending', 'in_progress', 'completed']);

        return [
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'due_date' => $dueDate,
            'completed_at' => $status === 'completed' ? fake()->dateTimeBetween('-5 days', 'now') : null,
            'user_id' => User::factory(),
        ];
    }
}
