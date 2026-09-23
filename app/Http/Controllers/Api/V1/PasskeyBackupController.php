<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

/**
 * Serves `GET /api/hawki/v1/passkey-backups/{id}`.
 *
 * The only id that resolves is `me`; see
 * {@see \App\JsonApi\V1\PasskeyBackups\PasskeyBackupRepository::find()} for the lookup and the
 * two 404 shapes.
 */
class PasskeyBackupController extends Controller
{
    use Actions\FetchOne;
}
