<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Value;


use App\Models\User;
use Carbon\Carbon;

readonly class SyncLogEntryConstraints
{
    public function __construct(
        public User    $user,
        public ?Carbon $lastSync = null,
        public ?int    $offset = null,
        public ?int    $limit = null,
        public ?int    $roomId = null,
    )
    {
    }
}
