<?php
declare(strict_types=1);


namespace App\Services\Encryption;


enum SaltType: string
{
    case USERDATA = 'USERDATA_ENCRYPTION_SALT';
    case INVITATION = 'INVITATION_SALT';
    case AI = 'AI_CRYPTO_SALT';
    case PASSKEY = 'PASSKEY_SALT';
    case BACKUP = 'BACKUP_SALT';
}
