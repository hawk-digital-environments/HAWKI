<?php

namespace App\Models\Assistants;

use App\Models\Ai\Tools\AiTool;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Table('assistants')]
class Assistant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'handle',
        'system_prompt',
        'greeting',
        'description',
        'detail_description',
        'allow_remix',
        'allow_model_select',
        'language',
        'category',
        'review_stage',
        'formality',
        'model',
        'model_length',
        'model_temp',
        'model_top_p',
        'creator_id',
        'remixed_creator_id',
    ];

    public function userPrompts(): HasMany
    {
        return $this->hasMany(UserPrompt::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function original_creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remixed_creator_id');
    }

    public function originalAgent(): BelongsTo
    {
        return $this->belongsTo(Assistant::class, 'remixed_assistant_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(Assistant::class, 'remixed_assistant_id');
    }
    public function aiTools(): BelongsToMany
    {
        return $this->belongsToMany(AiTool::class, 'assistant_tools');
    }
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
