<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\JsonApi\V1\Admin\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

trait DeletesResources
{
    public function destroy(Request $request, Record $record): Response
    {
        $id = $record->id();
        $this->mutate($request, 'delete', $id, fn () => $this->repository()->delete((int) $id, $request->user()));

        return response()->noContent();
    }
}
