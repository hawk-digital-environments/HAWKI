<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CreatesResources
{
    public function store(Request $request): JsonResponse
    {
        $this->repository()->authorize($request->user());
        $data = $request->validate(['values' => 'required|array']);
        $id = $this->mutate($request, 'save', null, fn () => $this->repository()->save(null, $data['values'], $request->user()));

        return response()->json(['id' => (string) $id], 201);
    }
}
