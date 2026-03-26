<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Exception;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use Illuminate\Http\Client\Response;

class ConversionFailedException extends \RuntimeException implements FileConverterExceptionInterface
{
    public static function forThrowable(FileConverterInterface $converter, \Throwable $e): self
    {
        return new self(sprintf(
            'Conversion failed for converter %s: %s',
            get_class($converter),
            $e->getMessage()
        ), previous: $e);
    }

    public static function forFailedResponse(FileConverterInterface $converter, Response $response): self
    {
        return new self(sprintf(
            'Conversion failed for converter %s: Received status code %d with body: %s',
            get_class($converter),
            $response->status(),
            $response->body()
        ));
    }

    public static function forString(FileConverterInterface $converter, string $message): self
    {
        return new self(sprintf(
            'Conversion failed for converter %s: %s',
            get_class($converter),
            $message
        ));
    }
}
