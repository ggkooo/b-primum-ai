<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function success(array $data = [], ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['status' => 'success'];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message, int $status = 400, array $data = []): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }
}
