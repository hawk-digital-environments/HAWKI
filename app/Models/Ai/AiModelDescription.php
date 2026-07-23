<?php

namespace App\Models\Ai;

use App\Casts\AsLocale;
use App\Models\Scopes\Generic\LocaleAwareScope;
use App\Policies\AiModelDescriptionPolicy;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use App\Services\Users\UserCondition;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

#[UsePolicy(AiModelDescriptionPolicy::class)]
class AiModelDescription extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'locale',
        'ai_model_id',
        'description',
    ];

    protected $casts = [
        'locale' => AsLocale::class
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope(
            'locale',
            new LocaleAwareScope(discriminatorFieldsForOverlay: ['ai_model_id']),
            fn(Request $request) => UserCondition::isUser($request)
        );
    }

    /**
     * @return BelongsTo<AiModel, $this>
     */
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }
}
