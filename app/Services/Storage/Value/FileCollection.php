<?php
declare(strict_types=1);


namespace App\Services\Storage\Value;


use App\Services\Storage\Interfaces\FileInterface;

/**
 * @extends \IteratorAggregate<FileInterface>
 */
readonly class FileCollection implements \IteratorAggregate, \Countable, \JsonSerializable
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
        $filteredExtracts = array_filter(
            $this->files,
            static fn(FileInterface $extract) => $extract->getFileType() === $mediaType
        );

        return new self(...$filteredExtracts);
    }

    /**
     * Returns a new instance of this collection, filtered to only include extracts with the specified file extension.
     * @param string $extension
     * @return self
     */
    public function filterByExtension(string $extension): self
    {
        $filteredExtracts = array_filter(
            $this->files,
            static fn(FileInterface $extract) => $extract->getExtension() === $extension
        );

        return new self(...$filteredExtracts);
    }

    /**
     * Returns a new instance of this collection, filtered to only include extracts with the specified MIME type.
     * @param string $mimetype
     * @return self
     */
    public function filterByMimetype(string $mimetype): self
    {
        $filteredExtracts = array_filter(
            $this->files,
            static fn(FileInterface $extract) => $extract->getMimeType() === $mimetype
        );

        return new self(...$filteredExtracts);
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
