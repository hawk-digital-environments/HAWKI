<?php
declare(strict_types=1);

namespace App\Services\Auth\Value;

/**
 * The session resource behind JSON:API `sessions` — the lifecycle resource for logging in
 * and out. Only ever exists as the singleton `hawki`; never persisted.
 */
readonly class Session
{
    public function __construct(
        public string       $id,
        public ?LoginResult $loginResult = null,
    )
    {
    }
}
