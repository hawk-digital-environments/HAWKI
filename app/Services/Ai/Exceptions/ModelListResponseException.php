<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


/**
 * Thrown when a provider's model-list API response cannot be parsed into the expected structure.
 *
 * Provider adapters call the model-list endpoint to discover available models.  If the response
 * body is malformed or has an unexpected shape, one of the factory methods below is used to
 * produce a descriptive exception that includes the raw body or extracted value for diagnosis.
 */
class ModelListResponseException extends \RuntimeException implements AiExceptionInterface
{
    /**
     * Thrown when the raw response body is not valid JSON.
     *
     * The full body is included in the message to aid diagnosis; callers should
     * only log this at a level where the payload size is acceptable.
     *
     * @param \Throwable $previous The JSON parse error returned by the decoder.
     */
    public static function forInvalidJson(string $body, \Throwable $previous): self
    {
        return new self(
            sprintf('Failed to parse model list response as JSON: %s', $body),
            0,
            $previous
        );
    }

    /**
     * Thrown when the top-level decoded JSON value is not an array (e.g. the API returned a
     * plain string or a scalar instead of an object/array).
     *
     * @param string $type The PHP type name returned by {@see get_debug_type()}.
     */
    public static function forNonArrayResponse(string $type): self
    {
        return new self(sprintf('Model list response is not an array, got %s.', $type));
    }

    /**
     * Thrown when a nested key extracted from the response body at a dot-notation path is not
     * an array.  Used by adapters that need to reach into a wrapper object to find the model
     * list (e.g. `"data"` in an OpenAI-style envelope).
     *
     * @param string $path  The dot-notation extraction path that was used (e.g. `"data"`).
     * @param mixed  $value The actual value found at that path, JSON-encoded for readability.
     */
    public static function forNonArrayExtract(string $path, mixed $value): self
    {
        return new self(sprintf(
            'Extracted data at path "%s" is not an array, got: %s',
            $path,
            json_encode($value)
        ));
    }
}
