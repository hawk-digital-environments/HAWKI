<?php
declare(strict_types=1);


namespace App\Services\Storage\Config;

use App\Services\Config\Contracts\PublicConfigInterface;
use App\Services\Storage\FileStorageService;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class FileStorageConfig extends AbstractStorageConfig implements PublicConfigInterface
{
    /**
     * Maximum file uploads (attachments) file size in bytes
     */
    public readonly int $maxFileSize;
    /**
     * Allowed MIME types for file uploads (attachments)
     */
    public readonly array $allowedMimeTypes;
    /**
     * Allowed file extensions for file uploads (attachments)
     */
    public readonly array $allowedExtensions;

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'storage_files';
    }

    /**
     * @inheritDoc
     */
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
    public static function make(Repository $repo, ?FileStorageService $fileStorageService = null): static
    {
        $mimeTypes = $fileStorageService?->getAllowedMimeTypes() ?? [];
        return self::fromArray([
            'maxFileSize' => $fileStorageService?->getMaxFileSize() ?? 0,
            'allowedMimeTypes' => $mimeTypes,
            'allowedExtensions' => self::extensionsFromMimeTypes($mimeTypes),
        ]);
    }
}
