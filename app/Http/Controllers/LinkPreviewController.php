<?php

namespace App\Http\Controllers;

use App\Services\ExternalContent\ExternalImageProxy;
use App\Services\ExternalContent\FavIconProxy;
use App\Services\ExternalContent\WebsiteMetadataLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Validation\Rule;

class LinkPreviewController extends Controller
{
    public function getFavicon(Request $request, FavIconProxy $favIconLoader): IlluminateResponse
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');

        $icon = $favIconLoader->getFaviconOf($url);

        return response($icon->content, 200)
            ->header('Content-Type', $icon->mimeType);
    }

    public function getImage(Request $request, ExternalImageProxy $proxy): IlluminateResponse
    {
        $request->validate([
            'url' => [
                'required',
                Rule::anyOf([
                    'url',
                    'starts_with:fallback_'
                ])
            ]
        ]);

        $url = $request->input('url');

        // If we want to generate a fallback image, we can use a special URL parameter like "fallback_" prefix
        /* @see \App\Services\ExternalContent\WebsiteMetadataLoader::makeFallbackMetadata */
        if (str_starts_with('fallback_', $url)) {
            $image = $proxy->makeFallbackImage();
        } else {
            $image = $proxy->get($url);
        }

        return response($image->content, 200)
            ->header('Content-Type', $image->mimeType);
    }

    /**
     * Fetch link metadata for preview
     */
    public function getPreview(Request $request, WebsiteMetadataLoader $metadataLoader): JsonResponse
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');

        return response()->json($metadataLoader->load($url));
    }
}
