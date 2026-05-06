<?php

declare(strict_types=1);

namespace App\Http\Controllers;

trait ApiTrait
{
    private function pageSize(): int
    {
        return (int) request()->input(
            'per_page',
            config('app.api_page_size')
        );
    }
}