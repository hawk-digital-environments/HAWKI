<?php
declare(strict_types=1);


namespace App\Services\Storage\Value;

use App\Http\Resources\StorageFileProxyRoutePathArgsResource;
use Symfony\Component\Mime\MimeTypes;

/**
 * @property string $mimeType The MIME type of the file (determined by the file extension)
 */
readonly class StorageFileInfo implements \Stringable
{
    
    public function __construct(
        /**
         * The category of the file (what type of storage it is)
         * This implicitly defines which storage the file is stored in and which implementation to use.
         */
        public StorageFileCategory $category,
        /**
         * The path of the file within the storage (without the filename)
         */
        public string              $directory,
        /**
         * The subdirectory below the main directory (for extracted files, e.g. for rag/ai conversations)
         */
        public string              $outputDirectory,
        /**
         * The full path of the file within the storage (including the filename)
         */
        public string              $pathname,
        /**
         * The basename of the file (the filename with extension)
         * @var string
         */
        public string              $basename,
        /**
         * The UUID of the file (the unique identifier of the file, without extension)
         */
        public string              $uuid
    )
    {
    }
    
    /**
     * Returns the route params resource for this file info.
     * This resource object is used by the frontend to dynamically generate the correct URL to access the file.
     * We will only pass the required parameters to the frontend, so it can generate the correct URL;
     * Because external / internal URLs may differ, we cannot pass a full URL here.
     * @return StorageFileProxyRoutePathArgsResource
     */
    public function toRoutePathArgsResource(): StorageFileProxyRoutePathArgsResource
    {
        return new StorageFileProxyRoutePathArgsResource($this);
    }
    
    public function __toString(): string
    {
        return $this->pathname;
    }
    
    /**
     * @noinspection MagicMethodsValidityInspection
     */
    public function __get(string $name)
    {
        if ($name === 'mimeType') {
            // @todo once PHP 8.4 is the baseline we can use the property hooks instead of the magic method
            $extension = pathinfo($this->basename, PATHINFO_EXTENSION);
            return [...($extension ? (new MimeTypes())->getMimeTypes($extension) : 'application/octet-stream')][0];
        }
        
        return null;
    }
}
