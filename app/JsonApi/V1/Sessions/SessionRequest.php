<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Sessions;

use App\Http\Errors\CodedError;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class SessionRequest extends ResourceRequest
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
            'account' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ];
    }
}
