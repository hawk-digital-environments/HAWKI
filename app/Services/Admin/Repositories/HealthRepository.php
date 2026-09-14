<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\HealthMonitor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class HealthRepository extends ResourceRepository
{
    public const RESOURCE = 'health';

    public function healthAction(User $actor, string $command, ?string $id = null): array
    {
        app(\App\Services\Admin\PermissionService::class)->authorize($actor, \App\Services\Admin\Permission::HEALTH_MANAGE);

        if ('ai:check-status' === $command) {
            Artisan::queue($command);
        } else {
            if ('queue:retry' === $command) {
                abort_unless($id && DB::table('failed_jobs')->where('uuid', $id)->exists(), 404);
            }

            Artisan::call($command, $id ? ['id' => [$id]] : []);
        }

        return ['queued' => true];
    }

    protected function readContent(User $user, array $filters): array
    {
        return app(HealthMonitor::class)->read() + ['columns' => ['name', 'status', 'message', 'response_time']];
    }
}
