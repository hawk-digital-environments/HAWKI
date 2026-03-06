<?php

namespace App\Models\Ai\Tools;

use App\Models\Ai\AiModel;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;

class AiTool extends Model
{
    protected $fillable = [
        'type',
        'name',
        'class_name',
        'server_id',
        'description',
        'capability',
        'inputSchema',
        'outputSchema',
        'status',
        'active',
    ];

    protected $casts = [
        'inputSchema' => 'array',
        'active'      => 'boolean',
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

    // -------------------------------------------------------------------------
    // Status Check
    // -------------------------------------------------------------------------

    /**
     * Check whether this tool's MCP server is reachable and update status accordingly.
     *
     * Returns true when the server is available (status set to 'active'),
     * false otherwise (status set to 'inactive').
     */
    public function checkStatus(): bool
    {
        if (!$this->relationLoaded('server')) {
            $this->load('server');
        }

        if (!$this->server) {
            Log::warning("AiTool '{$this->name}' has no associated MCP server — marking inactive.");
            $this->update(['status' => 'inactive']);
            return false;
        }

        $server = $this->server;

        try {
            $client      = new MCPSSEClient($server->url, (int) $server->timeout, $server->api_key ?: null);
            $isAvailable = $client->isAvailable();
        } catch (\Exception $e) {
            Log::warning("AiTool '{$this->name}' status check failed: " . $e->getMessage());
            $isAvailable = false;
        }

        $newStatus = $isAvailable ? 'active' : 'inactive';
        $this->update(['status' => $newStatus]);

        return $isAvailable;
    }
}
