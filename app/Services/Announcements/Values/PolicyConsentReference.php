<?php
declare(strict_types=1);

namespace App\Services\Announcements\Values;

/**
 * The policy revision a registration completion refers to — the id and content hash the
 * client sends back after showing the policy to the user.
 *
 * Compared against the {@see RegistrationPolicy} that is currently in effect: matching both
 * id and hash is what lets the server notice that the policy was edited while the user was
 * reading it. Shape and constraints come from the `registrations` request validation.
 */
readonly class PolicyConsentReference
{
    public function __construct(
        /** Id of the underlying announcement. */
        public int    $id,
        /** Hash of the normalized policy text the user saw. */
        public string $hash,
    )
    {
    }

    /**
     * @param array{id: int|string, hash: string} $clientPayload the validated `policy` payload
     */
    public static function fromClientPayload(array $clientPayload): self
    {
        return new self((int)$clientPayload['id'], $clientPayload['hash']);
    }
}
