<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class EstablishExtAppConnectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'passkey' => 'required|string',
            'connectRequest' => 'required|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function getPasskey(): string
    {
        return $this->input('passkey');
    }

    public function getConnectRequest(): string
    {
        return $this->input('connectRequest');
    }
}
