<?php

namespace App\Models\Assistants;

use App\Models\Ai\Tools\AiTool;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Repositories\OrganizationRepository;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

#[Table('assistants')]
class Assistant extends Model
{
    use HasFactory;

    protected $attributes = [
        'name' => '',
        'system_prompt' => '',
        'greeting' => '',
        'description' => '',
        'detail_description' => '',
        'allow_remix' => false,
        'allow_model_select' => false,
        'release_stage' => ReleaseStage::DRAFT,
        'model' => '',
        'max_tokens' => 0,
        'temp' => 0.0,
        'top_p' => 0.0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Assistant $assistant) {
            if ($assistant->creator_id !== null) {
                return;
            }

            $user = Auth::user();
            if ($user === null) {
                return;
            }

            $assistant->creator_id = $user->id;
            $assistant->remixed_creator_id ??= null;
            $assistant->organization_id = app(OrganizationRepository::class)->getForUser($user)?->id;
        });
    }

    protected $fillable = [
        'name',
        'handle',
        'system_prompt',
        'greeting',
        'description',
        'detail_description',
        'allow_remix',
        'allow_model_select',
        'category_id',
        'release_stage',
        'model',
        'max_tokens',
        'temp',
        'top_p',
        'creator_id',
        'remixed_creator_id',
        'remixed_assistant_id',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'allow_remix' => 'boolean',
            'allow_model_select' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function settingValues(): HasMany
    {
        return $this->hasMany(AssistantSettingValue::class);
    }

    public function user_prompts(): HasMany
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function remix_creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remixed_creator_id');
    }

    public function remixed_assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class, 'remixed_assistant_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(Assistant::class, 'remixed_assistant_id');
    }

    public function ai_tools(): BelongsToMany
    {
        return $this->belongsToMany(AiTool::class, 'assistant_tools');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assistant_favorite_users')
            ->withTimestamps();
    }

    public function getIsFavoriteAttribute(): bool
    {
        if (array_key_exists('is_favorite', $this->attributes)) {
            return (bool) $this->attributes['is_favorite'];
        }

        return $this->favoritedByUsers()
            ->where('user_id', Auth::id())
            ->exists();
    }
}
