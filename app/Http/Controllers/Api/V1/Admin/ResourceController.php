<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
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
        $data = $request->validate(['filter' => 'sometimes|array']);

        return response()->json(['content' => $repository->read($request->user(), $data['filter'] ?? [])]);
    }

    abstract protected function repository(): ResourceRepository;

    protected function mutate(Request $request, string $action, ?string $id, callable $operation, bool $checkVersion = true): mixed
    {
        $repository = $this->repository();
        $repository->authorize($request->user());
        $data = $request->validate(['version' => 'nullable|string', 'values' => 'sometimes|array', 'section' => 'prohibited', 'id' => 'prohibited']);

        return DB::transaction(static function () use ($request, $repository, $action, $id, $operation, $checkVersion, $data) {
            // Serialize configuration, policy publication and role changes, including creates.
            DB::table('roles')->where('slug', 'admin')->lockForUpdate()->first();
            $repository->authorize($request->user());

            if (null !== $id && $checkVersion) {
                $repository->checkVersion($id, $data['version'] ?? null);
            }

            $result = $operation();
            app(AdminAudit::class)->record($action, $repository::RESOURCE, $id ?? $result, $data['values'] ?? []);

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
