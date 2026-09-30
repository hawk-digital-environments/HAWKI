<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\AdminAudit;
use App\Services\Admin\Repositories\EnvironmentRepository;

class EnvironmentController extends ResourceController
{
    public function __construct(private EnvironmentRepository $resource, AdminAudit $audit)
    {
        parent::__construct($audit);
    }

    protected function repository(): EnvironmentRepository
    {
        return $this->resource;
    }
}
