<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantAvatars;

use App\Models\Assistants\AssistantAvatar;
use App\Services\Assistant\AssistantAvatarUrlResolver;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AssistantAvatarSchema extends Schema
{
    public static string $model = AssistantAvatar::class;

    public function __construct(Server $server, private AssistantAvatarUrlResolver $urlResolver)
    {
        parent::__construct($server);
    }

    public static function type(): string
    {
        return 'assistant-avatars';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name')->sortable(),
            Str::make('url')->readOnly()->extractUsing(
                fn (AssistantAvatar $model) => $this->urlResolver->forUuid($model->uuid),
            ),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
