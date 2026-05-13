<?php

namespace App\JsonApi\V1\Languages;

use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class LanguageRequest extends ResourceRequest
{

    /**
     * Get the validation rules for the resource.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', Rule::unique('text')]
        ];
    }

}
