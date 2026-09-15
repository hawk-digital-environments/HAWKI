<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminCollection;
use App\Http\Resources\AdminResource;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Repositories\ResourceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class ResourceController extends Controller
{
    final public function index(Request $request): JsonResponse
    {
        $repository = $this->repository();
        $repository->authorize($request->user());
        $data = $request->validate([
            'filter' => 'sometimes|array',
            'page' => 'sometimes|array:number,size',
            'page.number' => 'sometimes|integer|min:1',
            'page.size' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|regex:/^-?[a-zA-Z_][a-zA-Z0-9_]*$/',
        ]);
        $filters = $data['filter'] ?? [];
        // Pagination and sorting belong to their JSON:API query parameters.
        unset($filters['page'], $filters['size'], $filters['sort'], $filters['direction']);
        $filters['page'] = $data['page']['number'] ?? 1;
        $filters['size'] = $data['page']['size'] ?? 25;
        // The resources expose the `type` column as `kind`; sort and value filters arrive under that name.
        if (\is_array($filters['where'] ?? null) && \array_key_exists('kind', $filters['where'])) {
            $filters['where']['type'] = $filters['where']['kind'];
            unset($filters['where']['kind']);
        }
        if (isset($data['sort'])) {
            $filters['sort'] = ltrim($data['sort'], '-');
            if ('kind' === $filters['sort']) {
                $filters['sort'] = 'type';
            }
            $filters['direction'] = str_starts_with($data['sort'], '-') ? 'desc' : 'asc';
        }

        return (new AdminCollection($repository::RESOURCE, $repository->read($request->user(), $filters)))
            ->toResponse($request);
    }

    abstract protected function repository(): ResourceRepository;

    /** Validate the resource document before passing its attributes to the section repository. */
    protected function attributes(Request $request, ?string $id = null): array
    {
        $this->repository()->authorize($request->user());
        abort_unless('application/vnd.api+json' === $request->header('Content-Type'), 415);
        $data = $request->validate([
            'data' => 'required|array:type,id,attributes',
            'data.type' => 'required|string',
            'data.id' => null === $id ? 'prohibited' : 'required|string',
            'data.attributes' => 'present|array',
            'data.attributes.id' => 'prohibited',
            'data.attributes.type' => 'prohibited',
            'section' => 'prohibited', 'id' => 'prohibited', 'values' => 'prohibited', 'version' => 'prohibited',
        ])['data'];
        abort_unless('admin-' . $this->repository()::RESOURCE === $data['type'], 409);
        abort_if(null !== $id && $data['id'] !== $id, 409);
        $values = $data['attributes'];
        if (array_key_exists('kind', $values)) {
            $values['type'] = $values['kind'];
            unset($values['kind']);
        }

        return $values;
    }

    protected function resourceResponse(Request $request, string $id, int $status = 200): JsonResponse
    {
        $repository = $this->repository();
        $row = $repository->readOne($request->user(), $id);
        $headers = ['Content-Type' => 'application/vnd.api+json'];
        if (isset($row['_version'])) {
            $headers['ETag'] = '"' . $row['_version'] . '"';
        }

        return response()->json(['data' => new AdminResource($repository::RESOURCE, $row)], $status, $headers);
    }

    protected function mutate(Request $request, string $action, ?string $id, callable $operation, bool $checkVersion = true, array $values = []): mixed
    {
        $repository = $this->repository();
        $repository->authorize($request->user());
        $request->validate(['section' => 'prohibited', 'id' => 'prohibited', 'values' => 'prohibited', 'version' => 'prohibited']);

        return DB::transaction(static function () use ($request, $repository, $action, $id, $operation, $checkVersion, $values) {
            // Serialize configuration, policy publication and role changes, including creates.
            DB::table('roles')->where('name', 'admin')->lockForUpdate()->first();
            $repository->authorize($request->user());

            if (null !== $id && $checkVersion) {
                $etag = $request->header('If-Match', '');
                $version = preg_match('/^"([a-f0-9]{64})"$/D', $etag, $matches) ? $matches[1] : null;
                $repository->checkVersion($id, $version);
            }

            $result = $operation();
            app(AdminAudit::class)->record($action, $repository::RESOURCE, $id ?? $result, $values);

            return $result;
        }, 3);
    }

    protected function action(Request $request, string $action, ?string $id, callable $operation): JsonResponse
    {
        $repository = $this->repository();
        $repository->authorize($request->user());
        $request->validate(['section' => 'prohibited', 'action' => 'prohibited', 'id' => 'prohibited']);
        $result = $operation();
        app(AdminAudit::class)->record($action, $repository::RESOURCE, $id);

        return response()->json($result);
    }
}
