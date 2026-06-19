<?php

namespace App\Services\Frontend\Migrations\Values;

enum FrontendMigrationRunType: string
{
    case AFTER_LOGIN = 'after_login';
    case AFTER_PASSKEY = 'after_passkey';
}
