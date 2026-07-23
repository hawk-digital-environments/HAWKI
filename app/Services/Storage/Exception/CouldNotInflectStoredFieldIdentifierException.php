<?php
declare(strict_types=1);


namespace App\Services\Storage\Exception;


/**
 * Thrown when a {@see StoredFileIdentifier} cannot be derived from a model (e.g. a User with no avatar_id set,
 * or an Attachment that has no category or an unrecognised category value).
 */
class CouldNotInflectStoredFieldIdentifierException extends \ValueError implements StorageExceptionInterface
{
    public function __construct(string $reason)
    {
        parent::__construct("Could not inflect stored field identifier: $reason");
    }
}
