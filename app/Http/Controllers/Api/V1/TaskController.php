<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\TaskIndexRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService)
    {
    }

    public function index(TaskIndexRequest $request)
    {
        $paginator = $this->taskService->list($request->user(), $request->validated());

        return api_response(true, 'Tasks fetched successfully.', [
            'items' => TaskResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request)
    {
        $task = $this->taskService->create($request->user(), $request->validated());

        return api_response(true, 'Task created successfully.', new TaskResource($task), null, 201);
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return api_response(true, 'Task fetched successfully.', new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);

        $updatedTask = $this->taskService->update($task, $request->validated());

        return api_response(true, 'Task updated successfully.', new TaskResource($updatedTask));
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $this->taskService->delete($task);

        return api_response(true, 'Task deleted successfully.');
    }

    public function markCompleted(Task $task)
    {
        $this->authorize('update', $task);

        $completedTask = $this->taskService->markCompleted($task);

        return api_response(true, 'Task marked as completed.', new TaskResource($completedTask));
    }
}
