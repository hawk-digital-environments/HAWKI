<?php
declare(strict_types=1);

namespace App\Services\Auth\Value;

enum SpaAuthPage: string
{
    case LOGIN = 'login';
    case REGISTER = 'register';
    case HANDSHAKE = 'handshake';
}
