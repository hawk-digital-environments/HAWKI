<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\AdminAudit;
use App\Services\Admin\Repositories\SystemModelRepository;

class SystemModelController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;
    use DeletesResources;

    public function __construct(private SystemModelRepository $resource, AdminAudit $audit)
    {
        parent::__construct($audit);
    }

    protected function repository(): SystemModelRepository
    {
        return $this->resource;
    }
}
