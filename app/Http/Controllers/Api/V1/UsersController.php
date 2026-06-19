<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class UsersController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;

    #[Authorize('view', User::class)]
    public function handleMe(
        #[CurrentUser]
        ?User $user
    ): Responsable
    {
        if (!$user) {
            abort(401, 'Can only fetch the current user for authenticated users');
        }

        return new DataResponse($user);
    }
}
