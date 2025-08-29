<?php
declare(strict_types=1);


namespace App\Services\Message\Value;


readonly class LegacyMessageIdInfo
{
    public function __construct(
        /**
         * @var int The internal ID of the message in our database. This is the auto-increment primary key.
         */
        public int    $id,
        /**
         * @var string The legacy message ID in the 12.001 format. This is used for compatibility with older parts.
         */
        public string $legacyMessageId,
    )
    {
    }
}
