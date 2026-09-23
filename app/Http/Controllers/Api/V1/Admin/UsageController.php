<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\AdminAudit;
use App\Services\Admin\Repositories\UsageRepository;

class UsageController extends ResourceController
{
    public function __construct(private UsageRepository $resource, AdminAudit $audit)
    {
        parent::__construct($audit);
    }

    protected function repository(): UsageRepository
    {
        return $this->resource;
    }
}
