<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Services\Profile\Exception\NoCurrentUserException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Psr\Log\LoggerInterface;

readonly class ApiTokenService
{
    public function __construct(
        private Factory         $authFactory,
        private LoggerInterface $logger
    )
    {
    }

    public function createApiToken(string $name): NewAccessToken
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }
        return $currentUser->createToken($name);
    }

    /**
     * @return Collection<array{id: int, name: string}>
     */
    public function fetchTokenList(): Collection
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }

        // Construct an array of token data
        return $currentUser->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
            ];
        });
    }

    public function revokeToken(int $tokenId): void
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }
        try {
            $token = $currentUser->tokens()->where('id', $tokenId);
            $token->delete();
        } catch (\Throwable $e) {
            $this->logger->error('Error revoking API token', ['token_id' => $tokenId, 'exception' => $e]);
            throw $e;
        }
    }

    /**
     * We resolve the current user from the auth factory to ensure we are using the currently resolved
     * user, even if the user was set after the service was constructed.
     * This is important for cases where the user might be set in a middleware or other part of the request lifecycle.
     * @return User|null
     */
    private function getCurrentUser(): User|null
    {
        return $this->authFactory->guard()->user();
    }
}
