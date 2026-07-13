<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OpenApi\OpenApiGenerator;
use Illuminate\Http\JsonResponse;

class OpenApiSpecController extends Controller
{
    public function __construct(private readonly OpenApiGenerator $generator)
    {
    }

    public function __invoke(): JsonResponse
    {
        return response()->json($this->generator->generate(true));
    }
}
