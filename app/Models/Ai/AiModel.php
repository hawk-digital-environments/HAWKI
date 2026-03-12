<?php

namespace App\Models\Ai;

use App\Models\Ai\Tools\AiTool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiModel extends Model
{
    protected $fillable = [
        'active',
        'model_id',
        'label',
        'input',
        'output',
        'tools',
        'default_params',
        'provider_id',
    ];

    protected $casts = [
        'active'        => 'boolean',
        'input'         => 'array',
        'output'        => 'array',
        'tools'         => 'array',
        'default_params' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return $this->model_id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDefaultParams(): array
    {
        return $this->default_params ?? [];
    }

    // -------------------------------------------------------------------------
    // Status helpers (delegates to the status relationship)
    // -------------------------------------------------------------------------

    /**
     * Returns the model status value, or null if no status record exists.
     */
    public function getOnlineStatus(): ?\App\Services\AI\Value\ModelOnlineStatus
    {
        return $this->status?->status;
    }

    /**
     * Persist a new status for this model.
     */
    public function setStatus(\App\Services\AI\Value\ModelOnlineStatus $onlineStatus): void
    {
        AiModelStatus::updateOrCreate(
            ['model_id' => $this->model_id],
            ['status' => $onlineStatus]
        );
    }

    // -------------------------------------------------------------------------
    // Eloquent Relationships
    // -------------------------------------------------------------------------

    /**
     * The online-status record for this model (1-to-1, keyed by model_id string).
     */
    public function status(): HasOne
    {
        return $this->hasOne(AiModelStatus::class, 'model_id', 'model_id');
    }

    /**
     * The provider that owns this model.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    /**
     * The tools that are externally assigned to this model via the pivot table.
     * Named 'assignedTools' to avoid collision with the 'tools' JSON config column.
     */
    public function assignedTools(): BelongsToMany
    {
        return $this->belongsToMany(AiTool::class, 'ai_model_tools', 'ai_model_id', 'ai_tool_id')
            ->withPivot(['type', 'source_id'])
            ->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // Capabilities (DB-assigned, cached)
    // -------------------------------------------------------------------------

    /**
     * Load all model→capability mappings from the DB in one query.
     * Result shape: [ model_id => [ capability => tool_name, ... ], ... ]
     *
     * Cached for 1 hour. Call clearCapabilitiesCache() after modifying assignments.
     */
    public static function capabilitiesMap(): array
    {
        return Cache::remember('ai_model_capabilities', now()->addHour(), function () {
            // Query directly from the pivot table so only tools WITH an assignment
            // can appear — no eager-load ambiguity, no unassigned tools leaking in.
            $rows = DB::table('ai_model_tools')
                ->join('ai_tools', 'ai_model_tools.ai_tool_id', '=', 'ai_tools.id')
                ->join('ai_models', 'ai_model_tools.ai_model_id', '=', 'ai_models.id')
                ->where('ai_tools.active', true)
                ->where('ai_tools.status', 'active')
                ->where('ai_models.active', true)
                ->select('ai_models.model_id', 'ai_tools.name', 'ai_tools.capability')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                $capability = ($row->capability !== null && $row->capability !== '')
                    ? $row->capability
                    : $row->name;

                $map[$row->model_id][$capability] = $row->name;
            }

            return $map;
        });
    }
    /**
     * Invalidate the capabilities cache — call this after assigning or detaching tools.
     */
    public static function clearCapabilitiesCache(): void
    {
        Cache::forget('ai_model_capabilities');
    }
}
