<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Values;

use App\Policies\MigrationPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

/**
 * Represents a single frontend migration that has not yet been applied for the current user.
 *
 * Delivered to the frontend via the JSON:API response so the JS migration runner knows
 * which migrations to fetch and execute. The optional `$data` array contains the
 * per-user context that was collected server-side at registration time (already decrypted).
 */
#[UsePolicy(MigrationPolicy::class)]
class MigrationToApply
{
    public function __construct(
        /** Filename-based migration name used by the frontend runner to locate the TS module. */
        public string $name,
        /** Optional context array collected during migration registration, or `null` if none was provided. */
        public ?array $data
    )
    {
    }
}
