<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class ConnectionController extends Controller
{
    use Actions\FetchOne;
}
