<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\ResourceRepository;
use Illuminate\Support\Facades\Auth;
use LaravelJsonApi\NonEloquent\AbstractRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class Repository extends AbstractRepository
{
    /**
     * @param class-string<Record> $recordClass
     */
    public function __construct(
        private readonly ResourceRepository $repository,
        private readonly string $recordClass,
    ) {
    }

    public function find(string $resourceId): ?object
    {
        $user = Auth::user();
        abort_unless(null !== $user, 401);
        $this->repository->authorize($user);

        try {
            $row = $this->repository->readOne($user, $resourceId);
        } catch (NotFoundHttpException) {
            return null;
        }

        return new $this->recordClass($row);
    }
}
