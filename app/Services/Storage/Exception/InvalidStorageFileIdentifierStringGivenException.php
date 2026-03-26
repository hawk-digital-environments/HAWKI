<?php
declare(strict_types=1);


namespace App\Services\Storage\Exception;


class InvalidStorageFileIdentifierStringGivenException extends \InvalidArgumentException implements StorageExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct("The given string '$id' is not a valid storage file identifier. Expected format: 'category-uuid[.extension]'.");
    }
}
