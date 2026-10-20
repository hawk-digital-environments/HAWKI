<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;

use App\Services\Storage\Values\StoredFileIdentifier;

/**
 * Authenticated user's profile snapshot delivered to the frontend with every connection response.
 *
 * For registering users (mid-registration, not yet persisted), `id` is `0` and `hash` is an
 * empty string because no stable identity exists yet.
 */
readonly class Userinfo
{
    public function __construct(
        /** HAWKI database user ID, or `0` for a registering user. */
        public int                       $id,
        public string                    $name,
        public string                    $username,
        public string                    $email,
        /**
         * MD5 of `{userId}-{publicKey}`, used by the frontend to detect profile changes
         * and invalidate cached avatars. Empty string for registering users.
         */
        public string                    $hash,
        /** Reference to the user's avatar file; `null` when no avatar has been uploaded. */
        public StoredFileIdentifier|null $avatar = null,
        public string|null               $bio = null,
    )
    {
    }
}
