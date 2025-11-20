<?php
declare(strict_types=1);


namespace App\Services\Auth\Value\Local;


readonly class GuestUserRequestData
{
    public function __construct(
        public string $username,
        public string $password,
        public string $passwordConfirmation,
        public string $email,
        public string $employeeType,
    )
    {
    }
}
