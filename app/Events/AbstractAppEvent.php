<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\ExtApp;
use Illuminate\Foundation\Events\Dispatchable;

readonly abstract class AbstractAppEvent
{
    use Dispatchable;
    
    public function __construct(
        public ExtApp $app
    )
    {
    }
}
