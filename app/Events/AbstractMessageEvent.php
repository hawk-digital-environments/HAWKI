<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractMessageEvent
{
    use Dispatchable;

    public function __construct(
        public Message $message
    )
    {
    }
}
