<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Admin\Repositories\AdminAuditRepository;
use App\Services\System\Time\CarbonClockInterface;

class AdminAudit
{
    public function __construct(
        private readonly AdminAuditRepository $repository,
        private readonly CarbonClockInterface $clock,
    ) {
    }

    public function record(
        string $action,
        string $type,
        null|int|string $id,
        ?int $actorId,
        ?string $ip,
        array $changes = [],
    ): void {
        $this->repository->record(
            $action,
            $type,
            $id,
            $actorId,
            $ip,
            $this->redact($changes),
            $this->clock->now(),
        );
    }

    private function redact(array $changes): array
    {
        foreach ($changes as $key => $value) {
            if (preg_match('/key|secret|password|token|additional_config|content|prompt|description/i', (string) $key)) {
                $changes[$key] = '[redacted]';
            } elseif (\is_array($value)) {
                $changes[$key] = $this->redact($value);
            }
        }

        return $changes;
    }
}
