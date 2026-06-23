<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Request successful',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    public static function created(
        mixed $data = null,
        string $message = 'Resource created successfully',
        array $meta = []
    ): JsonResponse {
        return self::success($data, $message, 201, $meta);
    }

    public static function updated(
        mixed $data = null,
        string $message = 'Resource updated successfully',
        array $meta = []
    ): JsonResponse {
        return self::success($data, $message, 200, $meta);
    }

    public static function deleted(
        string $message = 'Resource deleted successfully'
    ): JsonResponse {
        return self::success(null, $message);
    }

    public static function error(
        string $message = 'Request failed',
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    public static function validationError(
        mixed $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error($message, 422, $errors);
    }

    public static function notFound(
        string $message = 'Resource not found'
    ): JsonResponse {
        return self::error($message, 404);
    }

    public static function unauthorized(
        string $message = 'Unauthenticated'
    ): JsonResponse {
        return self::error($message, 401);
    }

    public static function forbidden(
        string $message = 'You are not allowed to perform this action'
    ): JsonResponse {
        return self::error($message, 403);
    }

    public static function serverError(
        string $message = 'Something went wrong'
    ): JsonResponse {
        return self::error($message, 500);
    }
}
