<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Contracts;


interface AgentInterface
{
    public function sendRequest(AgentRequestInterface $request): AgentResponseInterface;
}
