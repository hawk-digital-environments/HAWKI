<?php

namespace App\Services\Citations;

use App\Services\Citations\Contracts\CitationFormatterInterface;
use App\Services\Citations\Formatters\GoogleCitationFormatter;
use App\Services\Citations\Formatters\AnthropicCitationFormatter;

/**
 * Unified Citation Service for HAWKI
 * 
 * Converts provider-specific citation formats into a standardized HAWKI format
 */
class CitationService
{
    /**
     * @var array<string, CitationFormatterInterface>
     */
    private array $formatters = [];

    public function __construct()
    {
        $this->registerDefaultFormatters();
    }

    /**
     * Format provider-specific citation data into HAWKI's unified format
     *
     * @param string $provider Provider name (e.g., 'google', 'anthropic')
     * @param array $providerData Raw citation data from the provider
     * @param string $messageText The AI-generated message text
     * @return array|null Formatted citation data or null if no citations
     */
    public function formatCitations(string $provider, array $providerData, string $messageText): ?array
    {
        $formatter = $this->getFormatter($provider);
        
        if (!$formatter || !$formatter->hasCitations($providerData)) {
            return null;
        }

        return $formatter->format($providerData, $messageText);
    }

    /**
     * Register a citation formatter for a specific provider
     */
    public function registerFormatter(CitationFormatterInterface $formatter): void
    {
        $this->formatters[$formatter->getProviderName()] = $formatter;
    }

    /**
     * Get formatter for a specific provider
     */
    private function getFormatter(string $provider): ?CitationFormatterInterface
    {
        return $this->formatters[$provider] ?? null;
    }

    /**
     * Register default formatters
     */
    private function registerDefaultFormatters(): void
    {
        $this->registerFormatter(new GoogleCitationFormatter());
        $this->registerFormatter(new AnthropicCitationFormatter());
    }

    /**
     * Get HAWKI Citation Format Schema for documentation
     * 
     * @return array The standard format structure
     */
    public static function getStandardFormat(): array
    {
        return [
            'citations' => [
                [
                    'id' => 'int - Unique citation ID (1-based)',
                    'title' => 'string - Source title',
                    'url' => 'string - Source URL',
                    'snippet' => 'string - Relevant text snippet (optional)'
                ]
            ],
            'textSegments' => [
                [
                    'text' => 'string - Text segment that needs citations',
                    'citationIds' => 'array - Array of citation IDs that apply to this text'
                ]
            ],
            'searchMetadata' => [
                'query' => 'string - Original search query (optional)',
                'renderedContent' => 'string - Provider-specific rendered content (optional)'
            ]
        ];
    }
}
