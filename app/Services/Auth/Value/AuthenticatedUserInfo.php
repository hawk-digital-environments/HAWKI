<?php
declare(strict_types=1);


namespace App\Services\Auth\Value;


readonly class AuthenticatedUserInfo implements \JsonSerializable
{
    public function __construct(
        /**
         * The username of the authenticated user.
         * This is normally a unique identifier within the authentication system, like an email or user ID.
         * This value MUST be unique across all users.
         */
        public string $username,
        /**
         * The display name of the authenticated user.
         * This is a human-friendly name that can be shown in the UI.
         */
        public string $displayName,
        /**
         * The email address of the authenticated user.
         * This is used for contact and identification purposes.
         * This value MUST be a valid email format and unique across all users.
         */
        public string $email,
        /**
         * This is a group identifier that is currently primarily used for logging purposes, but will, in the future,
         * also be used for permission management.
         * If you don't have a suitable attribute, you can also set it to a fixed value like 'employee' or 'member'.
         * @var string
         */
        public string $employeeType
    )
    {
    }

    /**
     * @inheritDoc
     * NOTE: Please note, that the keys are in the legacy format!
     */
    public function jsonSerialize(): array
    {
        return [
            'username' => $this->username,
            'name' => $this->displayName,
            'email' => $this->email,
            'employeetype' => $this->employeeType,
        ];
    }
}
