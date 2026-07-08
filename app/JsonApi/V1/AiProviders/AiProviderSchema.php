<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiProviders;

use App\Models\Ai\AiProvider;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AiProviderSchema extends Schema
{
    public static string $model = AiProvider::class;

    public static function type(): string
    {
        return 'ai-providers';
    }

    protected bool $selfLink = false;

    protected int $maxDepth = 2;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('provider_id'),
            Str::make('name'),
            Boolean::make('active'),
            Str::make('api_url'),
            Str::make('ping_url'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            HasMany::make('models')->type('ai-models')->readOnly(),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return [
            ToolCapabilityFilter::make(),
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
