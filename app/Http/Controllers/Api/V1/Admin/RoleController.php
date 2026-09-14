<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Repositories\RoleRepository;

class RoleController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;
    use DeletesResources;

    public function __construct(private RoleRepository $resource)
    {
    }

    protected function repository(): RoleRepository
    {
        return $this->resource;
    }
}
