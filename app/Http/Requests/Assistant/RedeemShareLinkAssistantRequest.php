<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Any signed-in user may redeem a share link; the token itself is the
 * authorization and is checked by the service.
 */
class RedeemShareLinkAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.token' => ['required', 'string', 'max:255'],
        ];
    }

    public function token(): string
    {
        return (string) $this->validated('data.token');
    }
}
