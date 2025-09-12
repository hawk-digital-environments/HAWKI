<?php
declare(strict_types=1);


namespace App\Services\Message\Listeners;


use App\Events\MessageSentEvent;
use App\Services\Message\MessageDb;

readonly class ThreadParentUpdateListener
{
    public function __construct(
        private MessageDb $messageDb
    )
    {
    
    }
    
    public function handle(MessageSentEvent $event): void
    {
        $this->messageDb->setHasThreadOnParentMessage($event->message);
    }
}
