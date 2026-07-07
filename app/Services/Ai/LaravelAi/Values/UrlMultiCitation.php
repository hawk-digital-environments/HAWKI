<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Values;


use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\UrlCitation;

class UrlMultiCitation extends UrlCitation
{
    public Collection $ranges;

    public function __construct(
        string      $url,
        ?string     $title = null,
        ?int        $startIndex = null,
        ?int        $endIndex = null,
        /**
         * If true, the startIndex and endIndex are interpreted as byte offsets rather than character offsets.
         * @var bool
         */
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

    public function addRange(?int $startIndex, ?int $endIndex): void
    {
        if ($startIndex === null || $endIndex === null) {
            return;
        }

        // Avoid adding duplicate ranges
        if ($this->ranges->contains(fn($range) => $range[0] === $startIndex && $range[1] === $endIndex)) {
            return;
        }

        $this->ranges[] = [$startIndex, $endIndex];

        // Update startIndex and endIndex based on the first range
        if (count($this->ranges) === 1) {
            $this->startIndex = $startIndex;
            $this->endIndex = $endIndex;
        }
    }

    /**
     * @inheritDoc
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
