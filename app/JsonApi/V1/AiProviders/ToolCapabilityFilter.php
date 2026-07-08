<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiProviders;

use App\Services\AI\Repositories\AiProviderRepository;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

class ToolCapabilityFilter implements Filter
{
    use DeserializesValue;
    use IsSingular;

    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'tool_capability';
    }

    public function apply($query, $value)
    {
        return app(AiProviderRepository::class)->filterByToolCapability($query, $value);
    }
}
