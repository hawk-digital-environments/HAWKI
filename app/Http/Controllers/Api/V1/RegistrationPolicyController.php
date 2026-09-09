<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\CodedError;
use App\Models\User;
use App\Services\Announcements\Exceptions\RegistrationPolicyUnavailableException;
use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationPolicyController extends Controller
{
    public function show(
        Request $request,
        RegistrationPolicyService $policies,
        RegistrationService $registration,
    ): JsonResponse {
        try {
            $policy = $policies->resolve($request->getLocaleContext()->getCurrentLocale()->lang);
        } catch (RegistrationPolicyUnavailableException $exception) {
            CodedError::abort(
                'registration_policy_unavailable',
                503,
                'Registration policy unavailable',
                $exception->getMessage()
            );
        }

        $user = $request->user();
        $isRegistering = $request->getUserContext()->isRegisteringUser();
        if (!$isRegistering && (!$user instanceof User || !$registration->canComplete($user))) {
            abort(403, 'Registration setup is not available for this session.');
        }

        if ($user instanceof User && $policies->hasValidConsent($user, $policy)) {
            return response()->json(['policy' => null, 'consent' => RegistrationPolicyService::CONSENT_VALID]);
        }

        return response()->json([
            'id' => (string)$policy->id,
            'locale' => $policy->locale,
            'text' => $policy->text,
            'hash' => $policy->hash,
        ]);
    }
}
