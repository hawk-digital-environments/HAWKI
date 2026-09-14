<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

trait DeletesResources
{
    public function destroy(Request $request, string $id): Response
    {
        $this->mutate($request, 'delete', $id, fn () => $this->repository()->delete((int) $id, $request->user()));

        return response()->noContent();
    }
}
