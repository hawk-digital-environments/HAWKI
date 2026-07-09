<?php
declare(strict_types=1);


namespace App\Services\Storage\Exception;


/**
 * Thrown when a string passed to {@see StoredFileIdentifier::fromString()} does not match the expected
 * `category-uuid[.extension]` format (e.g. missing the dash separator or carrying an unknown category prefix).
 */
class InvalidStorageFileIdentifierStringGivenException extends \InvalidArgumentException implements StorageExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct("The given string '$id' is not a valid storage file identifier. Expected format: 'category-uuid[.extension]'.");
    }
}
