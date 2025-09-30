<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;

class AiAssistantPrompt extends Model
{
    use HasFactory, Filterable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_assistants_prompts';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'category',
        'title',
        'description',
        'content',
        'language',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'language' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this prompt.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return 'ai_assistants_prompts';
    }

    /**
     * Get prompt by category and language.
     */
    public static function getByCategory(string $category, string $language = 'de_DE')
    {
        return self::where('category', $category)
            ->where('language', $language)
            ->get();
    }

    /**
     * Legacy method for backward compatibility - Get system prompt by model type and language.
     */
    public static function getPrompt(string $modelType, string $language): ?string
    {
        $prompt = self::where('category', $modelType)
            ->where('language', $language)
            ->first();

        return $prompt ? $prompt->content : null;
    }

    /**
     * Legacy method for backward compatibility - Set system prompt by model type and language.
     */
    public static function setPrompt(string $modelType, string $language, string $promptText): self
    {
        return self::updateOrCreate(
            [
                'category' => $modelType,
                'language' => $language,
            ],
            [
                'title' => ucfirst($modelType) . ' Prompt',
                'content' => $promptText,
                'description' => 'System prompt for ' . $modelType,
                'created_by' => auth()->id() ?? 1, // Fallback to admin user
            ]
        );
    }

    /**
     * Get the content of the prompt.
     * This method is needed for compatibility with some view components.
     */
    public function getContent(): string
    {
        return $this->content ?? '';
    }
}
