<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;

class CheckStorageConnection extends Command
{
    /**
     * Name of the temporary test file that is written to and removed from every checked disk.
     */
    private const TEST_FILE_NAME = '.storage_connection_test.txt';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:storage {--filesystem= : Comma-separated list of disks to check explicitly (default: the disks the storage roles resolve to)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the storage connection for the disks the app actually uses (list / write / read / delete test)';

    /**
     * Execute the console command.
     */
    public function handle(FilesystemFactory $storage): int
    {
        /** @var array<string, array<string, mixed>> $configuredDisks */
        $configuredDisks = (array) config('filesystems.disks', []);

        $checks = $this->resolveChecksToRun(array_keys($configuredDisks));
        if ($checks === null) {
            return self::FAILURE;
        }

        $rows = [];
        $failureCount = 0;

        foreach ($checks as $check) {
            $disk = $check['disk'];

            if (!array_key_exists($disk, $configuredDisks)) {
                // A storage role points to a disk that is not configured — report it as a failed
                // check instead of aborting, so the operator sees which role is misconfigured.
                $failureCount++;
                $rows[] = [
                    implode(', ', $check['labels']),
                    $disk,
                    'unknown',
                    'FAIL',
                    'Points to a disk that is not configured in filesystems.disks.',
                ];
                continue;
            }

            $driver = (string) ($configuredDisks[$disk]['driver'] ?? 'unknown');
            $result = $this->checkDisk($storage, $disk, $driver);
            $failureCount += $result['success'] ? 0 : 1;
            $rows[] = [
                implode(', ', $check['labels']),
                $disk,
                $driver,
                $result['success'] ? 'PASS' : 'FAIL',
                $result['message'],
            ];
        }

        $this->table(['Role(s)', 'Disk', 'Driver', 'Result', 'Message'], $rows);

        $diskCount = count($rows);
        if ($failureCount === 0) {
            $this->info("{$diskCount} of {$diskCount} storage disks passed the connection test.");

            return self::SUCCESS;
        }

        $this->error("{$failureCount} of {$diskCount} storage disks failed the connection test.");

        return self::FAILURE;
    }

    /**
     * Resolve what to check. Without the --filesystem option this follows the storage roles from
     * config/filesystems.php — only the disks the app actually uses are checked, and roles sharing
     * a disk are grouped into a single check. With the option, the given (comma-separated) disks
     * are checked explicitly instead — useful to verify a disk before switching a role to it.
     * Returns null (after reporting the error) when an explicitly requested disk is unknown.
     *
     * @param list<string> $availableDisks
     * @return list<array{labels: list<string>, disk: string}>|null
     */
    private function resolveChecksToRun(array $availableDisks): array|null
    {
        $requestedOption = trim((string) $this->option('filesystem'));
        if ($requestedOption !== '') {
            $requestedDisks = array_values(array_unique(array_map('trim', explode(',', $requestedOption))));
            $unknownDisks = array_diff($requestedDisks, $availableDisks);
            if ($unknownDisks !== []) {
                $this->error(
                    'Unknown disk(s): ' . implode(', ', $unknownDisks)
                    . '. Available disks: ' . implode(', ', $availableDisks) . '.'
                );

                return null;
            }

            return array_map(
                static fn (string $disk): array => ['labels' => [$disk], 'disk' => $disk],
                $requestedDisks
            );
        }

        $roles = [
            'Framework default' => (string) config('filesystems.default', 'local'),
            'File storage' => (string) config('filesystems.file_storage', 'local_file_storage'),
            'Avatar storage' => (string) config('filesystems.avatar_storage', 'public'),
        ];

        $groupedByDisk = [];
        foreach ($roles as $label => $disk) {
            $groupedByDisk[$disk][] = $label;
        }

        return array_map(
            static fn (string $disk, array $labels): array => ['labels' => $labels, 'disk' => $disk],
            array_keys($groupedByDisk),
            $groupedByDisk
        );
    }

    /**
     * Run the appropriate check for the disk, dispatched by its driver.
     *
     * @return array{success: bool, message: string}
     */
    private function checkDisk(FilesystemFactory $storage, string $disk, string $driver): array
    {
        return match ($driver) {
            'local' => $this->runWriteTest($storage->disk($disk)),
            's3' => $this->runWriteTestWhenConfigured($storage, $disk, $this->s3ConfigProblem($disk)),
            'webdav' => $this->runWriteTestWhenConfigured($storage, $disk, $this->webdavConfigProblem($disk)),
            'sftp' => $this->runWriteTestWhenConfigured($storage, $disk, $this->sftpConfigProblem($disk)),
            default => [
                'success' => false,
                'message' => "Unsupported driver \"{$driver}\" — no connection check implemented for this disk.",
            ],
        };
    }

    /**
     * Gate the write test behind a config completeness check: a disk with missing credentials
     * counts as a failure with an env-var hint instead of a confusing connection error.
     *
     * @return array{success: bool, message: string}
     */
    private function runWriteTestWhenConfigured(
        FilesystemFactory $storage,
        string $disk,
        string|null $configProblem
    ): array {
        if ($configProblem !== null) {
            return ['success' => false, 'message' => $configProblem];
        }

        return $this->runWriteTest($storage->disk($disk));
    }

    /**
     * @return string|null The config problem message, or null when the s3 disk is fully configured.
     */
    private function s3ConfigProblem(string $disk): string|null
    {
        /** @var array<string, mixed> $config */
        $config = (array) config("filesystems.disks.{$disk}", []);

        if (empty($config['key']) || empty($config['secret']) || empty($config['region']) || empty($config['bucket'])) {
            return 'S3 configuration incomplete. Check S3_ACCESS_KEY, S3_SECRET_KEY, S3_REGION and S3_DEFAULT_BUCKET environment variables.';
        }

        return null;
    }

    /**
     * @return string|null The config problem message, or null when the webdav disk is fully configured.
     */
    private function webdavConfigProblem(string $disk): string|null
    {
        /** @var array<string, mixed> $config */
        $config = (array) config("filesystems.disks.{$disk}", []);

        $baseUri = (string) ($config['base_uri'] ?? '');
        if (!str_starts_with($baseUri, 'http') || empty($config['username']) || empty($config['password'])) {
            return 'WebDAV configuration incomplete. Check NEXTCLOUD_BASE_URL, NEXTCLOUD_USERNAME and NEXTCLOUD_PASSWORD environment variables.';
        }

        return null;
    }

    /**
     * @return string|null The config problem message, or null when the sftp disk is fully configured.
     */
    private function sftpConfigProblem(string $disk): string|null
    {
        /** @var array<string, mixed> $config */
        $config = (array) config("filesystems.disks.{$disk}", []);

        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            return 'SFTP configuration incomplete. Check SFTP_HOST, SFTP_USERNAME and SFTP_PASSWORD environment variables.';
        }

        return null;
    }

