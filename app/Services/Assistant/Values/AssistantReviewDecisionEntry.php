<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * One decision record of a review's history.
 */
final readonly class AssistantReviewDecisionEntry
{
    private function __construct(
        public readonly AssistantReviewStatus $status,
        public readonly null|string $reason,
        public readonly \DateTimeImmutable $reviewedAt,
    ) {
    }

    public static function from(AssistantReviewStatus $status, null|string $reason, \DateTimeImmutable $reviewedAt): self
    {
        return new self($status, $reason, $reviewedAt);
    }

    /**
     * Tolerant row reader; null when the stored row is malformed.
     */
    public static function tryFromRow(mixed $row): null|self
    {
        if (!\is_array($row)) {
            return null;
        }

        $status = AssistantReviewStatus::tryFrom((string) ($row['status'] ?? ''));

        if (null === $status) {
            return null;
        }

        try {
            $reviewedAt = new \DateTimeImmutable((string) ($row['reviewed_at'] ?? ''));
        } catch (\Throwable) {
            return null;
        }

        $reason = $row['reason'] ?? null;

        return new self($status, \is_string($reason) ? $reason : null, $reviewedAt);
    }

    /**
     * @return array{status: string, reason: null|string, reviewed_at: string}
     */
    public function toRow(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => $this->reason,
            'reviewed_at' => $this->reviewedAt->format('c'),
        ];
    }
}
