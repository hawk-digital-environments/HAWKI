<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\AdminAudit;
use App\Services\Admin\Repositories\AnnouncementRepository;

class AnnouncementController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;
    use DeletesResources;

    public function __construct(private AnnouncementRepository $resource, AdminAudit $audit)
    {
        parent::__construct($audit);
    }

    protected function repository(): AnnouncementRepository
    {
        return $this->resource;
    }
}
