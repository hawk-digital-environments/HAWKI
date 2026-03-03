<?php

namespace App\Models\Ai\Tools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function server(): BelongsTo
    {
        return $this->belongsTo(McpServer::class, 'server_id');
    }


}
