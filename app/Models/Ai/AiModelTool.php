<?php

namespace App\Models\Ai;

use App\Services\Ai\Tools\Values\ToolType;
use Illuminate\Database\Eloquent\Model;

class AiModelTool extends Model
{
    protected $fillable = [
        'ai_model_id',
        'ai_tool_id',
        'type'
    ];

    protected $casts = [
        'type' => ToolType::class
    ];
}
