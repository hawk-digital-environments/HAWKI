<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\Http\Controllers\Controller;
use App\JsonApi\V1\Assistants\AssistantQuery;
use App\JsonApi\V1\Assistants\AssistantRequest;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\Assistants\ReleaseAssistantRequest;
use App\Models\Assistants\Assistant;
use App\Policies\AssistantPolicy;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantController extends Controller
{
    use Actions\AttachRelationship;
    use Actions\Destroy;
    use Actions\DetachRelationship;
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;
    use Actions\Update;
    use Actions\UpdateRelationship;

    public function __construct(private readonly AssistantService $assistantService)
    {
    }

    public function created(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        Event::dispatch(new AssistantCreated($assistant));
    }

    /**
     * Gate sensitive relationship include paths on the show endpoint. The
     * framework only authorises dedicated related/relationship URLs, not the
     * ?include query parameter, so a viewer who can `view` the assistant would
     * otherwise receive privileged children (assistant_setting_values, assistant_tags,
     * assistant_feedback, assistant_review, ai_tools) inline. Authorise each requested sensitive include
     * against the assistant here.
     *
     * @param mixed $request
     */
    public function read(Assistant $assistant, $request): void
    {
        $sensitive = array_merge(
            AssistantPolicy::PRIVILEGED_RELATIONSHIPS,
            AssistantPolicy::COLLABORATE_RELATIONSHIPS,
        );

        $paths = collect(explode(',', (string) $request->query('include', '')))
            ->filter()
            ->map(static fn (string $path) => explode('.', $path)[0]);

        foreach ($paths as $field) {
            if (\in_array($field, $sensitive, true)) {
                \Illuminate\Support\Facades\Gate::authorize('view' . Str::studly($field), $assistant);
            }
        }
    }

    public function updated(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        $changedKeys = array_values(array_filter(
            array_keys($assistant->getChanges()),
            static fn (string $key) => 'updated_at' !== $key,
        ));

        $validated = $request->validated();

        if (isset($validated['assistant_tags'])) {
            $changedKeys[] = 'assistant_tags';
        }

        if (isset($validated['ai_tools'])) {
            $changedKeys[] = 'ai_tools';
        }

        if ([] !== $changedKeys) {
            Event::dispatch(new AssistantUpdated(
                $assistant,
                null,
                $changedKeys,
            ));
        }
    }

    public function remix(AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        \Illuminate\Support\Facades\Gate::authorize('remix', $assistant);

        $remixed = $this->assistantService->remix($assistant, request()->user());

        if ($this->shouldNotifyUpdate($assistant)) {
            Event::dispatch(new AssistantUpdated($assistant, null, ['remixed']));
        }

        return DataResponse::make($remixed)
            ->withQueryParameters($query)
            ->didCreate();
    }

    public function release(
        ReleaseAssistantRequest $request,
        AssistantSchema $schema,
        AssistantQuery $query,
        Assistant $assistant,
    ): Responsable {
        \Illuminate\Support\Facades\Gate::authorize('release', $assistant);

        $releaseStage = AssistantReleaseStage::from($request->input('data.attributes.release_stage'));

        $assistant = $this->assistantService->release($assistant, $releaseStage);

        return DataResponse::make($assistant)
            ->withQueryParameters($query);
    }

    public function addFavorite(Assistant $assistant): Responsable
    {
        \Illuminate\Support\Facades\Gate::authorize('addFavorite', $assistant);

        $this->assistantService->setFavorite($assistant, request()->user(), isFavorite: true);

        return DataResponse::make($assistant->fresh());
    }

    public function removeFavorite(Assistant $assistant): Responsable
    {
        \Illuminate\Support\Facades\Gate::authorize('removeFavorite', $assistant);

        $this->assistantService->setFavorite($assistant, request()->user(), isFavorite: false);

        return DataResponse::make($assistant->fresh());
    }

    private function shouldNotifyUpdate(Assistant $assistant): bool
    {
        $skipStages = [AssistantReleaseStage::DRAFT->value, AssistantReleaseStage::PRIVATE->value];

        return !\in_array($assistant->release_stage, $skipStages, true);
    }
}
