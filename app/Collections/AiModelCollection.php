<?php
declare(strict_types=1);


namespace App\Collections;


use App\Models\Ai\AiModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Collection<int, AiModel>
 */
class AiModelCollection extends Collection
{
    /**
     * Get a model by its ID.
     * The ID will be matched using the {@see AiModel::idMatches()} method for flexible matching.
     *
     * @param string|int $id The ID to match.
     * @return AiModel|null The matched model or null if not found.
     */
    public function getModel(string|int $id): ?AiModel
    {
        foreach ($this as $item) {
            if ($item->idMatches($id)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * First sorts by the "sort_order" (int, higher come later), then by the "label" alphabetically.
     * @return self
     */
    public function sortByOrderAndLabel(): self
    {
        /** @var self $new */
        $new = $this->sortBy([
            ['sort_order', 'asc'],
            [fn(AiModel $a, AiModel $b) => strcasecmp($a->label, $b->label), 'asc'],
        ]);

        return $new;
    }
}
