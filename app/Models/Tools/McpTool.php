<?php

namespace App\Models\Tools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpTool extends Model
{
    protected $fillable = [
        'name',
        'description',
        'inputSchema',
        'capability',
        'server_id'
    ];
    protected $casts = [
        'inputSchema' => 'array',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(McpServer::class, 'server_id');
    }


}
