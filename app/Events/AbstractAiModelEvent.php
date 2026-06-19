<?php
declare(strict_types=1);


namespace App\Events;


use App\Services\Ai\Values\AiModel;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractAiModelEvent
{
    use Dispatchable;

    public function __construct(
        public readonly AiModel $model
    )
    {
    }

}
