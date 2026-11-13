<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\CodedError;
use App\JsonApi\V1\Sessions\SessionRequest;
use App\Services\Auth\LogoutHandler;
use App\Services\Auth\Value\Session;
use App\Services\Frontend\Connection\ConnectionFactory;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Core\Responses\MetaResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class SessionsController extends Controller
{
    use Actions\Store;
    use Actions\Destroy;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly LogoutHandler    $logout,
    ) {
    }

    public function created(Session $session, ResourceRequest $request, ResourceQuery $query): DataResponse
    {
        return DataResponse::make($this->connections->createHawkiConnection())
            ->withMeta(['next' => $session->loginResult?->nextStep->value]);
    }

    public function deleting(Session $session, ResourceRequest $request): MetaResponse
    {
        if (!$request->hasSession()) {
            CodedError::abort('auth_session_required', 403, 'A browser session is required');
        }

        return MetaResponse::make(['redirect_url' => $this->logout->handle($request)]);
    }
}
