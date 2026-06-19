<?php
declare(strict_types=1);


namespace App\Services\Api\Value;


readonly class ApiRequestFieldConfig
{
    public function __construct(
        /**
         * The name of the field that contains the message ID.
         * The message id is a string in the format "12.001".
         */
        public string $messageIdField = 'message_id',
        /**
         * The name of the field that contains the thread ID.
         * The thread id is a string in the format "12".
         * This is the first part of the parent message ID (before the dot).
         */
        public string $threadIdField = 'threadId',
        /**
         * The name of the field that contains the message ID in v2 requests.
         * The message id is an integer, which is the internal database ID of the message.
         * @var string
         */
        public string $v2MessageIdField = 'id',
        /**
         * The name of the field that contains the thread ID in v2 requests.
         * The thread id is an integer, which is the internal database ID of the parent message.
         * @var string
         */
        public string $v2ThreadIdField = 'thread_id',
        /**
         * In some cases the v1 format expects the message ID to be the internal ID.
         * This is not ideal, but we need to support it for backwards compatibility.
         * If this is set to true, the $messageIdField is assumed to be the internal ID instead of the "12.001" format.
         * @var bool
         */
        public bool   $v2MessageIdAssumesInternalId = false,
        /**
         * The name of the field that contains the API version.
         * If this field is not present, the request is assumed to be in the old format.
         * @var string
         */
        public string $versionField = 'version',
    )
    {
    }
}
