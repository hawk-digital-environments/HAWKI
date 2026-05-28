<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\JsonApi\V1\Assistants\AssistantQuery;
use App\JsonApi\V1\Assistants\AssistantRequest;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\Assistants\FeedbackAssistantRequest;
use App\JsonApi\V1\Assistants\FavoriteAssistantRequest;
use App\JsonApi\V1\Assistants\ReleaseAssistantRequest;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\Store;
    use Actions\Update;
    use Actions\Destroy;

    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
        $this->authorizeResource(Assistant::class, 'assistant');
    }

    public function created(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        Event::dispatch(new AssistantCreated($assistant));
    }

    public function updated(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        $changedKeys = array_values(array_filter(
            array_keys($assistant->getChanges()),
            fn(string $key) => $key !== 'updated_at',
        ));

        $validated = $request->validated();
        if (isset($validated['tags'])) {
            $changedKeys[] = 'tags';
        }
        if (isset($validated['ai_tools'])) {
            $changedKeys[] = 'ai_tools';
        }
        if (isset($validated['user_prompts'])) {
            $changedKeys[] = 'user_prompts';
        }

        if ($changedKeys !== []) {
            Event::dispatch(new AssistantUpdated(
                $assistant,
                $validated['version_text'] ?? null,
                $changedKeys,
            ));
        }
    }

    public function remix(AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('remix', $assistant);

        $remixed = $this->assistantService->remix($assistant, request()->user());

        return DataResponse::make($remixed)
            ->withQueryParameters($query)
            ->didCreate();
    }

    public function feedback(
        FeedbackAssistantRequest $request,
        AssistantSchema $schema,
        AssistantQuery $query,
        Assistant $assistant,
    ): Responsable {
        $this->authorize('view', $assistant);

        $this->assistantService->feedback(
            $assistant,
            $request->user(),
            $request->input('data.attributes.text'),
        );

        return DataResponse::make($assistant)
            ->withQueryParameters($query);
    }

    public function release(ReleaseAssistantRequest $request, AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('release', $assistant);

        $releaseStage = ReleaseStage::from($request->input('data.attributes.release_stage'));

        $assistant = $this->assistantService->release($assistant, $releaseStage);

        return DataResponse::make($assistant)
            ->withQueryParameters($query);
    }

    public function favorite(
        FavoriteAssistantRequest $request,
        AssistantSchema $schema,
        AssistantQuery $query,
        Assistant $assistant,
    ): Responsable {
        $this->authorize('favorite', $assistant);

        $this->assistantService->setFavorite(
            $assistant,
            $request->user(),
            $request->boolean('data.attributes.is_favorite'),
        );

        return DataResponse::make($assistant->fresh())
            ->withQueryParameters($query);
    }
}
