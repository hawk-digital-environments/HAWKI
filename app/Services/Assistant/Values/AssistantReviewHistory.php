<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * Append-only decision history of one review (the `history` JSON column).
 */
final readonly class AssistantReviewHistory
{
    /** @param list<AssistantReviewDecisionEntry> $entries */
    private function __construct(
        private readonly array $entries,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param null|array<int, mixed> $rows
     */
    public static function fromRows(null|array $rows): self
    {
        if (null === $rows) {
            return self::empty();
        }

        $entries = [];

        foreach ($rows as $row) {
            $entry = AssistantReviewDecisionEntry::tryFromRow($row);

            if (null !== $entry) {
                $entries[] = $entry;
            }
        }

        return new self($entries);
    }

    public function appended(AssistantReviewDecisionEntry $entry): self
    {
        return new self([...$this->entries, $entry]);
    }

    /**
     * @return list<AssistantReviewDecisionEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function latest(): null|AssistantReviewDecisionEntry
    {
        return $this->entries[\count($this->entries) - 1] ?? null;
    }

    /**
     * @return list<array{status: string, reason: null|string, reviewed_at: string}>
     */
    public function toRows(): array
    {
        return \array_map(
            static fn (AssistantReviewDecisionEntry $entry): array => $entry->toRow(),
            $this->entries,
        );
    }
}
