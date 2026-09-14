<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait UpdatesResources
{
    public function update(Request $request, string $id): JsonResponse
    {
        $this->repository()->authorize($request->user());
        $data = $request->validate(['values' => 'required|array']);
        $this->mutate($request, 'save', $id, fn () => $this->repository()->save((int) $id, $data['values'], $request->user()));

        return response()->json(['id' => $id]);
    }
}
