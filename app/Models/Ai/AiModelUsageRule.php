<?php

namespace App\Models\Ai;

use App\Collections\AiModelUsageRuleCollection;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[CollectedBy(AiModelUsageRuleCollection::class)]
class AiModelUsageRule extends Model
{
    protected $fillable = [
        'ai_model_id',
        'usage_type',
    ];

    /**
     * @return BelongsTo<AiModel, $this>
     */
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }
}
