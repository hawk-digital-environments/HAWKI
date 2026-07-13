<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantCategories;

use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AssistantCategoryRequest extends ResourceRequest
{
    /**
     * Get the validation rules for the resource.
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', Rule::unique('assistant_categories', 'text')],
        ];
    }
}
