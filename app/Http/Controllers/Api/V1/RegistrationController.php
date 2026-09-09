<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\CodedError;
use App\Http\Requests\Api\V1\CompleteRegistrationRequest;
use App\Models\User;
use App\Services\Announcements\Exceptions\RegistrationPolicyUnavailableException;
use App\Services\Auth\Exception\RegistrationAlreadyCompletedException;
use App\Services\Auth\Exception\RegistrationKeychainInconsistentException;
use App\Services\Auth\Exception\RegistrationPolicyChangedException;
use App\Services\Auth\RegistrationPayloadFingerprint;
use App\Services\Auth\RegistrationService;
use App\Services\Auth\SpaAuthHandoff;
use App\Services\Frontend\Connection\ConnectionFactory;
use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\UserContext;
use Illuminate\Auth\AuthManager;
use LaravelJsonApi\Core\Responses\DataResponse;

class RegistrationController extends Controller
{
    public function complete(
        CompleteRegistrationRequest $request,
        RegistrationService $registration,
        RegistrationPayloadFingerprint $fingerprints,
        ConnectionFactory $connections,
        UserContext $userContext,
        AuthManager $auth,
    ): DataResponse {
        $actor = $request->user();
        if (!$actor instanceof User) {
            $actor = $userContext->getRegisteringUser();
        }
        if ($actor === null) {
            abort(403, 'No registration in progress.');
        }

        try {
            $user = $registration->complete(
                $actor,
                $request->keychainBatch(),
                $request->backup(),
                $request->policyReference(),
                $request->payloadFingerprint($fingerprints),
                $request->getLocaleContext()->getCurrentLocale()->lang,
            );
        } catch (RegistrationPolicyUnavailableException $exception) {
            CodedError::abort(
                'registration_policy_unavailable',
                503,
                'Registration policy unavailable',
                $exception->getMessage()
            );
        } catch (RegistrationPolicyChangedException) {
            CodedError::abort(
                'policy_changed',
                409,
                'Registration policy changed',
                'Reload the current policy before completing registration.'
            );
        } catch (RegistrationAlreadyCompletedException) {
            $this->clearRegisteringSession($request, $userContext);
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

        $this->clearRegisteringSession($request, $userContext);
        $auth->login($user);
        $userContext->set(WellKnownUserTypes::USER);

        return DataResponse::make($connections->createHawkiConnection())
            ->withMeta(['next' => 'handshake']);
    }

    private function clearRegisteringSession(
        CompleteRegistrationRequest $request,
        UserContext $userContext
    ): void {
        $request->session()->forget([
            'authenticatedUserInfo',
            'registration_access',
            SpaAuthHandoff::SESSION_REGISTRATION_UI_KEY,
        ]);
        $userContext->setRegisteringUser(null);
    }
}
