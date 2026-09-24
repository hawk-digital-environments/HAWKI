<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Values;


use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\Citation;
use Laravel\Ai\Responses\Data\UrlCitation;

/**
 * Extends Laravel AI's {@see UrlCitation} to aggregate multiple text ranges that
 * reference the same source URL into a single citation object.
 *
 * Laravel AI emits one citation per provider annotation, so one URL can arrive many
 * times — each with its own `startIndex`/`endIndex`. Collapsing them via
 * {@see mergeByUrl()} avoids duplicate citations while preserving the full set of
 * ranges for client-side highlighting.
 *
 * The parent class stores only one `startIndex`/`endIndex` pair; this class
 * accumulates all pairs in {@see $ranges} and only populates the parent fields from
 * the very first range added, keeping backwards compatibility with consumers that
 * only read the inherited fields.
 *
 * The {@see $isByteOffset} flag distinguishes Google's grounding metadata (byte
 * offsets) from the legacy citation metadata format (character offsets), allowing
 * frontend code to apply the correct offset calculation.
 */
class UrlMultiCitation extends UrlCitation
{
    /**
     * All [startIndex, endIndex] pairs that point into the response text for this URL.
     *
     * Each entry is a two-element integer array: `[$startIndex, $endIndex]`.
     *
     * @var Collection<int, array{0: int, 1: int}>
     */
    public Collection $ranges;

    /**
     * @param bool $isByteOffset When true, all index values in {@see $ranges} are
     *                           byte offsets (Gemini grounding) rather than character
     *                           offsets (legacy citation metadata).
     */
    public function __construct(
        string      $url,
        ?string     $title = null,
        ?int        $startIndex = null,
        ?int        $endIndex = null,
        public bool $isByteOffset = false
    )
    {
        parent::__construct($url, $title, $startIndex, $endIndex);

        if ($startIndex !== null && $endIndex !== null) {
            $this->ranges = collect([[$startIndex, $endIndex]]);
        } else {
            $this->ranges = collect();
        }
    }

    /**
     * Collapses all {@see UrlCitation}s pointing at the same URL into one {@see UrlMultiCitation}
     * that carries every range. Laravel AI reports character offsets, so `$isByteOffset` is false.
     * Other citation types pass through unchanged, and the first-seen order is kept.
     *
     * @param iterable<Citation> $citations
     * @return list<Citation>
     */
    public static function mergeByUrl(iterable $citations): array
    {
        $merged = [];
        /** @var array<string, self> $byUrl */
        $byUrl = [];

        foreach ($citations as $citation) {
            if (!$citation instanceof UrlCitation) {
                $merged[] = $citation;
                continue;
            }

            $existing = $byUrl[$citation->url] ?? null;
            if ($existing === null) {
                $merged[] = $byUrl[$citation->url] = $citation instanceof self
                    ? $citation
                    : new self($citation->url, $citation->title, $citation->startIndex, $citation->endIndex);
                continue;
            }

            $existing->title ??= $citation->title;
            $ranges = $citation instanceof self ? $citation->ranges : [[$citation->startIndex, $citation->endIndex]];
            foreach ($ranges as [$startIndex, $endIndex]) {
                $existing->addRange($startIndex, $endIndex);
            }
        }

        return $merged;
    }

    /**
     * Add a text range for this citation, ignoring null values and exact duplicates.
     *
     * The parent class's `startIndex`/`endIndex` are populated from the first range
     * ever added so that consumers relying on the parent fields still get a valid
     * representative range.
     */
    public function addRange(?int $startIndex, ?int $endIndex): void
    {
        if ($startIndex === null || $endIndex === null) {
            return;
        }

        if ($this->ranges->contains(fn($range) => $range[0] === $startIndex && $range[1] === $endIndex)) {
            return;
        }

        $this->ranges[] = [$startIndex, $endIndex];

        if (count($this->ranges) === 1) {
            $this->startIndex = $startIndex;
            $this->endIndex = $endIndex;
        }
    }

    /**
     * @inheritDoc
     *
     * Merges the parent array representation with the accumulated ranges and the
     * byte-offset flag so that API responses carry the full citation detail.
     */
    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'ranges' => $this->ranges,
                'byteOffset' => $this->isByteOffset,
            ]
        );
    }
}
