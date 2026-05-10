<?php

if (! function_exists('api_response')) {
    function api_response(bool $success, string $message, mixed $data = null, mixed $errors = null, int $status = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $status, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
