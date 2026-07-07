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
use Illuminate\Database\Eloquent\Relations\HasOne;

#[CollectedBy(SystemModelCollection::class)]
#[UsePolicy(SystemModelPolicy::class)]
class SystemModel extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'model_type',
        'usage_type',
        'model_id',
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('usage_type_overlay', new UsageTypeOverlayScope('model_type'));
    }

    /**
     * @return HasOne<AiModel, $this>
     */
    public function model(): HasOne
    {
        return $this->hasOne(AiModel::class, 'model_id', 'model_id');
    }
}
