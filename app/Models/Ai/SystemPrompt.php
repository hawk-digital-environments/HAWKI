<?php

namespace App\Models\Ai;

use App\Casts\AsLocale;
use App\Models\Scopes\Generic\LocaleAwareScope;
use App\Models\Scopes\Generic\UsageTypeOverlayScope;
use App\Services\Ai\Values\SystemPromptType;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use App\Services\Users\UserCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SystemPrompt extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'prompt_type',
        'usage_type',
        'locale',
        'prompt',
    ];

    protected $casts = [
        'prompt_type' => SystemPromptType::class,
        'locale' => AsLocale::class
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('usage_type_overlay', new UsageTypeOverlayScope('prompt_type'))
            ->addScope('locale', new LocaleAwareScope(discriminatorFieldsForOverlay: ['prompt_type']), fn(Request $req) => UserCondition::isUser($req));
    }
}
