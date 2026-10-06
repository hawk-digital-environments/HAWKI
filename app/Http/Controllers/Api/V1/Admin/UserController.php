<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\JsonApi\V1\Admin\Record;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ResourceController
{
    use CreatesResources;
    use UpdatesResources;

    public function __construct(private UserRepository $resource, AdminAudit $audit)
    {
        parent::__construct($audit);
    }

    public function revokeTokens(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'revoke-tokens', $id, fn () => $this->resource->revokeTokens($request->user(), $id));
    }

    public function tokens(Request $request, Record $record): JsonResponse
    {
        $id = $record->id();

        return $this->action($request, 'tokens', $id, fn () => $this->resource->tokens($request->user(), $id));
    }

    protected function repository(): UserRepository
    {
        return $this->resource;
    }
}
