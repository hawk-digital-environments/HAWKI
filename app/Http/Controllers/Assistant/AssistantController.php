<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\AddFavoriteAssistantRequest;
use App\Http\Requests\Assistant\DeleteAssistantAttachmentRequest;
use App\Http\Requests\Assistant\RemixAssistantRequest;
use App\Http\Requests\Assistant\RemoveFavoriteAssistantRequest;
use App\Http\Requests\Assistant\UploadAssistantAttachmentRequest;
use App\JsonApi\V1\Assistants\AssistantQuery;
use App\JsonApi\V1\Assistants\AssistantRequest;
use App\JsonApi\V1\Assistants\ReleaseAssistantRequest;
use App\Models\Assistants\Assistant;
use App\Policies\Traits\AuthorizesSensitiveIncludesTrait;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Events\AssistantCreatedEvent;
use App\Services\Assistant\Events\AssistantUpdatedEvent;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Storage\Values\FileReference;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Routing\Route;
use LaravelJsonApi\Contracts\Store\Store as StoreContract;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use Symfony\Component\HttpFoundation\Response;

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
    use AuthorizesSensitiveIncludesTrait;

    public function __construct(
        private readonly AssistantService $assistantService,
        private readonly \Illuminate\Contracts\Events\Dispatcher $events,
    ) {
    }

    /**
     * Override json api life cycle hook to register create events.
     * Details: https://laraveljsonapi.io/5.x/routing/controllers.html#store
     * @param Assistant $assistant
     * @param AssistantRequest $request
     * @param AssistantQuery $query
     * @return void
     */
    public function created(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        $this->events->dispatch(new AssistantCreatedEvent($assistant));
    }

    /**
     * Gate sensitive relationship include paths on the show endpoint. The
     * framework only authorises dedicated related/relationship URLs, not the
     * ?include query parameter, so a viewer who can `view` the assistant
     * would otherwise receive privileged children inline. Delegated to
     * {@see AuthorizesSensitiveIncludesTrait::authorizeSensitiveIncludes()}.
     *
     * `is_favorite` is hydrated centrally by the schema's `loaderFor` hook
     * and needs no controller-side work on this path.
     * @param Assistant $assistant
     * @param Request $request
     * @return void
     */
    public function read(Assistant $assistant, Request $request): void
    {
        $roots = collect(explode(',', (string) $request->query('include', '')))
            ->filter()
            ->map(static fn (string $path) => explode('.', $path)[0])
            ->all();

        $this->authorizeSensitiveIncludes($assistant, $roots);
    }

    /**
     * Override for json api life cycle hook to toggle update events.
     * Details see: https://laraveljsonapi.io/5.x/routing/controllers.html#update
     * @param Assistant $assistant
     * @param AssistantRequest $request
     * @param AssistantQuery $query
     * @return void
     */
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

        // Child resources (assistant_user_prompts, assistant_setting_values,
        // assistant_attachments) own their change-events: each child endpoint
        // dispatches AssistantUpdatedEvent with its own changed key. The main
        // PATCH only carries scalar/vector fields of the Assistant itself.
        // NOTE: avatar changes are intentionally ignored (no version bump) —
        // see AssistantAvatarController.

        if ([] !== $changedKeys) {
            $this->events->dispatch(new AssistantUpdatedEvent(
                $assistant,
                $changedKeys,
            ));
        }
    }

    /**
     * Remix an assisant for another user.
     * Sensitive fields are not remixed to the new assistant.
     * @param RemixAssistantRequest $request
     * @param Route $route
     * @param StoreContract $store
     * @param Assistant $assistant
     * @return DataResponse
     */
    public function remix(
        RemixAssistantRequest $request,
        Route $route,
        StoreContract $store,
        Assistant $assistant,
    ): Responsable {
        $remixed = $this->assistantService->remix($assistant, $request->user(), $request->organizationId());

        return $this->refetchedResponse($route, $store, $remixed)->didCreate();
    }

    /**
     * Request a release stage for the assistant.
     * For public assistants this triggers a review process.
     * @param ReleaseAssistantRequest $request
     * @param Route $route
     * @param StoreContract $store
     * @param Assistant $assistant
     * @return DataResponse
     */
    public function release(
        ReleaseAssistantRequest $request,
        Route $route,
        StoreContract $store,
        Assistant $assistant,
    ): Responsable {
        $assistant = $this->assistantService->release($assistant, $request->releaseStage());

        return $this->refetchedResponse($route, $store, $assistant);
    }

    /**
     * Set favorited assistant for user.
     */
    public function addFavorite(
        AddFavoriteAssistantRequest $request,
        Route $route,
        StoreContract $store,
        Assistant $assistant,
    ): Responsable {
        $this->assistantService->setFavorite($assistant, $request->user(), isFavorite: true);

        return $this->refetchedResponse($route, $store, $assistant);
    }

    /**
     * Unset favorited assitant for user.
     */
    public function removeFavorite(
        RemoveFavoriteAssistantRequest $request,
        Route $route,
        StoreContract $store,
        Assistant $assistant,
    ): Responsable {
        $this->assistantService->setFavorite($assistant, $request->user(), isFavorite: false);

        return $this->refetchedResponse($route, $store, $assistant);
    }

    /**
     * Upload an attachment file for the assistant. The file content is extracted
     * with the fileconverter and stored permanently.
     * It is linked to the assistant via the polymorphic attachments table; its
     * extracted text content is later injected into the assistant's system
     * prompt by AssistantPromptComposer.
     */
    public function uploadAttachment(
        UploadAssistantAttachmentRequest $request,
        Route $route,
        StoreContract $store,
        Assistant $assistant,
    ): Responsable|Response {
        $this->assistantService->uploadAttachment(
            FileReference::fromUploadedFile($request->uploadedFile()),
            $assistant,
            $request->user(),
        );

        if ($this->shouldNotifyUpdate($assistant)) {
            $this->events->dispatch(new AssistantUpdatedEvent($assistant, ['attachments']));
        }

        return $this->refetchedResponse($route, $store, $assistant);
    }

    /**
     * Delete an attachment (and its underlying stored file) from the assistant.
     * @param DeleteAssistantAttachmentRequest $request
     * @param Route $route
     * @param StoreContract $store
     * @param Assistant $assistant
     * @return DataResponse
     */
    public function deleteAttachment(
        DeleteAssistantAttachmentRequest $request,
        Route $route,
        StoreContract $store,
        Assistant $assistant,
    ): Responsable|Response {
        $attachment = $assistant->attachments()->where('uuid', $request->fileId())->firstOrFail();

        $attachment->delete();

        if ($this->shouldNotifyUpdate($assistant)) {
            $this->events->dispatch(new AssistantUpdatedEvent($assistant, ['attachments']));
        }

        return $this->refetchedResponse($route, $store, $assistant);
    }

    /**
     * Re-fetch the assistant through the JSON:API store so the schema's
     * `loaderFor` hook fires (per-user `is_favorite`, include paths, etc.) and
     * the model is serialised through the same pipeline as the framework's
     * FetchOne/Store/Update actions. Avoids the `DataResponse::make($model)`
     * pattern on an un-hydrated model that bypasses every schema hook.
     * @param Route $route
     * @param StoreContract $store
     * @param Assistant $assistant
     * @return DataResponse
     */
    private function refetchedResponse(Route $route, StoreContract $store, Assistant $assistant): DataResponse
    {
        $query = ResourceQuery::queryOne($route->resourceType());

        $assistant = $store->queryOne($route->resourceType(), $assistant)
            ->withQuery($query)
            ->first();

        return DataResponse::make($assistant)->withQueryParameters($query);
    }

    /**
     * The rule that update events should be propagated to workflows for
     * assistants that have been released to the community.
     * @param Assistant $assistant
     * @return bool
     */
    private function shouldNotifyUpdate(Assistant $assistant): bool
    {
        $skipStages = [AssistantReleaseStage::DRAFT, AssistantReleaseStage::PRIVATE];

        return !\in_array($assistant->release_stage, $skipStages, true);
    }
}
