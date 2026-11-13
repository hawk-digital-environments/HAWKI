<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\JsonApi\V1\Registrations\RegistrationRequest;
use App\Services\Auth\Value\Registration;
use App\Services\Frontend\Connection\ConnectionFactory;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class RegistrationsController extends Controller
{
    use Actions\FetchOne;
    use Actions\Store;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    public function created(Registration $registration, ResourceRequest $request, ResourceQuery $query): DataResponse
    {
        return DataResponse::make($this->connections->createHawkiConnection())
            ->withMeta(['next' => 'handshake']);
    }
}
