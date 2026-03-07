<?php

namespace App\Traits;

trait ApiResponse
{
    protected function successResponse(string $message, mixed $data = null, int $status = 200)
    {
        return api_response(true, $message, $data, null, $status);
    }

    protected function errorResponse(string $message, mixed $errors = null, int $status = 400)
    {
        return api_response(false, $message, null, $errors, $status);
    }
}
