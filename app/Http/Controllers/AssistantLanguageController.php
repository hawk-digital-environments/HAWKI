<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantLanguageController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
}
