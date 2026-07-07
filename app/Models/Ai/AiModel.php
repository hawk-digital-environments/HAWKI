<?php

namespace App\Models\Ai;

use App\Casts\AsInstance;
use App\Collections\AiModelCollection;
use App\Collections\AiModelUsageRuleCollection;
use App\Collections\AiToolCollection;
use App\Models\Scopes\Generic\ActiveFilterOnRelationScope;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Models\Scopes\Generic\UsageTypeFilterOnRelationScope;
use App\Policies\AiModelPolicy;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Limits\AiModelLimitRegistry;
use App\Services\Ai\Models\Limits\AiModelLimitsInterface;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\AiModelPricingInterface;
use App\Services\Ai\Models\Pricing\AiModelPricingRegistry;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\StatusCheck\Values\ModelDemand;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

/**
 * @property AiModelIoMethods $input
 * @property AiModelIoMethods $output
 * @property AiModelParameters $parameters
 * @property OnlineStatus $status
 * @property ModelDemand $demand
 * @property AiModelSettings $settings
 * @property AiModelLimitsInterface $limits
 * @property AiModelPricingInterface $pricing
 * @property AiModelFlags $flags
 * @property NativeAiModelCapabilities $native_capabilities
 * @property bool $active
 * @property-read AiToolCollection $tools
 */
#[CollectedBy(AiModelCollection::class)]
#[UsePolicy(AiModelPolicy::class)]
class AiModel extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'active',
        'model_id',
        'label',
        'input',
        'output',
        'parameters',
        'provider_id',
        'status',
        'demand',
        'settings',
        'model_type',
        'documentation_url',
        'deprecation_date',
        'native_capabilities',
        'limits',
        'pricing',
        'flags',
    ];

    public function getCasts(): array
    {
        return [
            'active' => 'boolean',
            'deprecation_date' => 'datetime',
            'input' => AsInstance::of(AiModelIoMethods::class),
            'output' => AsInstance::of(AiModelIoMethods::class),
            'parameters' => AsInstance::of(AiModelParameters::class),
            'status' => OnlineStatus::class,
            'demand' => ModelDemand::class,
            'settings' => AsInstance::of(AiModelSettings::class),
            'limits' => AsInstance::of(static function ($model) {
                return Container::getInstance()->make(AiModelLimitRegistry::class)
                    ->getLimitClassForModel($model) ?? NullAiModelLimits::class;
            }),
            'pricing' => AsInstance::of(static function ($model) {
                return Container::getInstance()->make(AiModelPricingRegistry::class)
                    ->getPricingClassForModel($model) ?? NullPricing::class;
            }),
            'flags' => AsInstance::of(AiModelFlags::class),
            'native_capabilities' => AsInstance::of(NativeAiModelCapabilities::class)
        ];
    }

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('active', new ActiveFilterScope())
            ->addScope('provider_active', new ActiveFilterOnRelationScope('provider'))
            ->addScope('usage_type_filter', new UsageTypeFilterOnRelationScope('usageRules'));
    }

    /**
     * The provider that owns this model.
     *
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    /**
     * The descriptions that are associated with this model.
     *
     * @return HasOneOrMany<AiModelDescription, $this>
     */
    public function description(): HasOneOrMany
    {
        return $this->hasMany(AiModelDescription::class, 'ai_model_id');
    }

    /**
     * The usage rules that apply to this model.
     *
     * @return HasOneOrMany<AiModelUsageRule, $this, AiModelUsageRuleCollection>
     */
    public function usageRules(): HasOneOrMany
    {
        // @phpstan-ignore return.type
        return $this->hasMany(AiModelUsageRule::class, 'ai_model_id');
    }

    /**
     * The tools that are associated with this model.
     *
     * @return BelongsToMany<AiTool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(AiTool::class, 'ai_model_tools', 'ai_model_id', 'ai_tool_id')
            ->withPivot(['type', 'source_id'])
            ->withTimestamps();
    }

    /**
     * Checks if the model ID matches the provided ID.
     * This is useful for checking if the model is the one we are looking for.
     * It will try a fuzzy match to check if the configured models ID ends with the provided ID or vis versa.
     * If a numeric ID is provided, it will be compared to the model's primary key ID.
     *
     * @param string|int $idToTest The ID to test against the model's ID.
     * @return bool True if the model's ID matches the provided ID, false otherwise.
     */
    public function idMatches(string|int $idToTest): bool
    {
        if (empty($idToTest)) {
            return false;
        }

        if (is_int($idToTest)) {
            return $this->id === $idToTest;
        }

        return $this->model_id === $idToTest || str_ends_with($this->model_id, $idToTest) || str_ends_with($idToTest, $this->model_id);
    }
}
