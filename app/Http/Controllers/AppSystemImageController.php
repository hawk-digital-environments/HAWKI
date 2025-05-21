<?php

namespace App\Http\Controllers;

use App\Models\AppSystemImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;


class AppSystemImageController extends Controller
{
    /**
     * Upload or update a system image
     *
     * @param Request $request
     * @param string $imageName
     * @return array|bool
     */
    public static function uploadImage(Request $request, string $imageName)
    {
        try {
            // Validate the uploaded file
            if (!$request->hasFile('image') || !$request->file('image')->isValid()) {
                return false;
            }
            
            $file = $request->file('image');
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/x-icon'];
            
            if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                return false;
            }
            
            // Get the existing image if any
            $systemImage = AppSystemImage::where('name', $imageName)->first();
            
            // Delete existing file if it exists
            if ($systemImage && File::exists(public_path($systemImage->file_path))) {
                File::delete(public_path($systemImage->file_path));
            }
            
            // Store the new file
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = $imageName . '_' . time() . '.' . $extension;
            $publicPath = 'img/system';
            
            // Ensure the directory exists
            if (!File::exists(public_path($publicPath))) {
                File::makeDirectory(public_path($publicPath), 0755, true);
            }
            
            // Move the file to the public directory
            $file->move(public_path($publicPath), $fileName);
            $filePath = $publicPath . '/' . $fileName;
            
            // Update or create the database record
            $systemImage = AppSystemImage::updateOrCreate(
                ['name' => $imageName],
                [
                    'file_path' => $filePath,
                    'original_name' => $originalName,
                    'mime_type' => $file->getMimeType(),
                    'active' => true
                ]
            );
            
            // Clear cache
            Cache::forget("system_image_{$imageName}");
            
            return [
                'success' => true,
                'image' => $systemImage,
                'url' => asset($filePath)
            ];
            
        } catch (\Exception $e) {
            Log::error("Error uploading system image: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset image to default
     * 
     * @param string $imageName
     * @return bool
     */
    public static function resetToDefault(string $imageName)
    {
        try {
            $defaultImages = [
                'favicon' => 'favicon.ico',
                'logo_svg' => 'img/logo.svg'
            ];
            
            if (!isset($defaultImages[$imageName])) {
                return false;
            }
            
            $defaultPath = $defaultImages[$imageName];
            
            // Check if default image exists
            if (!File::exists(public_path($defaultPath))) {
                return false;
            }
            
            // Get the existing image if any
            $systemImage = AppSystemImage::where('name', $imageName)->first();
            
            // Delete existing file if it exists and is different from default
            if ($systemImage && 
                File::exists(public_path($systemImage->file_path)) &&
                $systemImage->file_path !== $defaultPath) {
                File::delete(public_path($systemImage->file_path));
            }
            
            // Update or create the database record to point to the default file
            AppSystemImage::updateOrCreate(
                ['name' => $imageName],
                [
                    'file_path' => $defaultPath,
                    'original_name' => basename($defaultPath),
                    'mime_type' => File::mimeType(public_path($defaultPath)),
                    'active' => true
                ]
            );
            
            // Clear cache
            Cache::forget("system_image_{$imageName}");
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error resetting system image: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all system image caches
     */
    public static function clearCaches()
    {
        AppSystemImage::clearCaches();
    }
}