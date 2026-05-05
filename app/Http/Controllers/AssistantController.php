<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Assistant\StoreAssistantRequest;
use App\Http\Requests\Assistant\UpdateAssistantRequest;
use App\Http\Resources\Assistant\AssistantResource;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
        $this->authorizeResource(Assistant::class, 'assistant');
    }

    /**
     * Returns optional model relations from request query.
     * The model relations are defined in the model and migration files.
     * The map uses the default json representation created by laravel for the model for the model fields.
     */
    private function getOptionalModelRelations(): array
    {
        $with = request()->query('with', '');

        $jsonToModelRelationMap = [
            'user_prompts' => 'userPrompts',
            'ai_tools' => 'aiTools',
            'tags' => 'tags',
            'creator' => 'creator',
            'remix_creator' => 'remix_creator',
            'original_assistent' => 'originalAssistent',
            'copies' => 'copies',
            'versions' => 'versions',
            'attachments' => 'attachments'
        ];

        $requested = explode(',', $with);

        return array_values(array_intersect_key($jsonToModelRelationMap, array_flip($requested)));
    }

    public function index(): AnonymousResourceCollection
    {
        $relations = $this->getOptionalModelRelations();
        $filters = request()->only(['category']);
        $assistants = $this->assistantService->list($relations, $filters);

        return AssistantResource::collection($assistants);
    }

    public function store(StoreAssistantRequest $request): AssistantResource
    {
        $assistant = $this->assistantService->create(
            $request->validated(),
            $request->user()->id,
        );

        return new AssistantResource($assistant);
    }

    public function show(Assistant $assistant): AssistantResource
    {
        $relations = $this->getOptionalModelRelations();
        $assistant = $this->assistantService->find($assistant, $relations);

        return new AssistantResource($assistant);
    }

    public function update(UpdateAssistantRequest $request, Assistant $assistant): AssistantResource
    {
        $assistant = $this->assistantService->update(
            $assistant,
            $request->validated(),
        );

        return new AssistantResource($assistant);
    }

    public function destroy(Assistant $assistant): Response
    {
        $assistant -> delete();
        return response()->noContent();
    }

    public function remix(Assistant $assistant): AssistantResource
    {   
        // TODO: HKI-73: Use remix rules
        $this->authorize('remix', $assistant);

        $remixed = $this->assistantService->remix(
            $assistant,
            request()->user()->id,
        );

        return new AssistantResource($remixed);
    }
}
