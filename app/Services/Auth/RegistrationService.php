<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Announcements\Values\PolicyConsentReference;
use App\Services\Announcements\Values\RegistrationPolicy;
use App\Services\Auth\Exceptions\RegistrationAlreadyCompletedException;
use App\Services\Auth\Exceptions\RegistrationKeychainInconsistentException;
use App\Services\Auth\Exceptions\RegistrationPolicyChangedException;
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
use Illuminate\Database\DatabaseManager;

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
        private DatabaseManager $db,
    ) {
    }

    public function complete(
        User|RegisteringUser $actor,
        KeychainBatch $keychain,
        PasskeyBackupSecret $backup,
        ?PolicyConsentReference $policyReference,
        string $fingerprint,
        ?string $requestedLocale = null,
    ): User {
        return RegistrationGuard::retryUsernameCollision(fn(): User => $this->completeInTransaction(
            $actor,
            $keychain,
            $backup,
            $policyReference,
            $fingerprint,
            $requestedLocale
        ));
    }

    /**
     * Completes a registration through the legacy controller contract: create or reuse
     * the user row, without keychain, backup, fingerprint or policy consent — the legacy
     * Blade flow records consent afterwards and best-effort. The guard's completion rule
     * and its domain exception apply to both registration paths.
     */
    public function completeLegacy(RegisteringUser $actor): User
    {
        return RegistrationGuard::retryUsernameCollision(
            fn(): User => $this->db->transaction(fn(): User => $this->completeLegacyInTransaction($actor), 3)
        );
    }

    private function completeLegacyInTransaction(RegisteringUser $actor): User
    {
        $user = $this->users->lockOneByUsername($actor->username);

        RegistrationGuard::assertAccountNotInitialized($user);

        if ($user === null) {
            return $this->users->createForRegistration($actor->username, $actor->name, $actor->email, $actor->employeeType);
        }

        return $this->users->prepareForRegistration($user, $actor->name, $actor->email, $actor->employeeType);
    }

    public function canComplete(User $user): bool
    {
        return !$user->isRemoved && $this->keychainState($user) === KeychainState::SETUP_REQUIRED;
    }

    private function completeInTransaction(
        User|RegisteringUser $actor,
        KeychainBatch $keychain,
        PasskeyBackupSecret $backup,
        ?PolicyConsentReference $policyReference,
        string $fingerprint,
        ?string $requestedLocale,
    ): User {
        return $this->db->transaction(function () use (
            $actor,
            $keychain,
            $backup,
            $policyReference,
            $fingerprint,
            $requestedLocale
        ): User {
            $user = $this->users->lockOneByUsername($actor->username);

            if (RegistrationGuard::isAccountInitialized($user)) {
                if (is_string($user->registration_fingerprint)
                    && hash_equals($user->registration_fingerprint, $fingerprint)) {
                    return $user;
                }

                throw RegistrationAlreadyCompletedException::forUsername($user->username);
            }

            if ($user !== null && !$user->isRemoved && $this->keychainState($user) !== KeychainState::SETUP_REQUIRED) {
                throw RegistrationKeychainInconsistentException::forUsername($user->username);
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
                $this->appliedMigrations->applyAllForNewUser($this->frontendMigrations->findAll(), $user);
            }

            $this->keychains->write($user, $keychain);
            $this->passkeys->backupPassKey($user->username, $backup);
            $this->policies->recordConsent($user, $currentPolicy);
            $this->users->updateRegistrationFingerprint($user, $fingerprint);

            return $user->refresh();
        }, 3);
    }

    private function assertPolicyReference(
        ?User $user,
        ?PolicyConsentReference $policyReference,
        RegistrationPolicy $currentPolicy
    ): void {
        if ($policyReference === null) {
            if ($user !== null && $this->policies->hasValidConsent($user, $currentPolicy)) {
                return;
            }

            throw RegistrationPolicyChangedException::becauseConsentIsMissing($currentPolicy);
        }

        if ($policyReference->id !== $currentPolicy->id
            || !hash_equals($currentPolicy->hash, strtolower($policyReference->hash))) {
            throw RegistrationPolicyChangedException::becauseTheLivePolicyDiffers(
                $policyReference->id,
                $currentPolicy
            );
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
}
