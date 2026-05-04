<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Assistant\StoreAssistantRequest;
use App\Http\Requests\Assistant\UpdateAssistantRequest;
use App\Http\Resources\Assistant\AssistantResource;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
        $this->authorizeResource(Assistant::class, 'assistant');
    }

    private function parseRelations(): array
    {
        $with = request()->query('with', '');

        $map = [
            'user_prompts' => 'userPrompts',
            'ai_tools' => 'aiTools',
            'tags' => 'tags',
            'creator' => 'creator',
            'versions' => 'versions',
        ];

        $requested = explode(',', $with);

        return array_values(array_intersect_key($map, array_flip($requested)));
    }

    public function index(): AnonymousResourceCollection
    {
        $relations = $this->parseRelations();
        $assistants = $this->assistantService->list($relations);

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
        $relations = $this->parseRelations();
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

    public function destroy(Assistant $assistant): \Illuminate\Http\Response
    {
        $assistant -> delete();
        return response()->noContent();
    }

    public function remix(Assistant $assistant): AssistantResource
    {
        $this->authorize('remix', $assistant);

        $remixed = $this->assistantService->remix(
            $assistant,
            request()->user()->id,
        );

        return new AssistantResource($remixed);
    }
}
