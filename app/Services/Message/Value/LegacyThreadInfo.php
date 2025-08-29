<?php
declare(strict_types=1);


namespace App\Services\Message\Value;


use App\Models\Message;

readonly class LegacyThreadInfo
{
    public function __construct(
        /**
         * The value for the newer "thread_id" field in the {@see Message} model.
         * This is null if the message is not part of a thread.
         */
        public ?int $threadId,
        
        /**
         * The thread index, which is the first part of the "message_id" field in the {@see Message} model.
         * @var int
         * @deprecated This logic was flawed, as only 1000 messages can be in a thread, otherwise we will create a overflow error.
         */
        public int  $legacyThreadId,
    )
    {
    }
}
