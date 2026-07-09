<?php
declare(strict_types=1);


namespace App\JsonApi\V1\AiTools;


use App\Services\Ai\Values\OnlineStatus;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

class AiToolStatusFilter implements Filter
{
    use IsSingular;

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        $requestedType = OnlineStatus::tryFrom($value);
        if (!$requestedType) {
            abort(400, sprintf(
                    'Invalid status filter value "%s". Allowed values are: %s',
                    $value,
                    implode(', ', array_map(fn($status) => $status->value, OnlineStatus::cases())))
            ); // 400 Bad Request
        }

        match ($requestedType) {
            OnlineStatus::ONLINE => $query->where('type', 'function')
                ->orWhere(function ($q2) {
                    // Join McpServers to check their status
                    $q2->where('type', 'mcp')
                        ->whereHas('server', function ($q3) {
                            $q3->where('status', OnlineStatus::ONLINE->value);
                        });
                }),
            OnlineStatus::OFFLINE => $query->where('type', 'mcp')
                ->whereHas('server', function ($q3) {
                    $q3->where('status', OnlineStatus::OFFLINE->value);
                }),
            OnlineStatus::UNKNOWN => $query->where('type', 'mcp')
                ->whereHas('server', function ($q3) {
                    $q3->where('status', OnlineStatus::UNKNOWN->value);
                }),
        };

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'status';
    }
}
