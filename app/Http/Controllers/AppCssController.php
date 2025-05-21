<?php

namespace App\Http\Controllers;

use App\Models\AppCss;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AppCssController extends Controller
{
    /**
     * Get CSS by name
     *
     * @param string $name
     * @return string
     */
    public function getByName(string $name)
    {
        // Try to get from cache first
        $cacheKey = "css_{$name}";
        $css = Cache::get($cacheKey);
        
        if (!$css) {
            // Get from database if not in cache
            $css = AppCss::getByName($name);
            
            // Store in cache if found
            if ($css) {
                Cache::put($cacheKey, $css, now()->addDay());
            } else {
                $css = '/* CSS not found */';
            }
        }
        
        return response($css)->header('Content-Type', 'text/css');
    }
    
    /**
     * Update CSS in database
     *
     * @param string $name
     * @param string $content
     * @return bool
     */
    public static function updateCss(string $name, string $content): bool
    {
        try {
            AppCss::updateOrCreate(
                ['name' => $name],
                ['content' => $content]
            );
            
            // Clear cache
            Cache::forget("css_{$name}");
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Error updating CSS {$name}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all CSS caches
     */
    public static function clearCaches(): void
    {
        $cssItems = AppCss::all();
        
        foreach ($cssItems as $css) {
            Cache::forget("css_{$css->name}");
        }
    }
}
