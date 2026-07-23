<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Exception;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use Illuminate\Http\Client\Response;

/**
 * Thrown when a converter fails to process a file — either because the remote service returned
 * a non-success HTTP status, because an inner exception was raised during conversion, or because
 * of a local error (e.g. could not create a temporary directory or open a ZIP archive).
 */
class ConversionFailedException extends \RuntimeException implements FileConverterExceptionInterface
{
    /**
     * Wraps an unexpected exception thrown during conversion.
     * Preserves the original exception as the previous cause for stack-trace visibility.
     */
    public static function forThrowable(FileConverterInterface $converter, \Throwable $e): self
    {
        return new self(sprintf(
            'Conversion failed for converter %s: %s',
            get_class($converter),
            $e->getMessage()
        ), previous: $e);
    }

    /**
     * Used when the remote conversion API returned a non-2xx HTTP response.
     * The status code and response body are included in the message for easier diagnosis.
     */
    public static function forFailedResponse(FileConverterInterface $converter, Response $response): self
    {
        return new self(sprintf(
            'Conversion failed for converter %s: Received status code %d with body: %s',
            get_class($converter),
            $response->status(),
            $response->body()
        ));
    }

    /**
     * Used for local errors where no HTTP response or prior exception is available
     * (e.g. failed temp-dir creation or ZIP extraction errors).
     */
    public static function forString(FileConverterInterface $converter, string $message): self
    {
        return new self(sprintf(
            'Conversion failed for converter %s: %s',
            get_class($converter),
            $message
        ));
    }
}
