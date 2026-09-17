<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\JsonApi\V1\Admin\Record;
use App\Services\Admin\ProviderIconService;
use App\Services\Admin\Repositories\ProviderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;
    use DeletesResources;

    public function __construct(
        private ProviderRepository $resource,
        private ProviderIconService $providerIcons,
    ) {
    }

    public function test(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'test', $id, fn () => $this->resource->provider($id));
    }

    public function discover(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'discover', $id, fn () => $this->resource->provider($id, onlyNew: true));
    }

    public function inspect(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'inspect', $id, fn () => $this->resource->inspect($id, $request->validate(['model_id' => 'required|string|max:255'])['model_id']));
    }

    public function import(Request $request): JsonResponse
    {
        return $this->action($request, 'import', null, fn () => $this->resource->import($request->user()));
    }

    /**
     * Shows AI icons initially and searches the entire svgl catalogue when a term is supplied.
     */
    public function icons(Request $request, ProviderIconService $icons): JsonResponse
    {
        $this->resource->authorize($request->user());
        $data = $request->validate(['filter' => 'sometimes|array', 'filter.search' => 'nullable|string|max:100']);

        return response()->json(['icons' => $icons->search($data['filter']['search'] ?? '')]);
    }

    /**
     * Validates an upload; the editor persists its SVG when the provider is saved.
     */
    public function uploadIcon(Request $request, ProviderIconService $icons): JsonResponse
    {
        $this->resource->authorize($request->user());
        $data = $request->validate(['image' => $icons->uploadRules()]);

        return response()->json(['icon' => $icons->upload($data['image'])]);
    }

    protected function repository(): ProviderRepository
    {
        return $this->resource;
    }

    protected function attributes(Request $request, ?string $id = null): array
    {
        $values = parent::attributes($request, $id);

        if (\array_key_exists('icon', $values)) {
            $request->validate(['data.attributes.icon' => 'nullable|array']);
            $values['icon'] = $this->resource->resolveIcon($values['icon'], $id, $this->providerIcons);
        }

        return $values;
    }
}
