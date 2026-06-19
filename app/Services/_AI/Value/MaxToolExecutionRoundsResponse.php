<?php
declare(strict_types=1);


namespace App\Services\AI\Value;

/**
 * A special kind of AI response that tells the frontend, that the maximum tool execution rounds have been reached
 * and that the final response is being generated.
 */
readonly class MaxToolExecutionRoundsResponse extends AiResponse
{
    public function __construct()
    {
        parent::__construct(
            content: ['text' => ''],
            isDone: false,
            type: 'status',
            status: [
                'key' => 'max_execution',
                'value' => 'Maximum tool execution rounds reached. Generating final response...'
            ]
        );
    }
}
