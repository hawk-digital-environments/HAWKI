<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Support\Facades\Auth;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

class AssistantFavoriteFilter implements Filter
{
    use DeserializesValue;
    use IsSingular;

    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'is_favorite';
    }

    public function apply($query, $value)
    {
        return app(AssistantRepository::class)->filterByIsFavorite(
            $query,
            Auth::user(),
            filter_var($value, FILTER_VALIDATE_BOOLEAN),
        );
    }
}
