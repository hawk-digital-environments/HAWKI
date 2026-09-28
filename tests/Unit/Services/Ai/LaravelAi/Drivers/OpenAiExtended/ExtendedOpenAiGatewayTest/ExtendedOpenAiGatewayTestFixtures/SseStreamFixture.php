<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi\Drivers\OpenAiExtended\ExtendedOpenAiGatewayTest\ExtendedOpenAiGatewayTestFixtures;

/**
 * Minimal byte-readable stream carrying a raw Server-Sent Events body, so the
 * gateway's SSE parser can be exercised without an HTTP client.
 */
class SseStreamFixture
{
    private int $position = 0;

    public function __construct(private readonly string $body)
    {
    }

    public static function fromLines(array $lines): self
    {
        return new self(implode("\n", $lines) . "\n");
    }

    public function eof(): bool
    {
        return mb_strlen($this->body) <= $this->position;
    }

    public function read(int $length): string
    {
        $chunk = mb_substr($this->body, $this->position, $length);
        $this->position += mb_strlen($chunk);

        return $chunk;
    }
}
