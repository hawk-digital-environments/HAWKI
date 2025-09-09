<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppLocalizedText extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'content_key',
        'language',
        'content',
    ];
    
    /**
     * Get localized content by key and language.
     */
    public static function getContent(string $contentKey, string $language): ?string
    {
        $content = self::where('content_key', $contentKey)
            ->where('language', $language)
            ->first();
            
        return $content ? $content->content : null;
    }
    
    /**
     * Set localized content by key and language.
     */
    public static function setContent(string $contentKey, string $language, string $content): self
    {
        return self::updateOrCreate(
            [
                'content_key' => $contentKey,
                'language' => $language,
            ],
            [
                'content' => $content
            ]
        );
    }
    
    /**
     * Set localized content by key and language (only creates, no updates) - for seeders.
     */
    public static function setContentIfNotExists(string $contentKey, string $language, string $content): self
    {
        return self::firstOrCreate(
            [
                'content_key' => $contentKey,
                'language' => $language,
            ],
            [
                'content' => $content
            ]
        );
    }
}
