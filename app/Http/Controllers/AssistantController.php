<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Assistant\ReleaseAssistantRequest;
use App\Http\Requests\Assistant\StoreAssistantRequest;
use App\Http\Requests\Assistant\UpdateAssistantRequest;
use App\Http\Resources\Assistant\AssistantResource;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AssistantController extends Controller
{
    use ApiTrait;

    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
        $this->authorizeResource(Assistant::class, 'assistant');
    }

    public function index(): AnonymousResourceCollection
    {
        $assistants = $this->assistantService->list(
            request()->user(),
            $this->pageSize(),
        );

        return AssistantResource::collection($assistants);
    }

    public function store(StoreAssistantRequest $request): AssistantResource
    {
        $assistant = $this->assistantService->create(
            $request->validated(),
            $request->user(),
        );

        return (new AssistantResource($assistant))
            ->includePreviouslyLoadedRelationships();
    }

    public function show(Assistant $assistant): AssistantResource
    {
        return new AssistantResource($assistant);
    }

    public function update(UpdateAssistantRequest $request, Assistant $assistant): AssistantResource
    {
        $assistant = $this->assistantService->update(
            $assistant,
            $request->validated(),
        );

        return (new AssistantResource($assistant))
            ->includePreviouslyLoadedRelationships();
    }

    public function destroy(Assistant $assistant): Response
    {
        $assistant->delete();
        return response()->noContent();
    }

    public function remix(Assistant $assistant): AssistantResource
    {
        $this->authorize('remix', $assistant);

        $remixed = $this->assistantService->remix(
            $assistant,
            request()->user(),
        );

        return (new AssistantResource($remixed))
            ->includePreviouslyLoadedRelationships();
    }

    public function release(ReleaseAssistantRequest $request, Assistant $assistant): AssistantResource
    {
        $this->authorize('release', $assistant);

        $assistant = $this->assistantService->release(
            $assistant,
            ReleaseStage::from($request->validated()['release_stage']),
        );

        return new AssistantResource($assistant);
    }
}
