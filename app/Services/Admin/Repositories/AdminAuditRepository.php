<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use Illuminate\Database\ConnectionInterface;

class AdminAuditRepository
{
    public function __construct(private readonly ConnectionInterface $database)
    {
    }

    public function record(
        string $action,
        string $resourceType,
        null|int|string $resourceId,
        ?int $actorId,
        ?string $ip,
        array $changes,
        \DateTimeInterface $createdAt,
    ): void {
        $this->database->table('admin_audit_log')->insert([
            'user_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'changes' => json_encode($changes, \JSON_THROW_ON_ERROR),
            'ip' => $ip,
            'created_at' => $createdAt,
        ]);
    }
}
