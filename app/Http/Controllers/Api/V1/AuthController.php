<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AuthController extends Controller
{
    use Actions\FetchOne;
}
