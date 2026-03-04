<?php

namespace App\Models\Ai;

use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelStatus extends Model
{
    protected $primaryKey   = 'model_id';
    public $incrementing    = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'model_id',
        'status',
    ];

    protected $casts = [
        'status' => ModelOnlineStatus::class,
    ];

    /**
     * The AI model this status record belongs to.
     */
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id', 'model_id');
    }
}
