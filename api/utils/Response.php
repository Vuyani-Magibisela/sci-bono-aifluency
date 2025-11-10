<?php
namespace App\Utils;

/**
 * Response Utility Class
 *
 * Provides standardized JSON response formatting for the API
 */
class Response
{
    /**
     * Send a success response
     *
     * @param mixed $data Response data
     * @param string $message Optional success message
     * @param int $statusCode HTTP status code (default 200)
     * @return void
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send an error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default 400)
     * @param array $errors Optional array of validation errors
     * @return void
     */
    public static function error(string $message = 'An error occurred', int $statusCode = 400, array $errors = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        $response = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send a validation error response
     *
     * @param array $errors Array of field-specific validation errors
     * @param string $message Optional error message
     * @return void
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, 422, $errors);
    }

    /**
     * Send an unauthorized error response
     *
     * @param string $message Error message
     * @return void
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    /**
     * Send a forbidden error response
     *
     * @param string $message Error message
     * @return void
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    /**
     * Send a not found error response
     *
     * @param string $message Error message
     * @return void
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Send an internal server error response
     *
     * @param string $message Error message
     * @return void
     */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }

    /**
     * Send a paginated response
     *
     * @param array $items Array of items
     * @param int $total Total number of items
     * @param int $page Current page number
     * @param int $pageSize Number of items per page
     * @param string $message Optional message
     * @return void
     */
    public static function paginated(array $items, int $total, int $page, int $pageSize, string $message = 'Success'): void
    {
        $totalPages = ceil($total / $pageSize);

        $data = [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ]
        ];

        self::success($data, $message);
    }
}
