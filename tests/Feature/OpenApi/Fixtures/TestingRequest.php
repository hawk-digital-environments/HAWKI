<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi\Fixtures;

use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class TestingRequest extends ResourceRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::enum(TestingStatus::class)],
            'max_count' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
