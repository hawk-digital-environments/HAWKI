<?php
declare(strict_types=1);


namespace App\Services\Storage\Config;


use App\Services\Config\Contracts\PublicConfigInterface;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

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
