<?php

declare(strict_types=1);

namespace App\Services\AI\Repositories;

use Illuminate\Database\Eloquent\Builder;

readonly class AiProviderRepository
{
    public function filterByToolCapability(Builder $query, string $capability): Builder
    {
        return $query->whereHas('models.assignedTools', function ($q) use ($capability) {
            $q->where('capability', $capability);
        });
    }
}
