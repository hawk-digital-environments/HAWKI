<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;

class AppSystemText extends Model
{
    use AsSource, Filterable, HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'content_key',    // Geändert von 'text_key'
        'language',
        'content',        // Geändert von 'text_value'
    ];

    /**
     * Name of columns to which http filtering can be applied
     */
    protected $allowedFilters = [
        'content_key' => Like::class,
        'language' => Where::class,
        'content' => Like::class,
        'created_at' => WhereDateStartEnd::class,
        'updated_at' => WhereDateStartEnd::class,
    ];

    /**
     * Name of columns to which http sorting can be applied
     */
    protected $allowedSorts = [
        'content_key',
        'language',
        'content',
        'updated_at',
        'created_at',
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
                'content' => $content,
            ]
        );
    }

    /**
     * Set system text by key and language (only creates, no updates) - for seeders.
     */
    public static function setTextIfNotExists(string $contentKey, string $language, string $content): self
    {
        return self::firstOrCreate(
            [
                'content_key' => $contentKey,
                'language' => $language,
            ],
            [
                'content' => $content,
            ]
        );
    }
}
