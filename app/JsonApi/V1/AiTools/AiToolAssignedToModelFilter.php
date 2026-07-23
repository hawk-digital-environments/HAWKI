<?php
declare(strict_types=1);


namespace App\JsonApi\V1\AiTools;


use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

class AiToolAssignedToModelFilter implements Filter
{
    use IsSingular;
    use DeserializesValue;

    public function __construct()
    {
        $this->asBoolean();
    }

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        if ($value) {
            $query->whereHas('models');
            return $query;
        }

        $query->whereDoesntHave('models');

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'assigned';
    }
}
