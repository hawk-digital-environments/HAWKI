<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Contracts;


interface AgentRequestFactoryInterface
{
    public function createFromPayload(array $payload): AgentRequestInterface;
}
