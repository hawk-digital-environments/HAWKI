<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class AttachmentFormattingSkippedException extends \RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
