<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPrompt extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'model_type',
        'language',
        'prompt_text',
    ];
    
    /**
     * Get system prompt by model type and language.
     */
    public static function getPrompt(string $modelType, string $language): ?string
    {
        $prompt = self::where('model_type', $modelType)
            ->where('language', $language)
            ->first();
            
        return $prompt ? $prompt->prompt_text : null;
    }
    
    /**
     * Set system prompt by model type and language.
     */
    public static function setPrompt(string $modelType, string $language, string $promptText): self
    {
        return self::updateOrCreate(
            [
                'model_type' => $modelType,
                'language' => $language,
            ],
            [
                'prompt_text' => $promptText
            ]
        );
    }
}
