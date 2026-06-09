<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AiModelController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
}
