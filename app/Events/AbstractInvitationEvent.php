<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractInvitationEvent
{
    use Dispatchable;

    public function __construct(
        public Invitation $invitation
    )
    {
    }
}
