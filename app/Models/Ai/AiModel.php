<?php

namespace App\Models\Ai;

use App\Casts\AsInstance;
use App\Collections\AiModelCollection;
use App\Collections\AiModelUsageRuleCollection;
use App\Models\Scopes\Generic\ActiveFilterOnRelationScope;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Models\Scopes\Generic\UsageTypeFilterOnRelationScope;
use App\Policies\AiModelPolicy;
use App\Services\Ai\Values\ModelCapabilities;
use App\Services\Ai\Values\ModelDemand;
use App\Services\Ai\Values\ModelIoMethods;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\ModelSettings;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\Ai\Values\ToolType;
use App\Services\Storage\Values\FileType;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

/**
 * @property-read ModelCapabilities $capabilities
 * @property-read ModelIoMethods $input
 * @property-read ModelIoMethods $output
 * @property-read ModelParameters $parameters
 * @property-read ModelSettings $settings
 * @property OnlineStatus $status
 * @property ModelDemand $demand
 * @property bool $active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiTool> $tools
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
        'capabilities',
        'settings',
    ];

    public function getCasts(): array
    {
        return [
            'active' => 'boolean',
            'input' => AsInstance::of(ModelIoMethods::class),
            'output' => AsInstance::of(ModelIoMethods::class),
            'parameters' => AsInstance::of(ModelParameters::class),
            'status' => OnlineStatus::class,
            'demand' => ModelDemand::class,
            'capabilities' => AsInstance::of(ModelCapabilities::class),
            'settings' => AsInstance::of(ModelSettings::class)
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
     * The usage rules that apply to this model.
     *
     * @return HasOneOrMany<AiModelUsageRule, $this, AiModelUsageRuleCollection>
     */
    public function usageRules(): HasOneOrMany
    {
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
            ->withTimestamps()
            ->where(function ($query) {
                $query
                    ->where('active', true)
                    ->where('ai_tools.type', '!=', ToolType::MCP->value)
                    ->orWhereExists(function ($subQuery) {
                        $subQuery->selectRaw('1')
                            ->from('mcp_servers')
                            ->whereColumn('mcp_servers.id', 'ai_tools.mcp_server_id')
                            ->where('mcp_servers.status', OnlineStatus::ONLINE->value);
                    });

            });
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

    /**
     * Checks if this model possesses all required tools to process a document.
     * @return bool
     */
    public function canProcessDocument(): bool
    {
        return $this->settings->canHandleFiles();
    }

    /**
     * Checks if this model possesses all required tools to process an image.
     * @return bool
     */
    public function canProcessImage(): bool
    {
        return $this->input->hasImage() && $this->settings->canHandleFiles();
    }

    /**
     * Checks if the model can process a specific type of file based on its capabilities and input methods.
     * @param FileType $type The type of file to check.
     * @return bool True if the model can process the given file type, false otherwise.
     */
    public function canProcessFileType(FileType $type): bool
    {
        // @todo we should expand this list to allow detection for all kinds of attachments (audio, video, etc.) based on the model's capabilities and input methods.
        return match ($type) {
            FileType::IMAGE => $this->canProcessImage(),
            FileType::PLAIN_TEXT => $this->canProcessDocument(),
            default => false,
        };
    }
}
