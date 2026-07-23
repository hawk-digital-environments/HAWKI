<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Contracts;


interface AgentFactoryInterface
{
    public function createAgent(mixed $request): AgentInterface|null;
}
