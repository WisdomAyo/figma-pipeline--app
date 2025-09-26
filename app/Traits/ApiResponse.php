<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait for standardized API responses
 *
 * Provides consistent response format across all API endpoints
 */
trait ApiResponse
{
    /**
     * Return a successful response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function successfulResponse($data = null, string $message = 'Success', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success'        => true,
            'message'        => $message,
            'data'           => $data,
            'timestamp'      => now()->format('Y-m-d, H:i:s'),
            'execution_time' => $this->getExecutionTime()
        ], $statusCode);
    }

    /**
     * Return an error response
     *
     * @param string $message
     * @param mixed $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'Error', $errors = null, int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return response()->json([
            'success'        => false,
            'message'        => $message,
            'errors'         => $errors,
            'timestamp'      => now()->format('Y-m-d, H:i:s'),
            'execution_time' => $this->getExecutionTime()
        ], $statusCode);
    }

    /**
     * Calculate execution time
     *
     * @return string
     */
    private function getExecutionTime(): string
    {
        if (defined('LARAVEL_START')) {
            return round((microtime(true) - LARAVEL_START) * 1000, 2) . ' ms';
        }
        return '0 ms';
    }
}
