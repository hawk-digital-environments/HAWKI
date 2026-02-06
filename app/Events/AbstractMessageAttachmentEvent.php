<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

readonly class AbstractMessageAttachmentEvent
{
    use Dispatchable;
    
    public function __construct(
        public Message    $message,
        public Attachment $attachment
    )
    {
    }
}
