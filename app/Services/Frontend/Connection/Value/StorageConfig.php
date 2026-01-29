<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class StorageConfig implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        /**
         * Maximum file uploads (attachments) file size in bytes
         */
        public int   $maxFileSize,
        /**
         * Maximum avatar file size in bytes
         */
        public int   $maxAvatarFileSize,
        /**
         * Allowed MIME types for file uploads (attachments)
         */
        public array $allowedMimeTypes,
        /**
         * Allowed MIME types for avatars
         */
        public array $allowedAvatarMimeTypes,
        /**
         * Maximum number of attachment files per message
         */
        public int   $maxAttachmentFiles,
    )
    {
        $isArrayOfMimeTypes = static fn(array $arr): bool => array_reduce(
            $arr,
            static fn(bool $carry, mixed $item): bool => $carry && is_string($item) && $item !== '' && str_contains($item, '/'),
            true
        );
        
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isNonNegativeInteger($this->maxFileSize, 'maxFileSize'),
            fn() => Assert::isNonNegativeInteger($this->maxAvatarFileSize, 'maxAvatarFileSize'),
            fn() => Assert::is($this->allowedMimeTypes, $isArrayOfMimeTypes, 'allowedMimeTypes'),
            fn() => Assert::is($this->allowedAvatarMimeTypes, $isArrayOfMimeTypes, 'allowedAvatarMimeTypes'),
            fn() => Assert::isNonNegativeInteger($this->maxAttachmentFiles, 'maxAttachmentFiles'),
        );
    }
}
