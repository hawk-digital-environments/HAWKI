<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantSettingValues;

use App\Models\Assistants\AssistantSettingValue;
use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AssistantSettingValueSchema extends Schema
{
    public static string $model = AssistantSettingValue::class;

    public function __construct(
        Server $server,
        private readonly AssistantRepository $repository,
    ) {
        parent::__construct($server);
    }

    public static function type(): string
    {
        return 'assistant-setting-values';
    }

    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('value'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            BelongsTo::make('assistant')->type('assistants'),
            BelongsTo::make('setting')->type('assistant-settings'),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function indexQuery(?Request $request, Builder $query): Builder
    {
        $user = $request?->user();

        if ($user === null) {
            return $query;
        }

        return $query->whereHas('assistant', fn (Builder $assistantQuery) => $this->repository->filterPrivilegedForUser($assistantQuery, $user));
    }
}
