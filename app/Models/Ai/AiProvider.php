<?php

namespace App\Models\Ai;

use App\Casts\AsInstance;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Policies\AiProviderPolicy;
use App\Services\Ai\Providers\Values\ProviderSettings;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read ProviderSettings $settings
 */
#[UsePolicy(AiProviderPolicy::class)]
class AiProvider extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'provider_id',  // config key, e.g. 'openAi', 'gwdg'
        'name',
        'active',
        'api_url',
        'api_key',
        'adapter_key',
        'additional_config',
        'settings',
        'model_status_url',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'api_key' => 'encrypted',
            'additional_config' => 'encrypted:json',
            'settings' => AsInstance::of(ProviderSettings::class)
        ];
    }

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('active', new ActiveFilterScope());
    }

    /**
     * The models that belong to this provider.
     *
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        AiModel::scopeContext()->setAllScopesLocallyDisabled();
        return $this->hasMany(AiModel::class, 'provider_id');
    }
}
