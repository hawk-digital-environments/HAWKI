<?php

namespace App\Models;

use App\Orchid\Filters\SystemImageSearchFilter;
use App\Orchid\Presenters\SystemImagePresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class AppSystemImage extends Model
{
    use AsSource, Filterable, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_system_images';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'active',
    ];

    /**
     * Name of columns to which http sorting can be applied
     *
     * @var array
     */
    protected $allowedSorts = [
        'name',
        'original_name',
        'mime_type',
        'updated_at',
        'created_at',
    ];

    /**
     * Name of columns to which http filtering can be applied
     *
     * @var array
     */
    protected $allowedFilters = [
        SystemImageSearchFilter::class,
    ];

    /**
     * Get the presenter for this model
     */
    public function presenter(): SystemImagePresenter
    {
        return new SystemImagePresenter($this);
    }

    /**
     * Get an image by its name
     *
     * @return \App\Models\AppSystemImage|null
     */
    public static function getByName(string $name)
    {
        // Always fetch from database to avoid cache serialization issues
        $image = self::where('name', $name)
            ->where('active', true)
            ->first();

        return $image;
    }

    /**
     * Get all active system images
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllActive()
    {
        return self::where('active', true)->get();
    }

    /**
     * Clear caches for all system images
     */
    public static function clearCaches()
    {
        $images = self::all();
        foreach ($images as $image) {
            Cache::forget("system_image_{$image->name}");
        }
    }
}
