<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;

class AppLocalizedText extends Model
{
    use AsSource, Filterable, HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'content_key',
        'language',
        'content',
        'description',
    ];

    /**
     * Name of columns to which http filtering can be applied
     */
    protected $allowedFilters = [
        'content_key' => Like::class,
        'content' => Like::class,
        'description' => Like::class,
        'language' => Where::class,
        'created_at' => WhereDateStartEnd::class,
        'updated_at' => WhereDateStartEnd::class,
    ];

    /**
     * Name of columns to which http sorting can be applied
     */
    protected $allowedSorts = [
        'content_key',
        'content',
        'description',
        'language',
        'created_at',
        'updated_at',
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
                'content' => $content,
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
                'content' => $content,
            ]
        );
    }
}
