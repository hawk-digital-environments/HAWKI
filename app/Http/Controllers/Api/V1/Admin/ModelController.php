<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\JsonApi\V1\Admin\Record;
use App\Services\Admin\Repositories\ModelRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModelController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;
    use DeletesResources;

    public function __construct(private ModelRepository $resource)
    {
    }

    public function refresh(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'refresh', $id, fn () => $this->resource->refreshModel($id));
    }

    protected function repository(): ModelRepository
    {
        return $this->resource;
    }
}
