<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Factories\Contracts;

use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Chat\Values\AiRequest;

/**
 * Creates an {@see AgentInterface} for an {@see AiRequest}.
 *
 * Unlike a generic mixed-request factory, the
 * request is already the typed IR — factories inspect it (including its hawki
 * extensions) to accept or decline.
 */
interface ChatAgentFactoryInterface
{
    /**
     * Returns a ready-to-use agent, or null when this factory does not claim the request.
     */
    public function createAgent(AiRequest $request): ?AgentInterface;
}
