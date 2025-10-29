<?php
declare(strict_types=1);

use App\Services\Encryption\SaltProvider;

return [
    /**
     * Salts for various encryption purposes.
     * Those are required by the {@see SaltProvider} which automatically provides a semi static fallback
     * for them, if they have not been configured.
     */
    'salts' => [
        'USERDATA_ENCRYPTION_SALT' => env('USERDATA_ENCRYPTION_SALT', null),
        'INVITATION_SALT' => env('INVITATION_SALT', null),
        'AI_CRYPTO_SALT' => env('AI_CRYPTO_SALT', null),
        'PASSKEY_SALT' => env('PASSKEY_SALT', null),
        'BACKUP_SALT' => env('BACKUP_SALT', null),
    ]
];
