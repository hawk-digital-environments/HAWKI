<?php
declare(strict_types=1);


namespace App\Services\Storage\Exception;


class CouldNotInflectStoredFieldIdentifierException extends \ValueError implements StorageExceptionInterface
{
    public function __construct(
        string $reason
    )
    {
        parent::__construct("Could not inflect stored field identifier: $reason");
    }
}
