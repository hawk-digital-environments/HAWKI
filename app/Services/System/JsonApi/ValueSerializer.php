<?php
declare(strict_types=1);


namespace App\Services\System\JsonApi;


class ValueSerializer
{
    /**
     * Masks an API key by replacing all but the last 4 characters with asterisks.
     *
     * @param string|null $apiKey The API key to mask.
     * @return string|null The masked API key, or null if the input was null.
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
     * Converts a local file to a data URL.
     *
     * @param string|null $filePath The path to the local file.
     * @return string|null The data URL representation of the file, or null if the file does not exist.
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
