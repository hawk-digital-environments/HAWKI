<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Repositories\HealthRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends ResourceController
{
    public function __construct(private HealthRepository $resource)
    {
    }

    public function checkAiStatus(Request $request): JsonResponse
    {
        return $this->action($request, 'check-ai-status', null, fn () => $this->resource->healthAction($request->user(), 'ai:check-status'));
    }

    public function retryJob(Request $request, string $id): JsonResponse
    {
        return $this->action($request, 'retry-job', $id, fn () => $this->resource->healthAction($request->user(), 'queue:retry', $id));
    }

    public function flushJobs(Request $request): JsonResponse
    {
        return $this->action($request, 'flush-jobs', null, fn () => $this->resource->healthAction($request->user(), 'queue:flush'));
    }

    protected function repository(): HealthRepository
    {
        return $this->resource;
    }
}
