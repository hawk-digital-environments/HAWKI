<?php

namespace App\Models\Ai;

use App\Collections\AiToolCollection;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Policies\AiToolPolicy;
use App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry;
use App\Services\Ai\Models\Capabilities\Values\AiModelCapabilityDefinition;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[CollectedBy(AiToolCollection::class)]
#[UsePolicy(AiToolPolicy::class)]
class AiTool extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'type',
        'name',
        'class_name',
        'mcp_server_id',
        'mcp_name',
        'mcp_config',
        'description',
        'capability',
        'mapped_capability',
        'active',
        // @todo 'added_by_file' is a temporary field until we get rid of the config files
        'added_by_file',
    ];

    protected $casts = [
        'active' => 'boolean',
        'mcp_config' => 'json',
        'added_by_file' => 'boolean',
        'type' => ToolType::class,
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('active', ActiveFilterScope::class);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The MCP server this tool belongs to (nullable for function-call tools).
     *
     * @return BelongsTo<McpServer, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(McpServer::class, 'mcp_server_id');
    }

    /**
     * The AI models that are allowed to use this tool.
     *
     * @return BelongsToMany<AiModel, $this>
     */
    public function models(): BelongsToMany
    {
        return $this->belongsToMany(AiModel::class, 'ai_model_tools', 'ai_tool_id', 'ai_model_id')
            ->withPivot(['type', 'source_id'])
            ->withTimestamps();
    }

    public function capability(): AiModelCapabilityDefinition
    {
        $capabilityKey = $this->getEffectiveCapability();
        $registry = app(AiModelCapabilityRegistry::class);
        return $registry->getDefinition($capabilityKey);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the effective capability key for this tool, considering any user-defined mapping.
     *
     * If 'mapped_capability' is set, it will be returned. Otherwise, the original 'capability' will be used.
     */
    public function getEffectiveCapability(): string|null
    {
        return $this->mapped_capability ?? $this->capability;
    }

}
