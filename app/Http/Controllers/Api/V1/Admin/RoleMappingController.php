<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Repositories\RoleMappingRepository;

class RoleMappingController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;
    use DeletesResources;

    public function __construct(private RoleMappingRepository $resource)
    {
    }

    protected function repository(): RoleMappingRepository
    {
        return $this->resource;
    }
}
