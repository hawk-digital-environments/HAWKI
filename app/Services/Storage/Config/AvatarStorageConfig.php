<?php
declare(strict_types=1);


namespace App\Services\Storage\Config;


use App\Services\Config\Contracts\PublicConfigInterface;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Exposes upload constraints for user and room avatar uploads to the frontend under the
 * `storage_avatars` public-config key.
 *
 * The values are derived at runtime from the {@see AvatarStorageService} so that the frontend
 * always reflects the same limits that the backend enforces.
 */
class AvatarStorageConfig extends AbstractStorageConfig implements PublicConfigInterface
{
    /**
     * Maximum avatar file size in bytes
     */
    public readonly int $maxFileSize;
    /**
     * Allowed MIME types for avatars
     */
    public readonly array $allowedMimeTypes;
    /**
     * Allowed file extensions for avatars
     */
    public readonly array $allowedExtensions;

    public static function publicKey(): string
    {
        return 'storage_avatars';
    }

    public function toPublicArray(Request $request): array|null
    {
        if ($request->user()) {
            return [
                'maxFileSize' => $this->maxFileSize,
                'allowedMimeTypes' => $this->allowedMimeTypes,
                'allowedExtensions' => $this->allowedExtensions,
            ];
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public static function make(Repository $repo, ?AvatarStorageService $storageService = null): static
    {
        $mimeTypes = $storageService?->getAllowedMimeTypes() ?? [];
        return self::fromArray([
            'maxFileSize' => $storageService?->getMaxFileSize() ?? 0,
            'allowedMimeTypes' => $mimeTypes,
            'allowedExtensions' => self::extensionsFromMimeTypes($mimeTypes),
        ]);
    }
}
