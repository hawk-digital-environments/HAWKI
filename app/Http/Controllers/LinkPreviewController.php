<?php

namespace App\Http\Controllers;

use App\Services\System\Http\UrlResolver;
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
                ->getSsrfSafe($url);

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
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                $content = $node->getAttribute('content');
                if (!empty($content)) {
                    $metadata[$key] = $content;
                }
            }
        }

        // Fallback to Twitter Card tags
        if (!$metadata['title']) {
            $nodes = $xpath->query("//meta[@name='twitter:title']");
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                $metadata['title'] = $node->getAttribute('content');
            }
        }

        if (!$metadata['description']) {
            $nodes = $xpath->query("//meta[@name='twitter:description']");
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                $metadata['description'] = $node->getAttribute('content');
            }
        }

        if (!$metadata['image']) {
            $nodes = $xpath->query("//meta[@name='twitter:image']");
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                $metadata['image'] = $node->getAttribute('content');
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
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                $metadata['description'] = $node->getAttribute('content');
            }
        }

        // Get favicon
        $faviconNodes = $xpath->query("//link[@rel='icon' or @rel='shortcut icon']");
        $node = $faviconNodes->item(0);
        if ($node instanceof \DOMElement) {
            $favicon = $node->getAttribute('href');
            $metadata['favicon'] = UrlResolver::resolve($url, $favicon);
        } else {
            $metadata['favicon'] = "https://www.google.com/s2/favicons?domain={$metadata['domain']}&sz=32";
        }

        // Resolve relative image URL
        if ($metadata['image'] && !filter_var($metadata['image'], FILTER_VALIDATE_URL)) {
            $metadata['image'] = UrlResolver::resolve($url, $metadata['image']);
        }

        return $metadata;
    }
}
