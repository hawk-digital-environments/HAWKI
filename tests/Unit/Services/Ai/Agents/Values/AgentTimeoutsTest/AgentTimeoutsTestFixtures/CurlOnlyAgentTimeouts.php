<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Values\AgentTimeoutsTest\AgentTimeoutsTestFixtures;

use App\Services\Ai\Agents\Values\AgentTimeouts;

/**
 * Simulates an environment without `allow_url_fopen`, where Guzzle serves streamed
 * requests through curl instead of its PHP stream handler.
 */
readonly class CurlOnlyAgentTimeouts extends AgentTimeouts
{
    protected function streamHandlerUsed(): bool
    {
        return false;
    }
}
