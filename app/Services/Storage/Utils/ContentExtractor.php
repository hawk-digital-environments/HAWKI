<?php
declare(strict_types=1);


namespace App\Services\Storage\Utils;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\AbstractFileStorage;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileExtract;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;

#[Singleton]
readonly class ContentExtractor
{
    public function __construct(
        private FileConverterInterface $fileConverter,
        private LoggerInterface        $logger,
    )
    {
    }

    /**
     * Starts the SYNCHRONOUS content extraction process for a given file. This method will use the configured
     * {@see FileConverterInterface} to attempt to extract content from the file, and will store the extracted content back in
     * the same filesystem, in a subdirectory within the same folder as the original file, named according to {@see AbstractFileStorage::EXTRACT_FOLDER_NAME}.
     *
     * The extracted content will be stored as separate files, and the method will return a collection of {@see StoredFileExtract} objects representing the extracted content.
     * The method will handle plain text files as a special case, where it will not attempt to extract content, but will instead create a single extract that references the original file itself, with the appropriate language type.
     *
     * @param string $storageDiskFilePath The full disk file path of the original file on the storage disk, used to determine where to store the extracted content and to associate the extracts with the original file.
     * @param FileReference $file The file reference object representing the original file, used to read metadata such as MIME type and size, and to determine if it's a plain text file that can be directly referenced as an extract.
     * @param Filesystem $filesystem The filesystem instance where the file is stored, used to read the original file and write the extracted content.
     * @param bool $extractContent A flag indicating whether to perform content extraction. If set to false, the method will skip the extraction process and return an empty collection.
     * @return FileCollection|null
     */
    public function getExtracts(
        string        $storageDiskFilePath,
        FileReference $file,
        Filesystem    $filesystem,
        bool          $extractContent = true,
    ): FileCollection|null
    {
        if (!$extractContent) {
            return new FileCollection();
        }

        // If the file is a plain text file, and we know it in our "PlainTextLanguageType" type system,
        // We simply reference the file itself as the "extract", so that it can be looked up in the same way as other extracts,
        // without having to duplicate the file or its contents.
        if ($file->isPlainTextFile() && $file->getPlainTextLanguageType() !== null) {
            return new FileCollection(
                StoredFileExtract::fromPlainTextFile(
                    storageDiskFilePath: $storageDiskFilePath,
                    sourceSize: $file->getSize(),
                    languageType: $file->getPlainTextLanguageType(),
                    filesystem: $filesystem
                )
            );
        }

        if (!$this->fileConverter->isAvailable()) {
            return null;
        }

        // If we get a file passed with an incorrect extension,
        // we should implicitly rename it here, so the converter can recognize the corrected extension.
        $mimeType = $file->getMimeType();
        $extensions = (new MimeTypes())->getExtensions($mimeType);
        if (!empty($extensions)) {
            $currentExtension = pathinfo($file->getOriginalFilename(), PATHINFO_EXTENSION);
            if (!in_array($currentExtension, $extensions, true)) {
                $correctedFilename = pathinfo($file->getOriginalFilename(), PATHINFO_FILENAME) . '.' . $extensions[0];
                $file = $file->withOriginalFilename($correctedFilename);
            }
        }

        if (!$this->fileConverter->canConvertMimetype($mimeType)) {
            return new FileCollection();
        }

        $storageDiskFolderPath = dirname($storageDiskFilePath);
        $extractDiskFolderPath = Path::join($storageDiskFolderPath, AbstractFileStorage::EXTRACT_FOLDER_NAME);
        $extracts = [];

        $c = 0;
        foreach ($this->fileConverter->convert($file) as $extractFile) {
            // Ensure that even the same file names from the converter get a unique name on disk, to avoid overwriting each other.
            $extractRealDiskFilePath = Path::join(
                $extractDiskFolderPath,
                str_pad((string)$c++, 3, '0', STR_PAD_LEFT) . '-' . $extractFile->getOriginalFilename()
            );

            if (!$filesystem->writeStream($extractRealDiskFilePath, $extractFile->getStream())) {
                $this->logger->error("Failed to store extracted content for file {$file->getOriginalFilename()} ({$file->getDiskFilePath()}) at path {$extractRealDiskFilePath}");
                continue;
            }

            $extracts[] = StoredFileExtract::fromExtractFile(
                storageDiskFilePath: $storageDiskFilePath,
                // We create a new reference here, because the original $extractFile does not point to the correct path on disk.
                extractFile: FileReference::fromFilesystemDisk(
                    diskFilePath: $extractRealDiskFilePath,
                    filesystem: $filesystem,
                    originalFilename: $extractFile->getOriginalFilename(),
                ),
                filesystem: $filesystem
            );
        }

        return new FileCollection(...$extracts);
    }
}
