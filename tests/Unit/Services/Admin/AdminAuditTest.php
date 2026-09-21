<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\AdminAudit;
use App\Services\Admin\Repositories\AdminAuditRepository;
use App\Services\System\Time\CarbonClock;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AdminAudit::class)]
class AdminAuditTest extends TestCase
{
    public function testItRecordsRedactedChangesWithExplicitContextAtTheClockTime(): void
    {
        $repository = $this->createMock(AdminAuditRepository::class);
        $repository->expects($this->once())->method('record')->with(
            'save',
            'providers',
            '17',
            42,
            '203.0.113.42',
            [
                'name' => 'Visible name',
                'api_key' => '[redacted]',
                'nested' => ['password' => '[redacted]'],
            ],
            self::callback(static fn (\DateTimeInterface $createdAt): bool => '2026-09-21 12:34:56' === $createdAt->format('Y-m-d H:i:s')),
        );
        $sut = new AdminAudit($repository, new CarbonClock(new \DateTimeImmutable('2026-09-21 12:34:56 UTC')));

        $sut->record('save', 'providers', '17', 42, '203.0.113.42', [
            'name' => 'Visible name',
            'api_key' => 'provider-secret',
            'nested' => ['password' => 'nested-secret'],
        ]);
    }
}
