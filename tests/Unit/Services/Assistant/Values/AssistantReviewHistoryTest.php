<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\Values;

use App\Services\Assistant\Values\AssistantReviewDecisionEntry;
use App\Services\Assistant\Values\AssistantReviewHistory;
use App\Services\Assistant\Values\AssistantReviewStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantReviewHistory::class)]
#[CoversClass(AssistantReviewDecisionEntry::class)]
class AssistantReviewHistoryTest extends TestCase
{
    public function testItRoundTripsRowsOldestFirst(): void
    {
        $history = AssistantReviewHistory::fromRows([
            ['status' => 'needs_revision', 'reason' => 'Tighten the greeting.', 'reviewed_at' => '2026-11-01T10:00:00+01:00'],
            ['status' => 'denied', 'reason' => null, 'reviewed_at' => '2026-11-05T12:30:00+01:00'],
        ]);

        self::assertSame('needs_revision', $history->entries()[0]->status->value);
        self::assertSame('Tighten the greeting.', $history->entries()[0]->reason);
        self::assertSame('denied', $history->latest()?->status->value);

        self::assertSame([
            ['status' => 'needs_revision', 'reason' => 'Tighten the greeting.', 'reviewed_at' => '2026-11-01T10:00:00+01:00'],
            ['status' => 'denied', 'reason' => null, 'reviewed_at' => '2026-11-05T12:30:00+01:00'],
        ], $history->toRows());
    }

    public function testItTreatsNullRowsAsEmpty(): void
    {
        $history = AssistantReviewHistory::fromRows(null);

        self::assertSame([], $history->entries());
        self::assertNull($history->latest());
        self::assertSame([], $history->toRows());
    }

    public function testItSkipsMalformedRows(): void
    {
        $history = AssistantReviewHistory::fromRows([
            'nope',
            ['status' => 'unknown_status', 'reason' => null, 'reviewed_at' => '2026-11-01T10:00:00+01:00'],
            ['status' => 'approved', 'reason' => null, 'reviewed_at' => 'not-a-date'],
            ['reason' => 'missing status'],
            ['status' => 'approved', 'reason' => null, 'reviewed_at' => '2026-11-01T10:00:00+01:00'],
        ]);

        self::assertCount(1, $history->entries());
        self::assertSame('approved', $history->latest()?->status->value);
    }

    public function testItAppendsImmutably(): void
    {
        $original = AssistantReviewHistory::fromRows([
            ['status' => 'needs_revision', 'reason' => 'First note.', 'reviewed_at' => '2026-11-01T10:00:00+01:00'],
        ]);

        $appended = $original->appended(
            AssistantReviewDecisionEntry::from(AssistantReviewStatus::APPROVED, null, new \DateTimeImmutable('2026-11-05T12:30:00+01:00')),
        );

        self::assertCount(1, $original->entries(), 'appended() must not mutate the original');
        self::assertCount(2, $appended->entries());
        self::assertSame('approved', $appended->latest()?->status->value);
        self::assertNull($appended->latest()?->reason);
    }

    public function testItSerializesDatesAsIso8601(): void
    {
        $history = AssistantReviewHistory::empty()->appended(
            AssistantReviewDecisionEntry::from(AssistantReviewStatus::APPROVED, 'Looks good.', new \DateTimeImmutable('2026-11-05T12:30:00+01:00')),
        );

        self::assertSame(
            [['status' => 'approved', 'reason' => 'Looks good.', 'reviewed_at' => '2026-11-05T12:30:00+01:00']],
            $history->toRows(),
        );
    }
}
