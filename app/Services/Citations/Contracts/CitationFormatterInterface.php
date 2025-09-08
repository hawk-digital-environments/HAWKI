<?php

namespace App\Services\Citations\Contracts;

/**
 * Interface for formatting provider-specific citation data into HAWKI's unified format
 */
interface CitationFormatterInterface
{
    /**
     * Format provider-specific citation data into HAWKI's unified citation format
     *
     * @param  array  $providerData  Raw citation data from the AI provider
     * @param  string  $messageText  The AI-generated message text
     * @return array Formatted citation data in HAWKI format
     */
    public function format(array $providerData, string $messageText): array;

    /**
     * Check if the provider data contains citations
     *
     * @param  array  $providerData  Raw data from the AI provider
     * @return bool True if citations are present
     */
    public function hasCitations(array $providerData): bool;

    /**
     * Get the provider name this formatter handles
     *
     * @return string Provider name (e.g., 'google', 'anthropic')
     */
    public function getProviderName(): string;
}
