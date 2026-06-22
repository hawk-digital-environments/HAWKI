<?php

namespace App\Models\Ai;

use App\Casts\AsInstance;
use App\Policies\McpServerPolicy;
use App\Services\Ai\Values\McpServerTimeouts;
use App\Services\Ai\Values\McpServerType;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property McpServerTimeouts $timeouts
 * @property OnlineStatus $status
 * @property McpServerType $type
 * @property array $additional_config
 */
#[UsePolicy(McpServerPolicy::class)]
class McpServer extends Model
{
    protected $fillable = [
        'url',
        'server_label',
        'version',
        'protocol_version',
        'description',
        'require_approval',
        'timeouts',
        'api_key',
        // @todo 'added_by_file' is a temporary field until we get rid of the config files
        'added_by_file',
        'additional_config',
        'type'
    ];

    protected function casts(): array
    {
        return [
            'added_by_file' => 'boolean',
            'api_key' => 'encrypted',
            'additional_config' => 'encrypted:json',
            'status' => OnlineStatus::class,
            'type' => McpServerType::class,
            'timeouts' => AsInstance::of(McpServerTimeouts::class)
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function tools(): HasMany
    {
        return $this->hasMany(AiTool::class, 'mcp_server_id');
    }
}
