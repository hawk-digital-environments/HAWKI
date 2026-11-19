<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

use Carbon\CarbonInterface;

/**
 * A freshly minted share link as handed to its creator: the plain token
 * exists only here, the database keeps its hash.
 */
final readonly class AssistantShareLinkToken
{
    private function __construct(
        public string $token,
        public bool $canEdit,
        public CarbonInterface $expiresAt,
    ) {
    }

    public static function mint(string $token, bool $canEdit, CarbonInterface $expiresAt): self
    {
        return new self($token, $canEdit, $expiresAt);
    }

    /**
     * The controller's wire payload; the plain token is rendered exactly
     * once, at mint time.
     *
     * @return array{token: string, can_edit: bool, expires_at: string}
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'can_edit' => $this->canEdit,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}
