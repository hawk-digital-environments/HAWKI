<?php
declare(strict_types=1);


namespace App\Collections;


use App\Models\Ai\SystemModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Collection<int, SystemModel>
 */
class SystemModelCollection extends Collection
{
    public function getModelOfType(string $modelType, string|null $usageType = null): ?SystemModel
    {
        foreach ($this as $item) {
            if ($item->model_type === $modelType && ($usageType === null || $item->usage_type === $usageType)) {
                return $item;
            }
        }
        return null;
    }
}
