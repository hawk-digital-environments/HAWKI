<?php

namespace App\Models\Ai;

use App\Casts\AsInstance;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Policies\AiProviderPolicy;
use App\Services\Ai\Providers\Values\ProviderSettings;
use App\Services\Storage\ProviderIconStorageService;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read ProviderSettings $settings
 * @property array|null $icon SVG contents and source metadata, or legacy storage UUIDs.
 * @property-read string|null $icon_url
 * @property-read string|null $icon_url_dark
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
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'api_key' => 'encrypted',
            'additional_config' => 'encrypted:json',
            'settings' => AsInstance::of(ProviderSettings::class),
            'icon' => 'array',
        ];
    }

    /**
     * Public URL of the icon for light backgrounds (also the fallback for dark ones).
     */
    protected function iconUrl(): Attribute
    {
        return Attribute::get(fn() => isset($this->icon['svg'])
            ? 'data:image/svg+xml;base64,' . base64_encode($this->icon['svg'])
            : app(ProviderIconStorageService::class)->urlForUuid($this->icon['light'] ?? null));
    }

    /**
     * Public URL of the dark-background variant, null when the icon has none.
     */
    protected function iconUrlDark(): Attribute
    {
        return Attribute::get(fn() => isset($this->icon['svg_dark'])
            ? 'data:image/svg+xml;base64,' . base64_encode($this->icon['svg_dark'])
            : app(ProviderIconStorageService::class)->urlForUuid($this->icon['dark'] ?? null));
    }

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('active', new ActiveFilterScope());
    }

    /**
     * The provider's full model catalog, unaffected by AiModel's contextual
     * scopes (active/provider/usage filters) — admin surfaces and the status
     * updater must see every model row. Exempting only this relation's query
     * keeps every other AiModel query scoped: the previous global disable
     * leaked past the relation and unscoped all later AiModel queries of
     * the process.
     *
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id')
            ->withoutGlobalScopes(self::aiModelContextualScopeKeys());
    }

    /**
     * @return list<string>
     */
    private static function aiModelContextualScopeKeys(): array
    {
        $context = AiModel::scopeContext();

        return array_map(
            static fn (string $scopeKey): string => $context->getFullScopeKey($scopeKey),
            array_keys(AiModel::getContextualScopes()),
        );
    }
}
