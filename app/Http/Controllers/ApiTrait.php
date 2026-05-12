<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Utils\JsonApiPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait ApiTrait
{
    private function pageSize(): int
    {
        return JsonApiPagination::pageSize();
    }

    private function applyPagination(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $queryParams = request()->except('page');
        $queryParams['page']['size'] = $this->pageSize();

        return $paginator->appends($queryParams);
    }
}