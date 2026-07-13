<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Models\Ai\AiTool;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;
use App\Policies\AssistantPolicy;
use App\Services\Assistant\Repositories\AssistantOrganizationRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * @property bool                            $allow_model_select
 * @property bool                            $allow_remix
 * @property null|int                        $category_id
 * @property null|\Illuminate\Support\Carbon $created_at
 * @property null|int                        $creator_id
 * @property string                          $description
 * @property string                          $detail_description
 * @property string                          $greeting
 * @property null|string                     $handle
 * @property int                             $id
 * @property int                             $max_tokens
 * @property string                          $model
 * @property string                          $name
 * @property null|int                        $organization_id
 * @property string                          $release_stage
 * @property null|int                        $remixed_assistant_id
 * @property null|int                        $remixed_creator_id
 * @property string                          $system_prompt
 * @property float                           $temp
 * @property float                           $top_p
 * @property null|\Illuminate\Support\Carbon $updated_at
 */
#[Table('assistants')]
#[UsePolicy(AssistantPolicy::class)]
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
        'release_stage' => AssistantReleaseStage::DRAFT,
        'model' => '',
        'max_tokens' => 0,
        'temp' => 0.0,
        'top_p' => 0.0,
    ];
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

    /**
     * @return BelongsTo<AssistantCategory, $this>
     */
    public function assistantCategory(): BelongsTo
    {
        return $this->belongsTo(AssistantCategory::class, 'category_id');
    }

    /**
     * @return HasOne<AssistantAvatar, $this>
     */
    public function assistantAvatar(): HasOne
    {
        return $this->hasOne(AssistantAvatar::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<AssistantSettingValue, $this>
     */
    public function settingValues(): HasMany
    {
        return $this->hasMany(AssistantSettingValue::class);
    }

    /**
     * @return HasMany<AssistantUserPrompt, $this>
     */
    public function assistantUserPrompts(): HasMany
    {
        return $this->hasMany(AssistantUserPrompt::class);
    }

    /**
     * @return HasMany<AssistantVersion, $this>
     */
    public function assistantVersions(): HasMany
    {
        return $this->hasMany(AssistantVersion::class);
    }

    /**
     * @return BelongsToMany<AssistantTag, $this>
     */
    public function assistantTags(): BelongsToMany
    {
        return $this->belongsToMany(AssistantTag::class, 'assistant_tag', 'assistant_id', 'tag_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function remix_creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remixed_creator_id');
    }

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function remixed_assistant(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remixed_assistant_id');
    }

    /**
     * @return HasMany<Assistant, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(self::class, 'remixed_assistant_id');
    }

    /**
     * @return BelongsToMany<AiTool, $this>
     */
    public function ai_tools(): BelongsToMany
    {
        return $this->belongsToMany(AiTool::class, 'assistant_tools');
    }

    /**
     * @return HasMany<AssistantFeedback, $this>
     */
    public function assistantFeedback(): HasMany
    {
        return $this->hasMany(AssistantFeedback::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return HasOne<AssistantReview, $this>
     */
    public function assistantReview(): HasOne
    {
        return $this->hasOne(AssistantReview::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assistant_favorite_users')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assistant_shared_users')
            ->withTimestamps();
    }

    public function getIsFavoriteAttribute(): bool
    {
        if (\array_key_exists('is_favorite', $this->attributes)) {
            return (bool) $this->attributes['is_favorite'];
        }

        return $this->favoritedByUsers()
            ->where('user_id', Auth::id())
            ->exists();
    }

    protected static function booted(): void
    {
        static::creating(static function (Assistant $assistant): void {
            if (null !== $assistant->creator_id) {
                return;
            }

            $user = Auth::user();

            if (null === $user) {
                return;
            }

            $assistant->creator_id = $user->id;
            $assistant->remixed_creator_id ??= null;
            $assistant->organization_id = app(AssistantOrganizationRepository::class)->getForUser($user)?->id;
        });
    }

    protected function casts(): array
    {
        return [
            'allow_remix' => 'boolean',
            'allow_model_select' => 'boolean',
        ];
    }
}
