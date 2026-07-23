<?php
declare(strict_types=1);


namespace App\Collections;


use App\Models\Ai\AiModelUsageRule;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Collection<int, AiModelUsageRule>
 */
class AiModelUsageRuleCollection extends Collection
{
    /**
     * Returns true if the collection contains a rule for the given usage type.
     * If this returns false, the model is not allowed to be used for the given usage type.
     * @param string $usageType {@see WellKnownUsageTypes} for built-in usage types, but this can also be a custom usage type defined by plugins.
     * @return bool
     */
    public function isAllowedIn(string $usageType): bool
    {
        return $this->contains(fn(AiModelUsageRule $rule) => $rule->usage_type === $usageType);
    }
}
