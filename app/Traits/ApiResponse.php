<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a successful JSON response
     */
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'error_code' => null,
        ], $code);
    }

    /**
     * Return an error JSON response
     */
    protected function error(string $message = 'Error', int $code = 400, ?string $errorCode = null, $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'error_code' => $errorCode ?? $this->getErrorCodeFromStatus($code),
        ], $code);
    }

    /**
     * Return a validation error response
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'error_code' => 'VALIDATION_ERROR',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Return a not found error response
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404, 'RESOURCE_NOT_FOUND');
    }

    /**
     * Return an unauthorized error response
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401, 'UNAUTHORIZED');
    }

    /**
     * Return a forbidden error response
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403, 'FORBIDDEN');
    }

    /**
     * Return a server error response
     */
    protected function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return $this->error($message, 500, 'SERVER_ERROR');
    }

    /**
     * Map HTTP status codes to error codes
     */
    private function getErrorCodeFromStatus(int $code): string
    {
        return match ($code) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'RESOURCE_NOT_FOUND',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            500 => 'SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'UNKNOWN_ERROR'
        };
    }
}
