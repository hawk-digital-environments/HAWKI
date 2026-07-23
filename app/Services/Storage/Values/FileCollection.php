<?php
declare(strict_types=1);


namespace App\Services\Storage\Values;


use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Typed, immutable collection of {@see FileInterface} items (typically {@see StoredFileExtract} objects).
 *
 * Nested collections passed to the constructor are automatically flattened, so you can merge
 * two collections by doing `new FileCollection($collectionA, $collectionB)`.
 *
 * All filter methods return a new instance; the original is not modified.
 *
 * @implements \IteratorAggregate<int, FileInterface>
 */
readonly class FileCollection implements \IteratorAggregate, \Countable, \JsonSerializable, Arrayable
{
    private array $files;

    public function __construct(
        FileInterface|self ...$files
    )
    {
        $collectedFiles = [];
        foreach ($files as $file) {
            if ($file instanceof self) {
                $collectedFiles = array_merge($collectedFiles, $file->files);
            } else {
                $collectedFiles[] = $file;
            }
        }
        $this->files = $collectedFiles;
    }

    /**
     * Retrieves the first extract in the collection, or null if the collection is empty.
     *
     * @return FileInterface|null
     */
    public function getFirst(): ?FileInterface
    {
        return $this->files[0] ?? null;
    }

    /**
     * Returns a new instance of this collection, filtered to only include extracts of the specified media type.
     * @param FileType $mediaType
     * @return self
     */
    public function filterByMediaType(FileType $mediaType): self
    {
        $filterFiles = array_filter(
            $this->files,
            static fn(FileInterface $file) => $file->getFileType() === $mediaType
        );

        return new self(...$filterFiles);
    }

    /**
     * Returns a new instance of this collection, filtered to only include extracts with the specified file extension.
     * @param string $extension
     * @return self
     */
    public function filterByExtension(string $extension): self
    {
        $filteredFiles = array_filter(
            $this->files,
            static function (FileInterface $file) use ($extension) {
                if ($file instanceof StoredFileExtract) {
                    return $file->getExtension() === $extension;
                }
                return pathinfo($file->getOriginalFilename(), PATHINFO_EXTENSION) === $extension;
            }
        );

        return new self(...$filteredFiles);
    }

    /**
     * Returns a new instance of this collection, filtered to only include extracts with the specified MIME type.
     * @param string $mimetype
     * @return self
     */
    public function filterByMimetype(string $mimetype): self
    {
        $filteredFiles = array_filter(
            $this->files,
            static fn(FileInterface $extract) => $extract->getMimeType() === $mimetype
        );

        return new self(...$filteredFiles);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->files;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->files);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->files);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->files;
    }
}
