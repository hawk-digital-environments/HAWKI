<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


readonly class AiRequest
{
    public function __construct(
        public ?AiModel $model = null,
        public ?array   $payload = null
    )
    {
    }

    public function withModel(AiModel $model): self
    {
        return new self(
            model: $model,
            payload: $this->payload
        );
    }

    /**
     * Returns true if the request should be sent as a stream request.
     * This is determined by checking if the model supports streaming and if the payload indicates
     * that streaming is requested.
     * @return bool
     */
    public function shouldStream(): bool
    {
        // If no model is set, we can't determine if streaming is supported, so we return false.
        if (!$this->model) {
            return false;
        }

        // We check the payload for a 'stream' key, which should be a boolean indicating whether streaming is requested.
        $streamPayload = $this->payload['stream'] ?? false;
        if (!is_bool($streamPayload) || !$streamPayload) {
            return false;
        }

        // If the model supports streaming and the payload requests streaming, we return true.
        return $this->model->hasCapability('stream');
    }

    /**
     * Returns a new AiRequest instance with the 'stream' key set to true in the payload,
     * indicating that this request should be sent as a stream request.
     * @return self
     */
    public function withStreaming(): self
    {
        return new self(
            model: $this->model,
            payload: array_merge($this->payload ?? [], ['stream' => true])
        );
    }

    /**
     * Returns a new AiRequest instance with the 'stream' key set to false in the payload,
     * indicating that this request should be sent as a regular request.
     * @return self
     */
    public function withoutStreaming(): self
    {
        return new self(
            model: $this->model,
            payload: array_merge($this->payload ?? [], ['stream' => false])
        );
    }
}
