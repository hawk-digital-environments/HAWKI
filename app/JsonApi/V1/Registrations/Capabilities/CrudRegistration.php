<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Registrations\Capabilities;

use App\Http\Errors\CodedError;
use App\Models\User;
use App\Services\Announcements\Exceptions\RegistrationPolicyUnavailableException;
use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Announcements\Values\ConsentState;
use App\Services\Announcements\Values\PolicyConsentReference;
use App\Services\Auth\Exceptions\RegistrationAlreadyCompletedException;
use App\Services\Auth\Exceptions\RegistrationKeychainInconsistentException;
use App\Services\Auth\Exceptions\RegistrationPolicyChangedException;
use App\Services\Auth\RegistrationPayloadFingerprint;
use App\Services\Auth\RegistrationService;
use App\Services\Auth\SpaAuthHandoff;
use App\Services\Auth\Value\Registration;
use App\Services\Frontend\Connection\ConnectionFactory;
use App\Services\Profile\Values\PasskeyBackupSecret;
use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use App\Services\Users\Keychain\Value\KeychainBatch;
use App\Services\Users\Keychain\Value\UserKeychainValueToSet;
use App\Services\Users\Keychain\Value\UserKeychainValueType;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use LaravelJsonApi\NonEloquent\Capabilities\CrudResource;
use App\Services\System\Values\HawkiInstanceId;

/**
 * CRUD capability of the `registrations` resource — the completion write path. One create
 * runs the whole registration transaction (user, keychain, backup, policy consent,
 * fingerprint), maps domain failures to stable error codes, and — on success — logs the
 * freshly registered user into the guard so the controller's `created` hook answers with
 * the authenticated connection.
 */
class CrudRegistration extends CrudResource
{
    public function __construct(
        private readonly RegistrationService             $registrations,
        private readonly RegistrationPayloadFingerprint $fingerprints,
        private readonly UserContext                    $userContext,
        private readonly AuthManager                    $auth,
        Request                                        $request,
    ) {
        parent::__construct();
        $this->request = $request;
    }

    public function create(array $validatedData): object
    {
        $actor = $this->resolveActor();

        try {
            $user = $this->registrations->complete(
                $actor,
                $this->keychainBatch($validatedData),
                $this->backup($validatedData),
                $this->policyReference($validatedData),
                $this->fingerprints->make([
                    'policy' => $validatedData['policy'] ?? null,
                    'keychain' => $validatedData['keychain'],
                    'backup' => $validatedData['backup'],
                ]),
                $this->request->getLocaleContext()->getCurrentLocale()->lang,
            );
        } catch (RegistrationPolicyUnavailableException $exception) {
            report($exception);
            CodedError::abort(
                'registration_policy_unavailable',
                503,
                'Registration policy unavailable',
                $exception->getMessage()
            );
        } catch (RegistrationPolicyChangedException) {
            CodedError::abort(
                'registration_policy_changed',
                409,
                'Registration policy changed',
                'Reload the current policy before completing registration.'
            );
        } catch (RegistrationAlreadyCompletedException) {
            $this->clearRegisteringSession();
            CodedError::abort(
                'registration_already_completed',
                409,
                'Registration already completed',
                'This account was initialized by another registration request.'
            );
        } catch (RegistrationKeychainInconsistentException) {
            CodedError::abort(
                'registration_keychain_inconsistent',
                409,
                'Keychain is inconsistent',
                'Reset the profile explicitly before creating another keychain.'
            );
        }

        $this->clearRegisteringSession();
        $this->auth->login($user);
        $this->userContext->set(WellKnownUserTypes::USER);

        return new Registration(HawkiInstanceId::HAWKI, null, ConsentState::VALID);
    }

    private function resolveActor(): User|RegisteringUser
    {
        $actor = $this->request->user();
        if ($actor instanceof User) {
            return $actor;
        }

        $registeringUser = $this->request->getUserContext()->getRegisteringUser();
        if ($registeringUser === null) {
            CodedError::abort('registration_not_in_progress', 403, 'No registration in progress');
        }

        return $registeringUser;
    }

    private function clearRegisteringSession(): void
    {
        $this->request->session()->forget([
            'authenticatedUserInfo',
            'registration_access',
            SpaAuthHandoff::SESSION_REGISTRATION_UI_KEY,
        ]);
        $this->userContext->setRegisteringUser(null);
    }

    /** @param array<string, mixed> $data */
    private function keychainBatch(array $data): KeychainBatch
    {
        $set = array_map(
            static fn(array $entry): UserKeychainValueToSet => new UserKeychainValueToSet(
                key: $entry['key'],
                value: SymmetricCryptoValue::fromString($entry['value']),
                type: UserKeychainValueType::from($entry['type'])
            ),
            $data['keychain']['set']
        );

        return KeychainBatch::forFreshKeychain($data['keychain']['publicKey'], $set);
    }

    /** @param array<string, mixed> $data */
    private function backup(array $data): PasskeyBackupSecret
    {
        return PasskeyBackupSecret::fromArray($data['backup']);
    }

    private function policyReference(array $data): ?PolicyConsentReference
    {
        $policy = $data['policy'] ?? null;

        return $policy === null ? null : PolicyConsentReference::fromClientPayload($policy);
    }
}
