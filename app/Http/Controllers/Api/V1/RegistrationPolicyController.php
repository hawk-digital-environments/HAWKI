<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\CodedError;
use App\Http\Requests\Api\V1\RegistrationPolicyRequest;
use App\Models\User;
use App\Services\Announcements\Exceptions\RegistrationPolicyUnavailableException;
use App\Services\Announcements\RegistrationPolicyService;
use Illuminate\Http\JsonResponse;

class RegistrationPolicyController extends Controller
{
    public function show(
        RegistrationPolicyRequest $request,
        RegistrationPolicyService $policies,
    ): JsonResponse {
        $user = $request->user();
        try {
            $policy = $policies->resolve($request->getLocaleContext()->getCurrentLocale()->lang);
        } catch (RegistrationPolicyUnavailableException $exception) {
            report($exception);
            CodedError::abort(
                'registration_policy_unavailable',
                503,
                'Registration policy unavailable',
                $exception->getMessage()
            );
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
