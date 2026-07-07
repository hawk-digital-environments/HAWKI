<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Contracts;


use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Values\TokenUsage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

interface AgentInterface
{
    public function getContext(): AgentRequestContext;

    public function getUsage(): TokenUsage;

    public function send(): AgentResponse;

    public function sendStreaming(): StreamableAgentResponse;
}
