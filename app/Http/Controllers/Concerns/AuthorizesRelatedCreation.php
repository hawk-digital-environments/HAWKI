<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesRelatedCreation
{
    protected function authorizeCreateAgainstRelatedModel(string $modelRelation, string $modelClass, string $viewPolicy): void
    {
        $id = (int) request()->input("data.relationships.{$modelRelation}.data.id");

        Gate::authorize($viewPolicy, $modelClass::findOrFail($id));
    }
}
