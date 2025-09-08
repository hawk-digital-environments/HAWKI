<?php

namespace App\Services\Citations\Formatters;

use App\Services\Citations\Contracts\CitationFormatterInterface;

/**
 * Anthropic Citation Formatter
 * 
 * Converts Anthropic's citation format to HAWKI's unified citation format
 */
class AnthropicCitationFormatter implements CitationFormatterInterface
{
    /**
     * Format Anthropic citation data into HAWKI's unified format v1
     */
    public function format(array $providerData, string $messageText): array
    {
        $citations = [];

        // Extract citations from groundingChunks (Anthropic format)
        if (isset($providerData['groundingChunks'])) {
            foreach ($providerData['groundingChunks'] as $index => $chunk) {
                if (isset($chunk['web'])) {
                    $citations[] = [
                        'id' => $index + 1, // 1-based indexing
                        'title' => $chunk['web']['title'] ?? '',
                        'url' => $chunk['web']['uri'] ?? '',
                        'snippet' => '' // Anthropic doesn't provide snippets typically
                    ];
                }
            }
        }

        return [
            'format' => 'hawki_v1',
            'processing_mode' => 'inline', 
            'citations' => $citations,
            'text_processing' => [
                'mode' => 'inline',
                'inline_markers' => true // Frontend will detect [1], [2] patterns
            ],
            'searchMetadata' => [],
            
            // Backwards compatibility - keep old format for transition
            'textSegments' => [[
                'text' => $messageText,
                'citationIds' => [] // Not used for Anthropic pattern-based processing
            ]]
        ];
    }    /**
     * Check if Anthropic provider data contains citations
     */
    public function hasCitations(array $providerData): bool
    {
        return !empty($providerData['groundingChunks']);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'anthropic';
    }
}
