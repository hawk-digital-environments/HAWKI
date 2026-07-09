<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Repositories;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelDescription;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;

/**
 * Repository for {@see AiModelDescription} records.
 *
 * Handles persistence of per-locale descriptions that are attached to an
 * {@see AiModel}. Descriptions are upserted (not just inserted) so that
 * re-running the sync pipeline is idempotent: an existing description for a
 * given model + locale combination is updated in-place rather than duplicated.
 *
 * The associated model uses {@see \App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait},
 * which applies a locale-aware scope for regular user queries. Admin/sync
 * operations that must bypass that scope should call
 * {@see getQueryWithoutContextualScopes()} directly or pass the appropriate
 * {@see \App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides}.
 */
class AiModelDescriptionRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Upserts a localised description for the given model.
     *
     * Matching is done on (ai_model_id, locale); only the description text is
     * updated. The contextual locale scope is bypassed so the operation works
     * regardless of the currently active request locale.
     *
     * @return AiModelDescription The persisted (created or updated) description record.
     */
    public function assignDescriptionToModel(AiModel $model, AiModelDescription $description): AiModelDescription
    {
        return $this->getQueryWithoutContextualScopes()
            ->updateOrCreate(
                [
                    'ai_model_id' => $model->id,
                    'locale' => $description->locale
                ],
                [
                    'description' => $description->description
                ]
            );
    }

}
