<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\JsonApi\V1\Assistants\AssistantQuery;
use App\JsonApi\V1\Assistants\AssistantRequest;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use App\JsonApi\V1\Assistants\ReleaseAssistantRequest;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;

    private const RELATIONS = ['language', 'category', 'tags', 'ai_tools', 'user_prompts'];

    private function extractServiceDataForUpdateOrCreate(AssistantRequest $request): array
    {
        $validated = $request->validated();

        $data = collect($validated)
            ->except(['type', 'id', ...self::RELATIONS])
            ->all();

        if (isset($validated['language'])) {
            $data['language_id'] = (int) $validated['language']['id'];
        }
        if (isset($validated['category'])) {
            $data['category_id'] = (int) $validated['category']['id'];
        }
        if (isset($validated['tags'])) {
            $data['tag_ids'] = collect($validated['tags'])->map(fn ($item) => (int) $item['id'])->all();
        }
        if (isset($validated['ai_tools'])) {
            $data['ai_tool_ids'] = collect($validated['ai_tools'])->map(fn ($item) => (int) $item['id'])->all();
        }
        if (isset($validated['user_prompts'])) {
            $data['user_prompt_ids'] = collect($validated['user_prompts'])->map(fn ($item) => (int) $item['id'])->all();
        }

        return $data;
    }

    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
        $this->authorizeResource(Assistant::class, 'assistant');
    }

    public function store(AssistantRequest $request, AssistantSchema $schema, AssistantQuery $query): Responsable
    {
        $this->authorize('create', Assistant::class);

        $data = $this->extractServiceDataForUpdateOrCreate($request);

        $assistant = $this->assistantService->create($data, $request->user());

        return DataResponse::make($assistant)
            ->withQueryParameters($query)
            ->didCreate();
    }

    public function update(AssistantRequest $request, AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('update', $assistant);

        $data = $this->extractServiceDataForUpdateOrCreate($request);

        $assistant = $this->assistantService->update($assistant, $data);

        return DataResponse::make($assistant)
            ->withQueryParameters($query);
    }

    public function destroy(Assistant $assistant): Response
    {
        $this->authorize('delete', $assistant);

        $assistant->delete();

        return response()->noContent();
    }

    public function remix(AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('remix', $assistant);

        $remixed = $this->assistantService->remix($assistant, request()->user());

        return DataResponse::make($remixed)
            ->withQueryParameters($query)
            ->didCreate();
    }

    public function release(ReleaseAssistantRequest $request, AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('release', $assistant);

        $releaseStage = ReleaseStage::from($request->input('data.attributes.release_stage'));

        $assistant = $this->assistantService->release($assistant, $releaseStage);

        return DataResponse::make($assistant)
            ->withQueryParameters($query);
    }
}
