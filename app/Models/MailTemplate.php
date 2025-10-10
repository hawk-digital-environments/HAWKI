<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class MailTemplate extends Model
{
    use AsSource, Filterable;

    protected $table = 'mail_templates';

    protected $fillable = [
        'type',
        'language',
        'description',
        'subject',
        'body',
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
        \App\Orchid\Filters\Customization\MailTemplateSearchFilter::class,
    ];

    /**
     * The attributes for which you can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
        'type',
        'language',
        'description',
        'subject',
        'updated_at',
        'created_at',
    ];

    public static function findByType(string $type, string $language = 'de'): ?MailTemplate
    {
        return static::where('type', $type)
            ->where('language', $language)
            ->first();
    }

    public static function getAvailableTypes(): array
    {
        return static::distinct('type')->pluck('type')->toArray();
    }
}
