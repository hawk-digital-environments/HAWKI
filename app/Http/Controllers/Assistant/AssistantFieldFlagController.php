<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantFieldFlagController extends Controller
{
    use Actions\Destroy;
    use Actions\FetchMany;
    use Actions\Store;
    use Actions\Update;
}
