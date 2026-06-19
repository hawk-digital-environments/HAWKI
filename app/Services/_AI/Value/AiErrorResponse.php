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
    private array $stackTrace;

    public function __construct(
        private string $message,
        array|null     $stackTrace = null,
        private bool   $showStacktrace = false
    )
    {
        $content = [
            'text' => 'INTERNAL ERROR: ' . $this->message,
            'error' => $this->message
        ];

        $this->stackTrace = $stackTrace ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        parent::__construct(
            content: $content,
            error: $this->message
        );
    }

    /**
     * Returns a new instance of AiErrorResponse with the showStacktrace property set to the given value.
     * This allows us to control whether the stack trace should be included in the response or not.
     * @param bool $show
     * @return self
     */
    public function withShowStacktrace(bool $show): self
    {
        return new self(
            message: $this->message,
            stackTrace: $this->stackTrace,
            showStacktrace: $show
        );
    }

    /**
     * Gets the stack trace associated with this error response.
     * @return array
     */
    public function getStackTrace(): array
    {
        return $this->stackTrace;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        if ($this->showStacktrace) {
            return array_merge(parent::toArray(), [
                'stackTrace' => $this->stackTrace
            ]);
        }
        return parent::toArray();
    }
}
