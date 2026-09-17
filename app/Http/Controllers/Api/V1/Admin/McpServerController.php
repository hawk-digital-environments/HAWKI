<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\JsonApi\V1\Admin\Record;
use App\Services\Admin\Repositories\McpServerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpServerController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;
    use DeletesResources;

    public function __construct(private McpServerRepository $resource)
    {
    }

    public function test(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'test', $id, fn () => $this->resource->mcp($id, false));
    }

    public function discover(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'discover', $id, fn () => $this->resource->mcp($id, true));
    }

    protected function repository(): McpServerRepository
    {
        return $this->resource;
    }
}
