<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Repositories\AssistantRepository;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

class CategoryFilter implements Filter
{
    use DeserializesValue;
    use IsSingular;

    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'category';
    }

    public function apply($query, $value)
    {
        return app(AssistantRepository::class)->filterByCategoryText($query, $value);
    }
}
