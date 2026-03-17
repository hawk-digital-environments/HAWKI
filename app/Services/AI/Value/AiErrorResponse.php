<?php
declare(strict_types=1);


namespace App\Services\AI\Value;

/**
 * A special AiResponse that represents an error that occurred during the processing of an AI request.
 * This allows us to return a structured response even in error cases, which can be useful for the
 * frontend to display error messages in a consistent way, and also for logging and debugging purposes.
 */
readonly class AiErrorResponse extends AiResponse
{
    public function __construct(
        string $message
    )
    {
        parent::__construct(
            content: [
                'text' => 'INTERNAL ERROR: ' . $message,
                'error' => $message
            ],
            error: $message
        );
    }
}
