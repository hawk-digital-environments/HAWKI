<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{

    /// Stores image in storage/images
    /// NOTE: lateron there should be specified folders for each kind of image (user avatar, room avatar, etc.)
    public function storeImage($base64Image, $subFolder)
    {
        // Check if it's a valid base64 encoded image
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
            $imageData = base64_decode($imageData);

            // Get the image extension from the type
            $extension = strtolower($type[1]); // jpeg, png, gif, etc.

            // Ensure the extension is valid
            if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
                return response()->json([
                    'success' => false,
                    'response' => 'Invalid image type'
                ]);
            }

            // Create a filename for the image
            $fileName = uniqid() . '.' . $extension;

            // Store the image on the server (public folder in this case)
            $filePath = $subFolder . '/' . $fileName;

            Storage::disk('public')->put($filePath, $imageData);

            return response()->json([
                'success' => true,
                'response' =>'Image stored successfully', 
                'fileName' => $fileName
            ]);
        }

        return response()->json([
            'success' => true,
            'response' => 'Invalid image data'
        ]);
    }
}
