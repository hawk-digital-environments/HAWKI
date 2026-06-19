<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class AttachmentFormattingSkippedException extends \RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
