<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Exceptions;

use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;

/**
 * An embeddings request could not be parsed or violates a formatter-level constraint.
 *
 * Extends the format-agnostic {@see FormatterRequestException} so the controller can
 * render every embeddings error through {@see EmbeddingExceptionInterface}-aware
 * formatters' shared {@see FormatterRequestException} error body.
 */
class InvalidEmbeddingRequestException extends FormatterRequestException
{
    public static function forMissingModel(): self
    {
        return new self(
            'The request is missing the required "model" field.',
            errorCode: 'missing_model',
            param: 'model',
        );
    }

    /**
     * @param list<int|string> $offendingIndices
     */
    public static function forInvalidInput(array $offendingIndices = []): self
    {
        $suffix = [] !== $offendingIndices
            ? \sprintf(' Non-string entries at index/indices: %s.', implode(', ', $offendingIndices))
            : '';

        return new self(
            'The "input" field must be a non-empty string or an array of non-empty strings.' . $suffix,
            errorCode: 'invalid_input',
            param: 'input',
        );
    }

    public static function forUnsupportedEncodingFormat(string $encodingFormat): self
    {
        return new self(
            \sprintf('The encoding format "%s" is not supported; only "float" is available.', $encodingFormat),
            errorCode: 'unsupported_encoding_format',
            param: 'encoding_format',
        );
    }

    public static function forUnknownFormat(string $format): self
    {
        return new self(
            \sprintf('Unknown embeddings format "%s".', $format),
            errorCode: 'unknown_format',
            param: 'format',
        );
    }

    public static function forInvalidDimensions(mixed $dimensions): self
    {
        return new self(
            \sprintf('The "dimensions" field must be a positive integer, got: %s.', get_debug_type($dimensions)),
            errorCode: 'invalid_dimensions',
            param: 'dimensions',
        );
    }
}
