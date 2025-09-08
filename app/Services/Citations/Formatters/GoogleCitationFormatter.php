<?php

namespace App\Services\Citations\Formatters;

use App\Services\Citations\Contracts\CitationFormatterInterface;

/**
 * Google Citation Formatter
 * 
 * Converts Google's grounding metadata format to HAWKI's unified citation format
 */
class GoogleCitationFormatter implements CitationFormatterInterface
{
    /**
     * Format Google citation data into HAWKI's unified format v1
     */
    public function format(array $providerData, string $messageText): array
    {
        $citations = [];
        $textSegments = [];
        $searchMetadata = [];

        // Extract citations from groundingChunks
        if (isset($providerData['groundingChunks'])) {
            foreach ($providerData['groundingChunks'] as $index => $chunk) {
                if (isset($chunk['web'])) {
                    $citations[] = [
                        'id' => $index + 1, // 1-based indexing
                        'title' => $chunk['web']['title'] ?? '',
                        'url' => $chunk['web']['uri'] ?? '',
                        'snippet' => $chunk['web']['snippet'] ?? ''
                    ];
                }
            }
        }

        // Extract text segments from groundingSupports
        if (isset($providerData['groundingSupports'])) {
            foreach ($providerData['groundingSupports'] as $support) {
                if (isset($support['segment']['text']) && isset($support['groundingChunkIndices'])) {
                    $textSegments[] = [
                        'text' => $support['segment']['text'],
                        'citationIds' => array_map(fn($idx) => $idx + 1, $support['groundingChunkIndices']) // Convert to 1-based
                    ];
                }
            }
        }

        // Extract search metadata
        if (isset($providerData['searchEntryPoint'])) {
            $searchMetadata = [
                'query' => $providerData['searchEntryPoint']['searchQuery'] ?? '',
                'renderedContent' => $providerData['searchEntryPoint']['renderedContent'] ?? ''
            ];
        }

        return [
            'format' => 'hawki_v1',
            'processing_mode' => 'segments',
            'citations' => $citations,
            'text_processing' => [
                'mode' => 'segments',
                'text_segments' => $textSegments
            ],
            'searchMetadata' => $searchMetadata,
            
            // Backwards compatibility - keep old format for transition
            'textSegments' => $textSegments // Legacy support
        ];
    }

    /**
     * Check if Google provider data contains citations
     */
    public function hasCitations(array $providerData): bool
    {
        return !empty($providerData['groundingChunks']) || 
               !empty($providerData['groundingSupports']) ||
               !empty($providerData['searchEntryPoint']);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'google';
    }
}
