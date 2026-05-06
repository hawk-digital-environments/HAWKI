<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Config\Repository;

trait ApiTrait
{
    private function pageSize(): int
    {
        return (int) request()->input('per_page', $this->config->get('app.api_page_size'));
    }
}
