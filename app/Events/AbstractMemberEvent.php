<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\Member;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractMemberEvent
{
    use Dispatchable;

    public function __construct(
        public Member $member
    )
    {
    }
}
