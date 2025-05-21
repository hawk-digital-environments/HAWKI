<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSystemText extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'content_key',    // Geändert von 'text_key'
        'language',
        'content',        // Geändert von 'text_value'
    ];
    
    /**
     * Get system text by key and language.
     */
    public static function getText(string $contentKey, string $language): ?string
    {
        $text = self::where('content_key', $contentKey)
            ->where('language', $language)
            ->first();
            
        return $text ? $text->content : null;
    }
    
    /**
     * Set system text by key and language.
     */
    public static function setText(string $contentKey, string $language, string $content): self
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
}
