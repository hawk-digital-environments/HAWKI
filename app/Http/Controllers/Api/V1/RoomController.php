<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class RoomController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
}
