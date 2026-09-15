<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\JsonApi\V1\Admin\Record;
use App\Services\Admin\Repositories\SettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SettingController extends ResourceController
{
    public function __construct(private SettingRepository $resource)
    {
    }

    public function update(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();
        $values = $this->attributes($request, $id);
        $request->validate(['data.attributes' => 'required|array:value']);
        $this->mutate($request, 'save', $id, fn () => $this->resource->update($id, $values, $request->user()), false, $values);

        return $this->resourceResponse($request, $id);
    }

    public function destroy(Request $request, Record $record): Response
    {
        $id = $record->id();
        $this->mutate($request, 'delete', $id, fn () => $this->resource->reset($id), false);

        return response()->noContent();
    }

    protected function repository(): SettingRepository
    {
        return $this->resource;
    }
}
