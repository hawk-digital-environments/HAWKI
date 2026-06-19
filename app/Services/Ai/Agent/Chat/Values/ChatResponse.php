<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Chat\Values;


use App\Services\Ai\Agent\Contracts\AgentResponseInterface;

class ChatResponse implements AgentResponseInterface
{
    public function __construct(
        public readonly string $content
    )
    {
    }
}
