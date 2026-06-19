<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiProviders;

use App\Models\Ai\AiProvider;
use App\Services\Ai\Values\ProviderSettings;
use App\Services\System\JsonApi\ValueSerializer;
use App\Services\Users\UserCondition;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AiProviderSchema extends Schema
{
    public static string $model = AiProvider::class;

    public static function type(): string
    {
        return 'ai-providers';
    }

    protected int $maxDepth = 2;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('provider_id'),
            Str::make('name'),
            Boolean::make('active')
                ->hidden(UserCondition::isNonAdmin(...)),
            Str::make('api_key')
                ->hidden(UserCondition::isNonAdmin(...))
                ->serializeUsing(ValueSerializer::apiKey(...)),
            Str::make('api_url')
                ->hidden(UserCondition::isNonAdmin(...)),
            Str::make('model_status_url')
                ->hidden(UserCondition::isNonAdmin(...)),
            ArrayHash::make('additional_config')
                ->hidden(UserCondition::isNonAdmin(...)),
            ArrayHash::make('settings')
                ->hidden(UserCondition::isNonAdmin(...))
                ->serializeUsing(function (?ProviderSettings $settings) {
                    if (!$settings) {
                        return null;
                    }

                    return $settings->toArray();
                }),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            HasMany::make('models')->type('ai-models')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            ToolCapabilityFilter::make(),
            Where::make('active')->asBoolean(),
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
