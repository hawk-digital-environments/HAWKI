<?php
declare(strict_types=1);


namespace App\Services\System\JsonApi;


/**
 * Utility class for serialising sensitive or binary values for JSON API output.
 *
 * Methods here are called from API Resources where the raw value must not be exposed
 * to clients as-is — either because it is a secret (API key masking) or because the
 * client needs an inline data-URL instead of a file-system path.
 */
class ValueSerializer
{
    /**
     * Masks an API key by replacing all but the last 4 characters with asterisks.
     *
     * Keys of 8 characters or fewer are masked entirely. Returns null when the input is
     * null or empty so callers can distinguish "no key set" from "key set but hidden".
     */
    public static function apiKey(?string $apiKey): ?string
    {
        if (!$apiKey) {
            return null;
        }

        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }

        // Return a masked version of the API key, showing only the last 4 characters
        $maskedLength = max(0, strlen($apiKey) - 4);
        return str_repeat('*', $maskedLength) . substr($apiKey, -4);
    }

    /**
     * Converts a local file to an inline data URL (`data:<mime>;base64,<content>`).
     *
     * Returns null when the path is empty or the file does not exist, so callers can
     * omit the field rather than sending a broken data URL.
     */
    public static function localFileAsDataUrl(?string $filePath): ?string
    {
        if (!$filePath || !is_file($filePath)) {
            return null;
        }

        $mimeType = mime_content_type($filePath);
        $base64Data = base64_encode(file_get_contents($filePath));

        return "data:$mimeType;base64,$base64Data";
    }
}
