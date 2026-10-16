<?php

namespace App\Models\Ai;

use App\Collections\SystemModelCollection;
use App\Models\Scopes\Generic\UsageTypeOverlayScope;
use App\Policies\SystemModelPolicy;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $model_type
 * @property string $usage_type
 * @property int|null $ai_model_id
 * @property string $model_id Plain model id of the linked model.
 *                            {@deprecated Kept for backwards compatibility, use {@see model()} or `ai_model_id`.}
 * @property-read AiModel|null $model
 */
#[CollectedBy(SystemModelCollection::class)]
#[UsePolicy(SystemModelPolicy::class)]
class SystemModel extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'model_type',
        'usage_type',
        'ai_model_id',
        'model_id',
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('usage_type_overlay', new UsageTypeOverlayScope('model_type'));
    }

    /**
     * Linked by the numeric id, because the same `model_id` may exist for several providers.
     *
     * @return BelongsTo<AiModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
