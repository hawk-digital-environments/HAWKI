<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Sessions;

use App\JsonApi\V1\Sessions\Capabilities\CrudSession;
use App\Services\Auth\Value\Session;
use LaravelJsonApi\Contracts\Store\CreatesResources;
use LaravelJsonApi\NonEloquent\AbstractRepository;
use LaravelJsonApi\NonEloquent\Concerns\HasCrudCapability;
use App\Services\System\Values\HawkiInstanceId;

/**
 * Repository of the `sessions` JSON:API resource. Creates run the login through the
 * {@see CrudSession} capability; the singleton `hawki` exists purely so the destroy
 * action can address it.
 */
class SessionRepository extends AbstractRepository implements CreatesResources
{
    use HasCrudCapability;

    public function find(string $resourceId): ?object
    {
        if ($resourceId !== HawkiInstanceId::HAWKI) {
            return null;
        }

        return new Session(HawkiInstanceId::HAWKI);
    }

    protected function crud(): CrudSession
    {
        return CrudSession::make();
    }
}
