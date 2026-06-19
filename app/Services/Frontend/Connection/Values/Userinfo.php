<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;

use App\Services\Storage\Values\StoredFileIdentifier;

readonly class Userinfo
{
    public function __construct(
        public int                       $id,
        public string                    $name,
        public string                    $username,
        public string                    $email,
        public string                    $hash,
        public StoredFileIdentifier|null $avatar = null,
        public string|null               $bio = null,
    )
    {
    }
}
