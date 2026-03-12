<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkPreviewController extends Controller
{
    /**
     * Fetch link metadata for preview
     */
    public function getPreview(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');

        try {
            // Fetch the page content
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; HAWKI Link Preview Bot/1.0)',
                ])
                ->get($url);

            if (!$response->successful()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to fetch URL'
                ], 400);
            }

            $html = $response->body();
            $metadata = $this->parseMetadata($html, $url);

            return response()->json($metadata);

        } catch (\Exception $e) {
            Log::error('Link preview error: ' . $e->getMessage());

            return response()->json([
                'error' => true,
                'message' => 'Failed to fetch preview'
            ], 500);
        }
    }

    /**
     * Parse HTML to extract metadata
     */
    private function parseMetadata($html, $url)
    {
        $metadata = [
            'url' => $url,
            'title' => null,
            'description' => null,
            'image' => null,
            'favicon' => null,
            'domain' => parse_url($url, PHP_URL_HOST),
        ];

        // Use DOMDocument to parse HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Extract Open Graph tags
        $ogTags = [
            'og:title' => 'title',
            'og:description' => 'description',
            'og:image' => 'image',
        ];

        foreach ($ogTags as $property => $key) {
            $nodes = $xpath->query("//meta[@property='$property']");
            if ($nodes->length > 0) {
                $content = $nodes->item(0)->getAttribute('content');
                if (!empty($content)) {
                    $metadata[$key] = $content;
                }
            }
        }

        // Fallback to Twitter Card tags
        if (!$metadata['title']) {
            $nodes = $xpath->query("//meta[@name='twitter:title']");
            if ($nodes->length > 0) {
                $metadata['title'] = $nodes->item(0)->getAttribute('content');
            }
        }

        if (!$metadata['description']) {
            $nodes = $xpath->query("//meta[@name='twitter:description']");
            if ($nodes->length > 0) {
                $metadata['description'] = $nodes->item(0)->getAttribute('content');
            }
        }

        if (!$metadata['image']) {
            $nodes = $xpath->query("//meta[@name='twitter:image']");
            if ($nodes->length > 0) {
                $metadata['image'] = $nodes->item(0)->getAttribute('content');
            }
        }

        // Fallback to standard meta tags
        if (!$metadata['title']) {
            $nodes = $xpath->query("//title");
            if ($nodes->length > 0) {
                $metadata['title'] = $nodes->item(0)->textContent;
            }
        }

        if (!$metadata['description']) {
            $nodes = $xpath->query("//meta[@name='description']");
            if ($nodes->length > 0) {
                $metadata['description'] = $nodes->item(0)->getAttribute('content');
            }
        }

        // Get favicon
        $faviconNodes = $xpath->query("//link[@rel='icon' or @rel='shortcut icon']");
        if ($faviconNodes->length > 0) {
            $favicon = $faviconNodes->item(0)->getAttribute('href');
            $metadata['favicon'] = $this->resolveUrl($url, $favicon);
        } else {
            // Default to Google favicon service
            $metadata['favicon'] = "https://www.google.com/s2/favicons?domain={$metadata['domain']}&sz=32";
        }

        // Resolve relative image URL
        if ($metadata['image'] && !filter_var($metadata['image'], FILTER_VALIDATE_URL)) {
            $metadata['image'] = $this->resolveUrl($url, $metadata['image']);
        }

        return $metadata;
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl($baseUrl, $relativeUrl)
    {
        // If already absolute, return as is
        if (filter_var($relativeUrl, FILTER_VALIDATE_URL)) {
            return $relativeUrl;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        // Handle protocol-relative URLs
        if (substr($relativeUrl, 0, 2) === '//') {
            return $scheme . ':' . $relativeUrl;
        }

        // Handle absolute paths
        if (substr($relativeUrl, 0, 1) === '/') {
            return $scheme . '://' . $host . $relativeUrl;
        }

        // Handle relative paths
        $basePath = $base['path'] ?? '/';
        $basePath = dirname($basePath);
        return $scheme . '://' . $host . $basePath . '/' . $relativeUrl;
    }
}
