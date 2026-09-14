<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Repositories\SettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SettingController extends ResourceController
{
    public function __construct(private SettingRepository $resource)
    {
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->resource->authorize($request->user());
        $data = $request->validate(['values' => 'required|array:value']);
        $this->mutate($request, 'save', $id, fn () => $this->resource->update($id, $data['values'], $request->user()), false);

        return response()->json(['id' => $id]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->mutate($request, 'delete', $id, fn () => $this->resource->reset($id), false);

        return response()->noContent();
    }

    protected function repository(): SettingRepository
    {
        return $this->resource;
    }
}
