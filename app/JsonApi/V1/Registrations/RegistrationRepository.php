<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Registrations;

use App\Http\Errors\CodedError;
use App\JsonApi\V1\Registrations\Capabilities\CrudRegistration;
use App\Models\User;
use App\Services\Announcements\Exceptions\RegistrationPolicyUnavailableException;
use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Auth\RegistrationService;
use App\Services\Auth\Value\Registration;
use App\Services\System\UserTypes\UserContext;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Store\CreatesResources;
use LaravelJsonApi\NonEloquent\AbstractRepository;
use LaravelJsonApi\NonEloquent\Concerns\HasCrudCapability;

/**
 * Repository of the `registrations` JSON:API resource. Reading the singleton `hawki`
 * answers "which policy still needs consent"; creating one completes the registration
 * through the {@see CrudRegistration} capability.
 */
class RegistrationRepository extends AbstractRepository implements CreatesResources
{
    private const string HAWKI_ID = 'hawki';

    public function __construct(
        private readonly RegistrationPolicyService $policies,
        private readonly RegistrationService      $registrations,
        private readonly UserContext              $userContext,
        private readonly Request                  $request,
    ) {
    }

    public function find(string $resourceId): ?object
    {
        if ($resourceId !== self::HAWKI_ID) {
            return null;
        }

        $user = $this->request->user();
        $registering = $this->userContext->isRegisteringUser();
        if (!$registering
            && (!($user instanceof User) || !$this->registrations->canComplete($user))) {
            CodedError::abort('registration_not_in_progress', 403, 'No registration in progress');
        }

        try {
            $policy = $this->policies->resolve($this->request->getLocaleContext()->getCurrentLocale()->lang);
        } catch (RegistrationPolicyUnavailableException $exception) {
            report($exception);
            CodedError::abort(
                'registration_policy_unavailable',
                503,
                'Registration policy unavailable',
                $exception->getMessage()
            );
        }

        if ($user instanceof User && $this->policies->hasValidConsent($user, $policy)) {
            return new Registration(self::HAWKI_ID, null, RegistrationPolicyService::CONSENT_VALID);
        }

        return new Registration(self::HAWKI_ID, $policy, RegistrationPolicyService::CONSENT_REQUIRED);
    }

    protected function crud(): CrudRegistration
    {
        return CrudRegistration::make();
    }
}
