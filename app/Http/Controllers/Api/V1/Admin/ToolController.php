<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Repositories\ToolRepository;

class ToolController extends ResourceController
{
    use UpdatesResources;

    public function __construct(private ToolRepository $resource)
    {
    }

    protected function repository(): ToolRepository
    {
        return $this->resource;
    }
}
