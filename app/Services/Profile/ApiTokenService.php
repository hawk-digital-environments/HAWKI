<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Services\Profile\Exception\NoCurrentUserException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Psr\Log\LoggerInterface;

readonly class ApiTokenService
{
    public function __construct(
        #[CurrentUser]
        private User|null       $currentUser,
        private LoggerInterface $logger
    )
    {
    }

    public function createApiToken(string $name): NewAccessToken
    {
        if (!$this->currentUser) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }
        return $this->currentUser->createToken($name);
    }

    /**
     * @return Collection<array{id: int, name: string}>
     */
    public function fetchTokenList(): Collection
    {
        if (!$this->currentUser) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }

        // Construct an array of token data
        return $this->currentUser->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
            ];
        });
    }

    public function revokeToken(int $tokenId): void
    {
        if (!$this->currentUser) {
            throw NoCurrentUserException::forMethod(__METHOD__);
        }
        try {
            $token = $this->currentUser->tokens()->where('id', $tokenId);
            $token->delete();
        } catch (\Throwable $e) {
            $this->logger->error('Error revoking API token', ['token_id' => $tokenId, 'exception' => $e]);
            throw $e;
        }
    }
}
