<?php

namespace App\Models;

use App\Orchid\Filters\CssSearchFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class AppCss extends Model
{
    use AsSource, Filterable, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_css';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'content',
        'active',
    ];

    /**
     * Name of columns to which http sorting can be applied
     *
     * @var array
     */
    protected $allowedSorts = [
        'name',
        'description',
        'active',
        'updated_at',
        'created_at',
    ];

    /**
     * Name of columns to which http filtering can be applied
     *
     * @var array
     */
    protected $allowedFilters = [
        CssSearchFilter::class,
    ];

    /**
     * Get active CSS by name
     */
    public static function getByName(string $name): ?string
    {
        $css = self::where('name', $name)
            ->where('active', true)
            ->first();

        return $css ? $css->content : null;
    }

    /**
     * Get all active CSS files
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllActive()
    {
        return self::where('active', true)->get();
    }
}
