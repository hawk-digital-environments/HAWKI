<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\User;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Contracts\Cache\Repository;

final readonly class TemporaryUploadOwnership
{
    public function __construct(private Repository $cache)
    {
    }

    public function register(StoredFileIdentifier $identifier, User $user): bool
    {
        return $this->cache->put(
            $this->cacheKey($identifier),
            (string) $user->getAuthIdentifier(),
            AbstractFileStorage::TEMPORARY_FILE_TTL_SECONDS,
        );
    }

    /**
     * Whether an ownership record exists at all. False means the upload is unknown or has expired.
     */
    public function isRegistered(StoredFileIdentifier $identifier): bool
    {
        return null !== $this->cache->get($this->cacheKey($identifier));
    }

    public function isOwnedBy(StoredFileIdentifier $identifier, User $user): bool
    {
        $owner = $this->cache->get($this->cacheKey($identifier));

        return null !== $owner && (string) $owner === (string) $user->getAuthIdentifier();
    }

    public function forget(StoredFileIdentifier $identifier): void
    {
        $this->cache->forget($this->cacheKey($identifier));
    }

    private function cacheKey(StoredFileIdentifier $identifier): string
    {
        return 'temporary-upload-owner:' . hash(
            'sha256',
            $identifier->category->value . ':' . $identifier->uuid,
        );
    }
}
