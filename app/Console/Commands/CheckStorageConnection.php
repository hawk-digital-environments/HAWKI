<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\WebDAV\WebDAVAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;
use Throwable;



class CheckStorageConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:storage {--filesystem=s3 : The filesystem to test (s3, local, public, localstorage, nextcloud, sftp)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Storage Connection for specified filesystem';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filesystem = $this->option('filesystem');

        $this->info("Testing {$filesystem} storage connection...");

        $result = match($filesystem) {
            's3' => $this->checkS3WriteAccess(),
            'local' => $this->checkLocalWriteAccess('local'),
            'public' => $this->checkLocalWriteAccess('public'),
            'localstorage' => $this->checkLocalWriteAccess('local_file_storage'),
            'nextcloud' => $this->checkNextCloudConnection(),
            'sftp' => $this->checkSFTPConnection(),
            default => ['success' => false, 'message' => "Unsupported filesystem: {$filesystem}"]
        };

        if($result['success']){
            $this->info($result['message']);
        } else {
            $this->error($result['message']);
        }
    }




    public function checkS3WriteAccess(string $disk = 's3', string $testFileName = 's3_test.txt'): array
    {
        try {
            // Step 1: Test S3 connection using Flysystem AWS S3 v3 adapter
            $config = config("filesystems.disks.{$disk}");

            if (empty($config['key']) || empty($config['secret']) || empty($config['region']) || empty($config['bucket'])) {
                return [
                    'success' => false,
                    'message' => 'S3 configuration incomplete. Check AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, and AWS_BUCKET environment variables.'
                ];
            }

            $client = new S3Client([
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
                'region' => $config['region'],
                'version' => $config['version'] ?? 'latest',
            ]);

            $adapter = new AwsS3V3Adapter($client, $config['bucket'], $config['prefix'] ?? '');

            // Test basic connectivity by listing objects
            iterator_to_array($adapter->listContents('', false));

            $content = "S3 connection test: " . now();

            // Step 2: Upload test file
            $uploadSuccess = Storage::disk($disk)->put($testFileName, $content);
            if (!$uploadSuccess) {
                return [
                    'success' => false,
                    'message' => "Upload failed — check write permissions."
                ];
            }

            // Step 3: Verify file exists
            if (!Storage::disk($disk)->exists($testFileName)) {
                return [
                    'success' => false,
                    'message' => "Upload succeeded but file not found — possible visibility/ACL issue."
                ];
            }

            // Step 4: Test file retrieval
            $retrievedContent = Storage::disk($disk)->get($testFileName);
            if ($retrievedContent !== $content) {
                return [
                    'success' => false,
                    'message' => 'Content mismatch — file corruption or encoding issue.'
                ];
            }

            // Step 5: Cleanup
            Storage::disk($disk)->delete($testFileName);

            return [
                'success' => true,
                'message' => "S3 connection, upload, retrieval, and cleanup tests all succeeded."
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "S3 test failed: " . $e->getMessage()
            ];
        }
    }

    public function checkLocalWriteAccess(string $disk = 'local_file_storage', string $testFileName = 'local_test.txt'): array
    {
        $content = "Local storage test: " . now();

        try {
            // Step 1: Upload test file
            $uploadSuccess = Storage::disk($disk)->put($testFileName, $content);
            if (!$uploadSuccess) {
                return [
                    'success' => false,
                    'message' => "Upload failed — check write permissions."
                ];
            }

            // Step 2: Verify file exists
            if (!Storage::disk($disk)->exists($testFileName)) {
                return [
                    'success' => false,
                    'message' => "Upload succeeded but file not found — possible file system issue."
                ];
            }

            // Step 3: Read back content
            $retrievedContent = Storage::disk($disk)->get($testFileName);
            if ($retrievedContent !== $content) {
                return [
                    'success' => false,
                    'message' => "Content mismatch — file corruption or encoding issue."
                ];
            }

            // Step 4: Cleanup
            Storage::disk($disk)->delete($testFileName);

            return [
                'success' => true,
                'message' => "Local storage connection and write test succeeded."
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Local storage test failed: " . $e->getMessage()
            ];
        }
    }

    public function checkNextCloudConnection(): array
    {
        $baseUrl = config('filesystems.disks.nextcloud.base_uri');
        $username = config('filesystems.disks.nextcloud.username');
        $password = config('filesystems.disks.nextcloud.password');
        $basePath = config('filesystems.disks.nextcloud.base_path', '/');

        // Step 1: Check configuration
        if (empty($baseUrl) || empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'NextCloud configuration incomplete. Check NEXTCLOUD_BASE_URL, NEXTCLOUD_USERNAME, and NEXTCLOUD_PASSWORD environment variables.'
            ];
        }

        try {
            // Step 2: Test WebDAV connection using Flysystem WebDAV adapter
            $webdavUrl = rtrim($baseUrl, '/') . '/remote.php/dav/files/' . $username . '/';

            $client = new \Sabre\DAV\Client([
                'baseUri' => $webdavUrl,
                'userName' => $username,
                'password' => $password,
            ]);

            $adapter = new WebDAVAdapter($client, trim($basePath, '/'));

            // Test basic connectivity by listing directory
            iterator_to_array($adapter->listContents('', false));

            // Step 3: Test file upload using Laravel Storage
            $testContent = "NextCloud connection test: " . now();
            $testFilename = 'connection-test/connection_test.txt';

            $uploadResult = Storage::disk('nextcloud')->put($testFilename, $testContent);

            if (!$uploadResult) {
                return [
                    'success' => false,
                    'message' => 'NextCloud connection succeeded but file upload failed.'
                ];
            }

            // Step 4: Test file retrieval
            $retrievedContent = Storage::disk('nextcloud')->get($testFilename);

            if (!$retrievedContent) {
                return [
                    'success' => false,
                    'message' => 'File upload succeeded but retrieval failed.'
                ];
            }

            if ($retrievedContent !== $testContent) {
                return [
                    'success' => false,
                    'message' => 'Content mismatch — file corruption or encoding issue.'
                ];
            }

            // Step 5: Cleanup
            Storage::disk('nextcloud')->delete($testFilename);

            return [
                'success' => true,
                'message' => 'NextCloud WebDAV connection, upload, retrieval, and cleanup tests all succeeded.'
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "NextCloud test failed: " . $e->getMessage()
            ];
        }
    }

    public function checkSFTPConnection(): array
    {
        $host = config('filesystems.disks.sftp.host');
        $port = config('filesystems.disks.sftp.port', 22);
        $username = config('filesystems.disks.sftp.username');
        $password = config('filesystems.disks.sftp.password');
        $basePath = config('filesystems.disks.sftp.base_path', '/');

        // Step 1: Check configuration
        if (empty($host) || empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'SFTP configuration incomplete. Check SFTP_HOST, SFTP_USERNAME, and SFTP_PASSWORD environment variables.'
            ];
        }

        try {
            // Step 2: Test SFTP connection using Flysystem SFTP v3 adapter
            $connectionProvider = SftpConnectionProvider::fromArray([
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'timeout' => 10,
            ]);

            $adapter = new SftpAdapter($connectionProvider, $basePath);

            // Step 3: Test basic connectivity by listing directory
            iterator_to_array($adapter->listContents('', false));

            // Step 4: Test file upload using Laravel Storage
            $testContent = "SFTP connection test: " . now();
            $testFilename = 'connection-test/connection_test.txt';

            $uploadResult = Storage::disk('sftp')->put($testFilename, $testContent);

            if (!$uploadResult) {
                return [
                    'success' => false,
                    'message' => 'SFTP connection succeeded but file upload failed.'
                ];
            }

            // Step 5: Test file retrieval
            $retrievedContent = Storage::disk('sftp')->get($testFilename);

            if ($retrievedContent === false) {
                return [
                    'success' => false,
                    'message' => 'File upload succeeded but retrieval failed.'
                ];
            }

            if ($retrievedContent !== $testContent) {
                return [
                    'success' => false,
                    'message' => 'Content mismatch — file corruption or encoding issue.'
                ];
            }

            // Step 6: Cleanup
            Storage::disk('sftp')->delete($testFilename);

            return [
                'success' => true,
                'message' => 'SFTP connection, upload, retrieval, and cleanup tests all succeeded.'
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "SFTP test failed: " . $e->getMessage()
            ];
        }
    }
}
