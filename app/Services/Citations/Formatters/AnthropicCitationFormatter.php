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
     * Format Anthropic citation data into HAWKI's unified format
     */
    public function format(array $providerData, string $messageText): array
    {
        $citations = [];
        $textSegments = [];
        $searchMetadata = [];

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

        // For Anthropic inline citations, create a simple text segment structure
        // The frontend will detect and replace [1], [2] patterns automatically
        $textSegments = $this->extractTextSegmentsFromMessage($messageText, count($citations));

        return [
            'citations' => $citations,
            'textSegments' => $textSegments,
            'searchMetadata' => $searchMetadata
        ];
    }    /**
     * Extract text segments for Anthropic inline citations
     * For Anthropic, we provide the full text and let the frontend handle [1], [2] replacement
     */
    private function extractTextSegmentsFromMessage(string $messageText, int $totalCitations): array
    {
        // For Anthropic inline citations, create a single segment with the full text
        // The frontend will automatically detect and replace [1], [2] patterns
        return [
            [
                'text' => $messageText,
                'citationIds' => [] // Not used for Anthropic pattern-based processing
            ]
        ];
    }

    /**
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
