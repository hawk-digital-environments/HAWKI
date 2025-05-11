<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSystemImage extends Model
{
    use HasFactory;

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
        'file_path',
        'original_name',
        'mime_type',
        'active'
    ];

    /**
     * Get an image by its name
     *
     * @param string $name
     * @return \App\Models\AppSystemImage|null
     */
    public static function getByName(string $name)
    {
        // Try to get from cache
        $cacheKey = "system_image_{$name}";
        $image = Cache::get($cacheKey);
        
        if (!$image) {
            $image = self::where('name', $name)
                ->where('active', true)
                ->first();
                
            // Cache the result if found
            if ($image) {
                Cache::put($cacheKey, $image, now()->addDay());
            }
        }
        
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
