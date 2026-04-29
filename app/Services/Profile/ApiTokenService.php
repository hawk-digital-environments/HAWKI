<?php

namespace App\Services\Profile;

use App\Services\Profile\Exception\NoCurrentUserException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Psr\Log\LoggerInterface;

readonly class ApiTokenService
{
    public function __construct(
        private AuthFactory     $auth,
        private LoggerInterface $logger
    )
    {
    }

    public function createApiToken(string $name): NewAccessToken
    {
        $user = $this->auth->user();
        if (!$user) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }
        return $user->createToken($name);
    }

    /**
     * @return Collection<array{id: int, name: string}>
     */
    public function fetchTokenList(): Collection
    {
        $user = $this->auth->user();
        if (!$user) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }

        return $user->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
            ];
        });
    }

    public function revokeToken(int $tokenId): void
    {
        $user = $this->auth->user();
        if (!$user) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }
        try {
            $token = $user->tokens()->where('id', $tokenId);
            $token->delete();
        } catch (\Throwable $e) {
            $this->logger->error('Error revoking API token', ['token_id' => $tokenId, 'exception' => $e]);
            throw $e;
        }
    }
}
