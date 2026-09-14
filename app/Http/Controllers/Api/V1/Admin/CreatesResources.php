<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CreatesResources
{
    public function store(Request $request): JsonResponse
    {
        $values = $this->attributes($request);
        $id = $this->mutate($request, 'save', null, fn () => $this->repository()->save(null, $values, $request->user()), values: $values);

        return $this->resourceResponse($request, (string) $id, 201);
    }
}
