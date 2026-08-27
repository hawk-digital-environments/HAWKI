<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage;

use App\Services\Chat\Attachment\Repositories\AttachmentRepository;
use App\Services\Storage\AbstractFileStorage;
use App\Services\Storage\UrlGenerator;
use App\Services\Storage\Utils\ContentExtractor;
use App\Services\Storage\Values\StorageServiceContext;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Contracts\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;
use Tests\Unit\Services\Storage\AbstractFileStorageTestFixtures\ConcreteFileStorageStub;

#[CoversClass(AbstractFileStorage::class)]
class AbstractFileStorageTest extends TestCase
{
    private const UUID = 'abcd1234-e29b-41d4-a716-446655440000';

    private ConcreteFileStorageStub $sut;
    private StorageServiceContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->makeContext(allowedMimeTypes: [], maxFileSize: 1024 * 1024 * 10);
        $this->sut = new ConcreteFileStorageStub($this->context);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(ConcreteFileStorageStub::class, $this->sut);
    }

    // =========================================================================
    // getMaxFileSize
    // =========================================================================

    public function testItGetMaxFileSizeReturnsContextValue(): void
    {
        $context = $this->makeContext(maxFileSize: 5_242_880);
        $sut = new ConcreteFileStorageStub($context);

        static::assertSame(5_242_880, $sut->getMaxFileSize());
    }

    // =========================================================================
    // buildFolder
    // =========================================================================

    public function testItBuildsFolderWithoutTemp(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $folder = $this->sut->exposeBuildFolder($identifier, false);

        // Structure: {category}/{uuid[0]}/{uuid[1]}/{uuid[2]}/{uuid[3]}/{uuid}
        static::assertSame('private/a/b/c/d/abcd1234-e29b-41d4-a716-446655440000', $folder);
    }

    public function testItBuildsFolderWithTemp(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $folder = $this->sut->exposeBuildFolder($identifier, true);

        static::assertSame('temp/private/a/b/c/d/abcd1234-e29b-41d4-a716-446655440000', $folder);
    }

    public function testItBuildsFolderUsesCorrectCategory(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::GROUP, self::UUID);

        $folder = $this->sut->exposeBuildFolder($identifier);

        static::assertStringStartsWith('group/', $folder);
    }

    public function testItBuildsFolderShardsFirstFourCharsOfUuid(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $folder = $this->sut->exposeBuildFolder($identifier);
        $parts = explode('/', $folder);

        // parts: private / a / b / c / d / full-uuid
        static::assertSame('a', $parts[1]);
        static::assertSame('b', $parts[2]);
        static::assertSame('c', $parts[3]);
        static::assertSame('d', $parts[4]);
        static::assertSame(self::UUID, $parts[5]);
    }

    // =========================================================================
    // buildPath — extension handling
    // =========================================================================

    public static function provideTestItBuildsPathKeepsKnownExtensionsData(): iterable
    {
        yield 'pdf lowercase' => ['file.pdf', 'pdf'];
        yield 'pdf uppercase' => ['file.PDF', 'PDF'];
        yield 'doc' => ['file.doc', 'doc'];
        yield 'docx' => ['file.docx', 'docx'];
        yield 'jpg' => ['photo.jpg', 'jpg'];
        yield 'jpeg' => ['photo.jpeg', 'jpeg'];
        yield 'png' => ['image.png', 'png'];
        yield 'gif' => ['animation.gif', 'gif'];
    }

    #[DataProvider('provideTestItBuildsPathKeepsKnownExtensionsData')]
    public function testItBuildsPathKeepsKnownExtensions(string $filename, string $expectedExtension): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $path = $this->sut->exposeBuildPath($identifier, $filename);

        static::assertStringEndsWith('.' . $expectedExtension, $path);
    }

    public static function provideTestItBuildsPathConvertsUnknownExtensionsToBlobData(): iterable
    {
        yield 'exe' => ['virus.exe'];
        yield 'sh' => ['script.sh'];
        yield 'php' => ['code.php'];
        yield 'txt' => ['notes.txt'];
        yield 'zip' => ['archive.zip'];
        yield 'mp4' => ['video.mp4'];
        yield 'no extension' => ['noextension'];
    }

    #[DataProvider('provideTestItBuildsPathConvertsUnknownExtensionsToBlobData')]
    public function testItBuildsPathConvertsUnknownExtensionsToBlob(string $filename): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $path = $this->sut->exposeBuildPath($identifier, $filename);

        static::assertStringEndsWith('.blob', $path);
    }

    public function testItBuildsPathUsesUuidAsFilename(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $path = $this->sut->exposeBuildPath($identifier, 'document.pdf');

        static::assertStringContainsString(self::UUID . '.pdf', $path);
    }

    public function testItBuildsPathIsInsideBuildFolder(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $folder = $this->sut->exposeBuildFolder($identifier);
        $path = $this->sut->exposeBuildPath($identifier, 'file.pdf');

        static::assertStringStartsWith($folder . '/', $path);
    }

    // =========================================================================
    // filterMimeTypesByAllowed
    // =========================================================================

    public function testItFilterMimeTypesByAllowedReturnsAllWhenNoAllowListConfigured(): void
    {
        $sut = new ConcreteFileStorageStub($this->makeContext(allowedMimeTypes: []));
        $available = ['image/png', 'image/jpeg', 'application/pdf'];

        $result = $sut->exposeFilterMimeTypesByAllowed($available);

        static::assertSame($available, $result);
    }

    public function testItFilterMimeTypesByAllowedIntersectsWithAllowList(): void
    {
        $sut = new ConcreteFileStorageStub(
            $this->makeContext(allowedMimeTypes: ['image/png', 'application/pdf'])
        );
        $available = ['image/png', 'image/jpeg', 'application/pdf', 'text/plain'];

        $result = $sut->exposeFilterMimeTypesByAllowed($available);

        sort($result);
        static::assertSame(['application/pdf', 'image/png'], $result);
    }

    public function testItFilterMimeTypesByAllowedFallsBackToAllWhenNoMatchFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning');

        $sut = new ConcreteFileStorageStub(
            $this->makeContext(allowedMimeTypes: ['application/x-not-a-real-type'], logger: $logger)
        );
        $available = ['image/png', 'image/jpeg'];

        $result = $sut->exposeFilterMimeTypesByAllowed($available);

        static::assertSame($available, $result);
    }

    public function testItFilterMimeTypesByAllowedDeduplicatesAndLowercases(): void
    {
        $sut = new ConcreteFileStorageStub($this->makeContext(allowedMimeTypes: []));
        $available = ['Image/PNG', 'image/png', 'IMAGE/PNG'];

        $result = $sut->exposeFilterMimeTypesByAllowed($available);

        static::assertSame(['image/png'], $result);
    }

    // =========================================================================
    // persistTemporaryFile
    // =========================================================================

    /**
     * Regression test: persisting must never rely on moving the temp DIRECTORY, because
     * drivers like s3 have no real directories and a "directory" move silently loses
     * data there. Instead every file (including nested extract files) must be copied
     * individually into the persistent folder.
     */
    public function testItPersistsTemporaryFileByCopyingEachFileInsteadOfMovingTheDirectory(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);
        $tempFolder = 'temp/private/a/b/c/d/' . self::UUID;
        $persistentFolder = 'private/a/b/c/d/' . self::UUID;

        $tempFiles = [
            $tempFolder . '/.meta.json',
            $tempFolder . '/' . self::UUID . '.pdf',
            $tempFolder . '/output/000-extract.md',
        ];

        $copied = [];
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(static::once())->method('allFiles')->with($tempFolder)->willReturn($tempFiles);
        $filesystem->expects(static::never())->method('move');
        $filesystem->expects(static::exactly(3))->method('copy')->willReturnCallback(
            static function (string $from, string $to) use (&$copied): bool {
                $copied[$from] = $to;

                return true;
            }
        );

        $filesystem->expects(static::once())->method('deleteDirectory')->with($tempFolder);

        $sut = new ConcreteFileStorageStub($this->makeContext(filesystem: $filesystem));

        static::assertTrue($sut->persistTemporaryFile($identifier));

        static::assertSame(
            [
                $tempFolder . '/.meta.json' => $persistentFolder . '/.meta.json',
                $tempFolder . '/' . self::UUID . '.pdf' => $persistentFolder . '/' . self::UUID . '.pdf',
                $tempFolder . '/output/000-extract.md' => $persistentFolder . '/output/000-extract.md',
            ],
            $copied
        );
    }

    public function testItPersistsTemporaryFileSucceedsWhenTheTempFolderIsEmpty(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);
        $tempFolder = 'temp/private/a/b/c/d/' . self::UUID;

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(static::once())->method('allFiles')->with($tempFolder)->willReturn([]);
        $filesystem->expects(static::never())->method('copy');
        $filesystem->expects(static::never())->method('deleteDirectory');
        $filesystem->expects(static::never())->method('move');

        $sut = new ConcreteFileStorageStub($this->makeContext(filesystem: $filesystem));

        static::assertTrue($sut->persistTemporaryFile($identifier));
    }

    public function testItPersistsTemporaryFileReturnsFalseWhenAFileCanNotBeCopied(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);
        $tempFolder = 'temp/private/a/b/c/d/' . self::UUID;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('allFiles')->willReturn([$tempFolder . '/' . self::UUID . '.pdf']);
        $filesystem->method('copy')->willReturn(false);
        $filesystem->expects(static::never())->method('deleteDirectory');

        $sut = new ConcreteFileStorageStub($this->makeContext(logger: $logger, filesystem: $filesystem));

        static::assertFalse($sut->persistTemporaryFile($identifier));
    }

    public function testItPersistsTemporaryFileReturnsFalseWhenTheFilesystemThrows(): void
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('allFiles')->willThrowException(new \RuntimeException('disk offline'));

        $sut = new ConcreteFileStorageStub($this->makeContext(logger: $logger, filesystem: $filesystem));

        static::assertFalse($sut->persistTemporaryFile($identifier));
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function makeContext(
        array $allowedMimeTypes = [],
        int $maxFileSize = 10_000_000,
        LoggerInterface|null $logger = null,
        Filesystem|null $filesystem = null,
    ): StorageServiceContext
    {
        return new StorageServiceContext(
            allowedMimeTypes: $allowedMimeTypes,
            maxFileSize: $maxFileSize,
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            filesystem: $filesystem ?? $this->createStub(Filesystem::class),
            urlGenerator: new UrlGenerator('web.storage.proxy'),
            contentExtractor: $this->createStub(ContentExtractor::class),
            attachmentRepository: $this->createStub(AttachmentRepository::class),
            clock: new MockClock(),
        );
    }
}
