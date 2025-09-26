<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;

class AiAssistant extends Model
{
    use AsSource, Filterable, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'status',
        'visibility',
        'org_id',
        'owner_id',
        'ai_model',
        'prompt',
        'tools',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'tools' => 'array',
        ];
    }

    /**
     * Name of columns to which http filtering can be applied
     */
    protected $allowedFilters = [
        'name' => Like::class,
        'key' => Like::class,
        'description' => Like::class,
        'status' => Where::class,
        'visibility' => Where::class,
        'owner_id' => Where::class,
        'prompt' => Like::class,
        'created_at' => WhereDateStartEnd::class,
        'updated_at' => WhereDateStartEnd::class,
    ];

    /**
     * Name of columns to which http sorting can be applied
     */
    protected $allowedSorts = [
        'name',
        'key',
        'status',
        'visibility',
        'owner_id',
        'prompt',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the owner of the AI assistant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Relationship: AI Model assigned to this assistant
     */
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model', 'system_id');
    }

    /**
     * Scope query to active assistants.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to visible assistants based on visibility level.
     */
    public function scopeVisible($query, $level = 'public')
    {
        return $query->where('visibility', $level);
    }

    /**
     * Scope query to assistants owned by specific user.
     */
    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('owner_id', $userId);
    }
}
