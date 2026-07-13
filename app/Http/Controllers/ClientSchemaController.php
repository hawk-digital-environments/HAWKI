<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ClientSchema\ClientSchemaGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSchemaController extends Controller
{
    public function __construct(private readonly ClientSchemaGenerator $generator)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $schema = $this->generator->generate($request->user());

        return response()->json($schema);
    }
}
