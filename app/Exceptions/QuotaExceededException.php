<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when user exceeds their usage quota.
 */
class QuotaExceededException extends Exception
{
    public function __construct(string $message = 'Daily usage quota exceeded')
    {
        parent::__construct($message, 429); // 429 Too Many Requests
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        return response()->json([
            'error' => 'quota_exceeded',
            'message' => $this->getMessage(),
        ], 429);
    }
}
