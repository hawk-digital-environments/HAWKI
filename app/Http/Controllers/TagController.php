<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class TagController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\Store;
    use Actions\Destroy;
}
