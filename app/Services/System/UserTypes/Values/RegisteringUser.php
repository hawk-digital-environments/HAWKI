<?php
declare(strict_types=1);


namespace App\Services\System\UserTypes\Values;


readonly class RegisteringUser
{
    public function __construct(
        public string $username,
        public string $name,
        public string $email,
        public string $employeeType,
    )
    {
    }
}
