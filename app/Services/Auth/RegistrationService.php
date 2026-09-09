<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Auth\Exception\RegistrationAlreadyCompletedException;
use App\Services\Auth\Exception\RegistrationKeychainInconsistentException;
use App\Services\Auth\Exception\RegistrationPolicyChangedException;
use App\Services\Frontend\Migrations\Repositories\AppliedFrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationUserdataRepository;
use App\Services\Profile\PasskeyService;
use App\Services\Profile\Values\PasskeyBackupSecret;
use App\Services\System\UserTypes\Values\RegisteringUser;
use App\Services\Users\Keychain\KeychainBatchWriter;
use App\Services\Users\Keychain\KeychainStateResolver;
use App\Services\Users\Keychain\Value\KeychainBatch;
use App\Services\Users\Keychain\Value\KeychainState;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

#[Singleton]
readonly class RegistrationService
{
    public function __construct(
        private UserRepository $users,
        private KeychainBatchWriter $keychains,
        private KeychainStateResolver $keychainStates,
        private PasskeyService $passkeys,
        private RegistrationPolicyService $policies,
        private FrontendMigrationRepository $frontendMigrations,
        private AppliedFrontendMigrationRepository $appliedMigrations,
        private FrontendMigrationUserdataRepository $migrationData,
    ) {
    }

    /**
     * @param array{id: int, hash: string}|null $policyReference
     */
    public function complete(
        User|RegisteringUser $actor,
        KeychainBatch $keychain,
        PasskeyBackupSecret $backup,
        ?array $policyReference,
        string $fingerprint,
        ?string $requestedLocale = null,
    ): User {
        try {
            return $this->completeInTransaction(
                $actor,
                $keychain,
                $backup,
                $policyReference,
                $fingerprint,
                $requestedLocale
            );
        } catch (QueryException $exception) {
            if (!$this->isUsernameCollision($exception)) {
                throw $exception;
            }

            return $this->completeInTransaction(
                $actor,
                $keychain,
                $backup,
                $policyReference,
                $fingerprint,
                $requestedLocale
            );
        }
    }

    public function canComplete(User $user): bool
    {
        return !$user->isRemoved && $this->keychainState($user) === KeychainState::SETUP_REQUIRED;
    }

    /**
     * @param array{id: int, hash: string}|null $policyReference
     */
    private function completeInTransaction(
        User|RegisteringUser $actor,
        KeychainBatch $keychain,
        PasskeyBackupSecret $backup,
        ?array $policyReference,
        string $fingerprint,
        ?string $requestedLocale,
    ): User {
        return DB::transaction(function () use (
            $actor,
            $keychain,
            $backup,
            $policyReference,
            $fingerprint,
            $requestedLocale
        ): User {
            $user = $this->users->lockOneByUsername($actor->username);

            if ($user !== null && !$user->isRemoved && $user->publicKey !== '') {
                if (is_string($user->registration_fingerprint)
                    && hash_equals($user->registration_fingerprint, $fingerprint)) {
                    return $user;
                }

                throw new RegistrationAlreadyCompletedException();
            }

            if ($user !== null && !$user->isRemoved && $this->keychainState($user) !== KeychainState::SETUP_REQUIRED) {
                throw new RegistrationKeychainInconsistentException();
            }

            $currentPolicy = $this->policies->resolve($requestedLocale);
            $this->assertPolicyReference($user, $policyReference, $currentPolicy);

            $name = $actor->name;
            $email = $actor->email;
            $employeeType = $actor instanceof RegisteringUser ? $actor->employeeType : $actor->employeetype;

            if ($user === null) {
                $user = $this->users->createForRegistration(
                    $actor->username,
                    $name,
                    $email,
                    $employeeType
                );
            } else {
                $this->users->prepareForRegistration($user, $name, $email, $employeeType);
            }

            $this->keychains->write($user, $keychain);
            $this->passkeys->backupPassKey($user->username, $backup);
            $this->policies->recordConsent($user, $currentPolicy);
            $this->appliedMigrations->applyAllForNewUser($this->frontendMigrations->findAll(), $user);
            $this->users->updateRegistrationFingerprint($user, $fingerprint);

            return $user->refresh();
        }, 3);
    }

    /**
     * @param array{id: int, hash: string}|null $policyReference
     */
    private function assertPolicyReference(
        ?User $user,
        ?array $policyReference,
        \App\Services\Announcements\Values\RegistrationPolicy $currentPolicy
    ): void {
        if ($policyReference === null) {
            if ($user !== null && $this->policies->hasValidConsent($user, $currentPolicy)) {
                return;
            }

            throw new RegistrationPolicyChangedException();
        }

        if ($policyReference['id'] !== $currentPolicy->id
            || !hash_equals($currentPolicy->hash, strtolower($policyReference['hash']))) {
            throw new RegistrationPolicyChangedException();
        }
    }

    private function keychainState(User $user): KeychainState
    {
        $legacyData = $this->migrationData->findOneForMigrationAndUser(
            KeychainStateResolver::LEGACY_KEYCHAIN_MIGRATION,
            $user
        );
        $hasLegacyBlob = $legacyData !== null
            && is_array($legacyData->data)
            && isset($legacyData->data['blob'])
            && $legacyData->data['blob'] !== '';

        return $this->keychainStates->resolveForUser($user, $hasLegacyBlob);
    }

    private function isUsernameCollision(QueryException $exception): bool
    {
        $sqlState = (string)($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'username');
    }
}