    /**
     * Perform a full round-trip against the disk: list the root (connectivity), write a test file,
     * read it back, and remove it again. Every step produces a distinct failure message.
     *
     * @return array{success: bool, message: string}
     */
    private function runWriteTest(Filesystem $disk): array
    {
        $content = 'Storage connection test: ' . now()->toDateTimeString();

        try {
            // Step 1: connectivity pre-check — performs a real round-trip without writing anything.
            $disk->files();

            // Step 2: upload the test file.
            if (!$disk->put(self::TEST_FILE_NAME, $content)) {
                return ['success' => false, 'message' => 'Upload failed — check write permissions.'];
            }

            // Step 3: verify the file exists.
            if (!$disk->exists(self::TEST_FILE_NAME)) {
                return ['success' => false, 'message' => 'Upload succeeded but the file was not found afterwards — possible visibility/ACL issue.'];
            }

            // Step 4: retrieve and compare the content.
            if ($disk->get(self::TEST_FILE_NAME) !== $content) {
                return ['success' => false, 'message' => 'Content mismatch after retrieval — file corruption or encoding issue.'];
            }

            // Step 5: clean up the test file.
            if (!$disk->delete(self::TEST_FILE_NAME)) {
                return ['success' => false, 'message' => 'Test file could not be deleted — check delete permissions.'];
            }

            return ['success' => true, 'message' => 'Connection, upload, retrieval and cleanup tests succeeded.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Storage test failed: ' . $e->getMessage()];
        }
    }
}
