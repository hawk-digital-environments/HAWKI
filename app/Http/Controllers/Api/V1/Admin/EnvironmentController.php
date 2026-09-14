<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Repositories\EnvironmentRepository;

class EnvironmentController extends ResourceController
{
    public function __construct(private EnvironmentRepository $resource)
    {
    }

    protected function repository(): EnvironmentRepository
    {
        return $this->resource;
    }
}
