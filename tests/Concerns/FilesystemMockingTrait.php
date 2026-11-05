<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Contracts\Filesystem\Filesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;

trait FilesystemMockingTrait
{
    /**
     * State-based filesystem double. exists(), get() and size() answer from the given
     * path => content map so tests share one consistent view of the "disk".
     *
     * @param array<string, string> $files path => file content
     */
    private function stubFilesystem(array $files = []): Filesystem&Stub
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('exists')->willReturnCallback(static fn (string $path): bool => isset($files[$path]));
        $filesystem->method('get')->willReturnCallback(static fn (string $path): ?string => $files[$path] ?? null);
        $filesystem->method('size')->willReturnCallback(static fn (string $path): int => mb_strlen($files[$path] ?? ''));

        return $filesystem;
    }

    /**
     * Interaction-based filesystem double for tests that verify calls on the disk
     * (expects(...)->method(...)).
     */
    private function mockFilesystem(): Filesystem&MockObject
    {
        return $this->createMock(Filesystem::class);
    }
}
