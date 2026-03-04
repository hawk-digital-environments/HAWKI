<?php

namespace App\Models\Ai\Tools;

use App\Models\Ai\AiModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiTool extends Model
{
    protected $fillable = [
        'name',
        'description',
        'inputSchema',
        'capability',
        'server_id',
        'type',
        'status',
    ];

    protected $casts = [
        'inputSchema' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The MCP server this tool belongs to (nullable for function-call tools).
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(McpServer::class, 'server_id');
    }

    /**
     * The AI models that are allowed to use this tool.
     */
    public function models(): BelongsToMany
    {
        return $this->belongsToMany(AiModel::class, 'ai_model_tools', 'ai_tool_id', 'ai_model_id')
            ->withPivot(['type', 'source_id'])
            ->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMcp($query)
    {
        return $query->where('type', 'mcp');
    }
}
