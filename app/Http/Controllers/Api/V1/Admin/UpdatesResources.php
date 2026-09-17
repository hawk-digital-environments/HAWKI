<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\JsonApi\V1\Admin\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait UpdatesResources
{
    public function update(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();
        $values = $this->attributes($request, $id);
        $this->mutate($request, 'save', $id, fn () => $this->repository()->save((int) $id, $values, $request->user()), values: $values);

        return $this->resourceResponse($request, $id);
    }
}
