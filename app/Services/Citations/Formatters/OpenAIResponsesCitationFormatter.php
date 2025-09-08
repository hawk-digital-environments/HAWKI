<?php

namespace App\Services\Citations\Formatters;

use App\Services\Citations\Contracts\CitationFormatterInterface;

/**
 * OpenAI Responses Citation Formatter
 *
 * Converts OpenAI Responses API citation format to HAWKI's unified citation format
 */
class OpenAIResponsesCitationFormatter implements CitationFormatterInterface
{
    /**
     * Get the provider name this formatter handles
     */
    public function getProviderName(): string
    {
        return 'openai_responses';
    }

    /**
     * Format OpenAI Responses citation data into HAWKI's unified format v1
     */
    public function format(array $providerData, string $messageText): array
    {
        $citations = [];
        $textSegments = [];
        $searchMetadata = [];
        $processedText = $messageText; // Initialize with original text

        // Extract citations from annotations (inline citations with start/end indices)
        if (isset($providerData['citations'])) {
            $citationIdMap = [];
            $citationId = 1;

            foreach ($providerData['citations'] as $annotation) {
                $url = $annotation['url'] ?? '';
                $title = $annotation['title'] ?? '';

                // Create unique citation if not exists
                $key = $url.'|'.$title;
                if (! isset($citationIdMap[$key])) {
                    $citationIdMap[$key] = $citationId;
                    $citations[] = [
                        'id' => $citationId,
                        'title' => $title,
                        'url' => $url,
                        'snippet' => '', // OpenAI Responses doesn't provide snippets in annotations
                    ];
                    $citationId++;
                }
            }

            // Replace markdown links with citation numbers in message text
            $processedText = $this->replaceMarkdownLinksWithCitations($messageText, $providerData['citations'], $citationIdMap);

            // Build text segments from annotations (similar to Google's approach)
            if (! empty($providerData['citations'])) {
                // Create segments based on citation ranges using the processed text
                $segments = $this->buildTextSegments($providerData['citations'], $processedText, $citationIdMap);
                $textSegments = array_merge($textSegments, $segments);
            }
        }

        // Handle annotations format (alternative to citations)
        if (isset($providerData['annotations'])) {
            $citationIdMap = [];
            
            // Extract source metadata first 
            if (isset($providerData['sourceMetadata'])) {
                foreach ($providerData['sourceMetadata'] as $index => $source) {
                    $searchMetadata[] = [
                        'id' => $index + 1,
                        'url' => $source['url'] ?? '',
                        'title' => $source['title'] ?? '',
                        'description' => $source['description'] ?? '',
                    ];
                    
                    // Map citations for replacement (1-based indexing)
                    $citationIdMap[$index + 1] = $index + 1;
                }
            }

            // Create citations from source metadata  
            foreach ($searchMetadata as $source) {
                $citations[] = [
                    'id' => $source['id'],
                    'title' => $source['title'],
                    'url' => $source['url'],
                    'snippet' => $source['description'],
                ];
            }

            // Replace markdown links with citation numbers
            $processedText = $this->replaceMarkdownLinksWithCitations($messageText, $citationIdMap);

            // For now, use the entire processed text as one segment since annotation indices
            // don't match after text replacement. In the future, we could recalculate indices.
            $textSegments = [[
                'text' => $processedText,
                'citationIds' => array_keys($citationIdMap), // All citation IDs
            ]];
        }

        // Fallback: Extract citations from webSearchSources (if annotations are not available)
        if (empty($citations) && isset($providerData['webSearchSources'])) {
            foreach ($providerData['webSearchSources'] as $index => $source) {
                $citations[] = [
                    'id' => $index + 1, // 1-based indexing
                    'title' => $source['title'] ?? '',
                    'url' => $source['url'] ?? '',
                    'snippet' => $source['snippet'] ?? '',
                ];
            }
        }

        // Extract search metadata from queries
        if (isset($providerData['searchQueries']) && ! empty($providerData['searchQueries'])) {
            $searchMetadata = [
                'queries' => $providerData['searchQueries'],
                'query' => implode('; ', $providerData['searchQueries']), // Combined query for compatibility
            ];
        }

        // Determine processing mode based on available data
        $processingMode = ! empty($textSegments) ? 'segments' : 'inline';

        return [
            'format' => 'hawki_v1',
            'processing_mode' => $processingMode,
            'citations' => $citations,
            'text_processing' => [
                'mode' => $processingMode,
                'text_segments' => $textSegments,
                'inline_markers' => $processingMode === 'inline', // Frontend will detect citation patterns
            ],
            'searchMetadata' => $searchMetadata,

            // Backwards compatibility - keep old format for transition
            'textSegments' => ! empty($textSegments) ? $textSegments : [[
                'text' => $processedText,
                'citationIds' => [], // Not used for inline processing
            ]],
        ];
    }

