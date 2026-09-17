<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Repositories\AssistantRepository;

class AssistantController extends ResourceController
{
    public function __construct(private AssistantRepository $resource)
    {
    }

    protected function repository(): AssistantRepository
    {
        return $this->resource;
    }
}
