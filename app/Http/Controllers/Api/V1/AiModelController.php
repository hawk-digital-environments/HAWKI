<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AiModelController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
}
