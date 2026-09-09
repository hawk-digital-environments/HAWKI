<?php
declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Errors\CodedError;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use Illuminate\Foundation\Http\FormRequest;

class RegistrationPolicyRequest extends FormRequest
{
    public function authorize(RegistrationService $registration): bool
    {
        $user = $this->user();
        if (!$this->getUserContext()->isRegisteringUser()
            && (!$user instanceof User || !$registration->canComplete($user))) {
            CodedError::abort('registration_not_in_progress', 403, 'No registration in progress');
        }

        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
