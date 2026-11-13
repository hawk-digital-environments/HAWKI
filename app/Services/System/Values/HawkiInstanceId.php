<?php
declare(strict_types=1);

namespace App\Services\System\Values;

/**
 * Reserved id of the native-instance singleton JSON:API resources — the "this HAWKI"
 * that `connections/hawki`, `auth/hawki`, `sessions/hawki` and `registrations/hawki`
 * are all addressed by. The connections id space additionally holds external-app user
 * ids, which is why the native instance needs a reserved name there.
 */
final class HawkiInstanceId
{
    public const string HAWKI = 'hawki';
}
