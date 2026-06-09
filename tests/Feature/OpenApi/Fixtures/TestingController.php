<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class TestingController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return JsonResource::collection(collect());
    }

    public function store(Request $request): JsonResource
    {
        return new JsonResource([]);
    }

    public function show(Request $request, string $id): JsonResource
    {
        return new JsonResource([]);
    }

    public function update(Request $request, string $id): JsonResource
    {
        return new JsonResource([]);
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(null, 204);
    }
}
