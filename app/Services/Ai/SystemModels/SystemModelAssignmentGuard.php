<?php

declare(strict_types=1);

namespace App\Services\Ai\SystemModels;

use App\Models\Ai\AiModel;
use Illuminate\Database\ConnectionInterface;

final class SystemModelAssignmentGuard
{
    public function __construct(private readonly ConnectionInterface $database)
    {
    }

    public function assertAssignable(AiModel $model, string $usageType): void
    {

        $providerActive = $this->database->table('ai_providers')->where('id', $model->provider_id)->value('active');
        $usageAllowed = $this->database->table('ai_model_usage_rules')
            ->where('ai_model_id', $model->getKey())
            ->where('usage_type', $usageType)
            ->exists();

        if (!$model->active || !$providerActive || !$usageAllowed) {
            throw new SystemModelAssignmentException(SystemModelAssignmentException::UNAVAILABLE);
        }
    }

    /**
     * A model used by a system slot must stay active, available for every user, and available in
     * the usage contexts of its slots.
     *
     * @param array<int, string>     $usageRules
     */
    public function assertConfigurationAllowed(
        AiModel $model,
        bool $active,
        bool $providerActive,
        array $usageRules,
    ): void {
        $slots = $this->database->table('system_models')->where('model_id', $model->model_id)->get(['usage_type']);

        if ($slots->isEmpty()) {
            return;
        }

        foreach ($slots as $slot) {
            if (!$active || !$providerActive || !\in_array($slot->usage_type, $usageRules, true)) {
                throw new SystemModelAssignmentException(SystemModelAssignmentException::UNAVAILABLE);
            }
        }
    }
}
