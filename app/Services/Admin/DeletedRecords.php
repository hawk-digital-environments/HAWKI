<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

/**
 * Remembers configuration records deleted in Administration.
 *
 * Providers, models, system model slots and MCP servers are seeded from the deployment
 * files. Deleting such a record removes its `admin_managed` marker with it, so an import
 * would treat the file entry as new and recreate the record. The deletion is therefore
 * recorded under the key the files identify the record by, and the syncers skip those
 * entries until an administrator creates the record again.
 */
class DeletedRecords
{
    public const TABLE = 'admin_deleted_records';

    public function record(string $resource, string $identity, ?int $userId = null): void
    {
        DB::table(self::TABLE)->upsert(
            ['resource' => $resource, 'identity' => $identity, 'identity_hash' => hash('sha256', $identity), 'deleted_by' => $userId, 'created_at' => now()],
            ['resource', 'identity_hash'],
            ['identity', 'deleted_by', 'created_at']
        );
    }

    public function forget(string $resource, string $identity): void
    {
        $this->query($resource, $identity)->delete();
    }

    public function isDeleted(string $resource, string $identity): bool
    {
        return $this->query($resource, $identity)->exists();
    }

    private function query(string $resource, string $identity): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::TABLE)->where('resource', $resource)->where('identity_hash', hash('sha256', $identity));
    }

    /** System model slots have no single column key; usage type and model type identify them together. */
    public static function systemModelIdentity(string $usageType, string $modelType): string
    {
        return $usageType . ':' . $modelType;
    }
}
