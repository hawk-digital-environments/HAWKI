<?php
declare(strict_types=1);


namespace App\Services\Users\Keychain\Value;


/**
 * One atomic set of changes to a user's keychain, as
 * {@see \App\Services\Users\Keychain\KeychainBatchWriter} applies it.
 *
 * The order of the parts is part of the meaning, not an implementation detail: `clean` wipes the
 * existing keychain before `set` writes the new values, which is how a fresh registration and a
 * passkey change express "these values, and nothing else".
 */
readonly class KeychainBatch
{
    /**
     * @param bool                            $clean     Drop all existing values of the user first.
     * @param string|null                     $publicKey New plain public key of the user, or null to keep the current one.
     * @param list<UserKeychainValueToSet>    $set       Values to create or overwrite.
     * @param list<UserKeychainValueToRemove> $remove    Values to delete.
     */
    public function __construct(
        public bool    $clean = false,
        public ?string $publicKey = null,
        public array   $set = [],
        public array   $remove = []
    )
    {
    }

    /**
     * The batch a newly registered user starts with: their public key plus the initial keychain,
     * and nothing left over from a previous account with the same username.
     *
     * @param list<UserKeychainValueToSet> $set
     */
    public static function forFreshKeychain(string $publicKey, array $set): self
    {
        return new self(
            clean: true,
            publicKey: $publicKey,
            set: $set
        );
    }
}
