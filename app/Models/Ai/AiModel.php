<?php

namespace App\Models\Ai;

use App\Models\Ai\Tools\AiTool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    protected $fillable = [
        'active',
        'model_id',
        'label',
        'input',
        'output',
        'default_params',
        'provider_id'
    ];
    protected $casts = [
        'active' => 'boolean',
        'input' => 'array',
        'output' => 'array',
        'default_params' => 'array'
    ];



    /**
     * Returns the configured ID of the model.
     * @return string
     */
    public function getId(): string
    {
        return $this->model_id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getStatus(): AiModelStatus
    {
        return AiModelStatus::find($this->model_id);
    }

    public function setStatus($status){
        AiModelStatus::updateOrCreate(
            ['model_id' => $this->getId()],
            ['status' => $status]
        );
    }

    public function getTools(): belongstoMany
    {
        return $this->belongsToMany(AiTool::class, 'ai_model_tools')
            ->withPivot(['type', 'source_id'])
            ->withTimestamps();
    }

    public function getProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function getDefaultParams(): array{
        return $this->default_params;
    }




}
