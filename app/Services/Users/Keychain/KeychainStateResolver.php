<?php
declare(strict_types=1);


namespace App\Services\Users\Keychain;


use App\Models\User;
use App\Services\Users\Keychain\Repositories\UserKeychainRepository;
use App\Services\Users\Keychain\Value\KeychainState;
use App\Services\Users\Keychain\Value\UserKeychainValueType;
use Illuminate\Container\Attributes\Singleton;

/**
 * @api
 *
 * Decides which {@see KeychainState} an authenticated user is in.
 *
 * The frontend needs this before it can do anything with encrypted data, and it cannot work it
 * out itself: the difference between "brand new account" and "half-written keychain" lives in
 * rows the browser has no reason to have fetched yet, and getting it wrong is expensive —
 * running the setup ceremony over a partially populated keychain replaces the keys that the
 * user's existing messages were encrypted with.
 *
 * ```php
 * $state = $resolver->resolveForUser($user);   // KeychainState
 * ```
 */
#[Singleton]
readonly class KeychainStateResolver
{
    /**
     * The frontend migration that moved keychains off the pre-passkey-upgrade
     * `private_user_data` blob. While it is outstanding for a user, their keychain is expected
     * to look empty.
     * @see \database\migrations\2026_06_07_215609_after_passkey_upgrade_to_user_keychain_values
     */
    public const string LEGACY_KEYCHAIN_MIGRATION = '2026_06_07_215609_after_passkey_upgrade_to_user_keychain_values';

    /**
     * The entries every working keychain has. Room keys are per-room and therefore not part of
     * the baseline — a user with no rooms is still fully initialized.
     */
    private const array CORE_ENTRIES = [
        ['key' => 'privateKey', 'type' => UserKeychainValueType::PRIVATE_KEY],
        ['key' => 'publicKey', 'type' => UserKeychainValueType::PUBLIC_KEY],
        ['key' => 'aiConvKey', 'type' => UserKeychainValueType::AI_CONV],
    ];

    public function __construct(
        private UserKeychainRepository $repository
    )
    {
    }

    /**
     * @param bool $hasLegacyMigrationBlob Whether the outstanding pre-passkey migration has the
     *                                     encrypted legacy blob needed to perform it.
     * @see \App\Services\Frontend\Connection\ConnectionFactory
     */
    public function resolveForUser(User $user, bool $hasLegacyMigrationBlob): KeychainState
    {
        $entries = $this->repository->findAllKeysAndTypesOfUser($user);
        $coreEntryCount = $this->countCoreEntries($entries);
        $hasAllCoreEntries = $coreEntryCount === count(self::CORE_ENTRIES);
        $hasMalformedCoreEntry = $this->hasMalformedCoreEntry($entries);
        $hasPublicKey = $user->publicKey !== null && $user->publicKey !== '';

        if ($hasAllCoreEntries && $hasPublicKey) {
            return KeychainState::INITIALIZED;
        }

        if ($coreEntryCount === 0 && !$hasMalformedCoreEntry && $hasLegacyMigrationBlob) {
            return KeychainState::LEGACY_MIGRATION_REQUIRED;
        }

        if ($entries === [] && !$hasPublicKey) {
            return KeychainState::SETUP_REQUIRED;
        }

        return KeychainState::INCONSISTENT;
    }

    /**
     * @param list<array{key: string, type: string}> $entries
     */
    private function countCoreEntries(array $entries): int
    {
        $found = [];
        foreach ($entries as $entry) {
            foreach (self::CORE_ENTRIES as $core) {
                if ($entry['key'] === $core['key'] && $entry['type'] === $core['type']->value) {
                    $found[$core['key']] = true;
                }
            }
        }

        return count($found);
    }

    /**
     * @param list<array{key: string, type: string}> $entries
     */
    private function hasMalformedCoreEntry(array $entries): bool
    {
        $coreKeys = array_column(self::CORE_ENTRIES, 'key');
        $coreTypes = array_map(static fn(array $entry): string => $entry['type']->value, self::CORE_ENTRIES);

        foreach ($entries as $entry) {
            if (in_array($entry['key'], $coreKeys, true) || in_array($entry['type'], $coreTypes, true)) {
                return true;
            }
        }

        return false;
    }
}
