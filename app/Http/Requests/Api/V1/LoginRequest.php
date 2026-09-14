<?php
declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Errors\CodedError;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->hasSession()) {
            CodedError::abort('auth_session_required', 403, 'A browser session is required');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
