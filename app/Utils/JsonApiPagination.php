<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Http\Request;

class JsonApiPagination
{
    public static function pageName(): string
    {
        return 'page[number]';
    }

    public static function pageNumber(): int
    {
        return (int) request()->input('page.number', 1);
    }

    public static function pageSize(): int
    {
        return (int) request()->input(
            'page.size',
            config('app.api_page_size')
        );
    }
}