    /**
     * Build text segments from citation annotations (similar to Google's segments)
     */
    private function buildTextSegments(array $annotations, string $messageText, array $citationIdMap): array
    {
        $segments = [];
        $lastEnd = 0;

        // Group annotations by their position (start_index, end_index)
        $groupedAnnotations = [];
        foreach ($annotations as $annotation) {
            $startIndex = $annotation['start_index'] ?? null;
            $endIndex = $annotation['end_index'] ?? null;
            
            if ($startIndex === null || $endIndex === null) {
                continue;
            }
            
            $key = $startIndex . '-' . $endIndex;
            if (!isset($groupedAnnotations[$key])) {
                $groupedAnnotations[$key] = [
                    'start_index' => $startIndex,
                    'end_index' => $endIndex,
                    'citation_ids' => []
                ];
            }
            
            // Add citation ID for this annotation
            $citationKey = ($annotation['url'] ?? '').'|'.($annotation['title'] ?? '');
            $citationId = $citationIdMap[$citationKey] ?? null;
            if ($citationId && !in_array($citationId, $groupedAnnotations[$key]['citation_ids'])) {
                $groupedAnnotations[$key]['citation_ids'][] = $citationId;
            }
        }

        // Sort grouped annotations by start index
        uasort($groupedAnnotations, fn ($a, $b) => $a['start_index'] <=> $b['start_index']);

        foreach ($groupedAnnotations as $group) {
            $startIndex = $group['start_index'];
            $endIndex = $group['end_index'];
            $citationIds = $group['citation_ids'];

            // Add non-cited text before this annotation
            if ($startIndex > $lastEnd) {
                $segments[] = [
                    'text' => substr($messageText, $lastEnd, $startIndex - $lastEnd),
                    'citationIds' => [],
                ];
            }

            // Add cited text segment with all citation IDs for this position
            $citedText = substr($messageText, $startIndex, $endIndex - $startIndex);

            if (!empty($citationIds)) {
                $segments[] = [
                    'text' => $citedText,
                    'citationIds' => $citationIds,
                ];
            }

            $lastEnd = $endIndex;
        }

        // Add remaining non-cited text
        if ($lastEnd < strlen($messageText)) {
            $segments[] = [
                'text' => substr($messageText, $lastEnd),
                'citationIds' => [],
            ];
        }

        return $segments;
    }

    /**
     * Replace markdown links with citation numbers
     */
    private function replaceMarkdownLinksWithCitations(string $text, array $citationIdMap): string
    {
        // Use a simple incremental replacement for now
        $citationIndex = 1;
        
        // Process patterns from most specific to least specific to avoid conflicts:
        
        // 1. First, handle markdown links in parentheses: ([text](url))
        $text = preg_replace_callback('/\(\[([^\]]+)\]\(([^)]+)\)\)/', function ($matches) use (&$citationIndex) {
            $result = "[{$citationIndex}]";
            $citationIndex++;
            return $result;
        }, $text);
        
        // 2. Then handle standalone markdown links: [text](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) use (&$citationIndex) {
            $result = "[{$citationIndex}]";
            $citationIndex++;
            return $result;
        }, $text);
        
        // 3. Finally handle domain citations in parentheses: (domain.com, ...)
        $text = preg_replace_callback('/\(([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})[^)]*\)/', function ($matches) use (&$citationIndex) {
            $result = "[{$citationIndex}]";
            $citationIndex++;
            return $result;
        }, $text);
        
        return $text;
    }

    /**
     * Check if OpenAI Responses provider data contains citations
     */
    public function hasCitations(array $providerData): bool
    {
        return !empty($providerData['annotations']) || 
               !empty($providerData['citations']) || 
               !empty($providerData['webSearchSources']) ||
               !empty($providerData['sourceMetadata']) ||
               !empty($providerData['searchQueries']);
    }
}
