<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use App\Utils\JobMetrics;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\RequestException;

/**
 * Downloads the full LiteLLM model catalog and writes it to static PHP files on disk.
 *
 * One PHP file is created per provider under {@see StaticLiteLlmDataStore::getStorageDirectoryPath()},
 * named `provider-{slug}.php`. Each file is a valid PHP script that returns an array of raw
 * model records, exactly as received from the API.
 *
 * These files are committed to the repository and serve as an offline fallback for
 * {@see StaticLiteLlmDataStore} when the live API is unavailable.
 *
 * Invoked exclusively by {@see \App\Console\Commands\Dev\UpdateLiteLlmStaticDataCommand}.
 */
readonly class StaticLiteLlmDataUpdater
{
    public function __construct(
        private LiteLlmApiClient       $apiClient,
        private StaticLiteLlmDataStore $dataStore,
        private Filesystem             $filesystem
    )
    {
    }

    /**
     * Downloads all records from the LiteLLM API and writes them to per-provider static files.
     *
     * Existing files are deleted before writing so that removed or renamed providers do not
     * leave stale files behind. If the download fails or yields no records, the existing files
     * are kept and the failure is reported as an error on the returned {@see JobMetrics}.
     */
    public function run(): JobMetrics
    {
        /** @var array<string, resource> $fileToWrite */
        $fileToWrite = [];
        $addToFile = function (string $providerName, string $content) use (&$fileToWrite) {
            if (!array_key_exists($providerName, $fileToWrite)) {
                $filePath = $this->dataStore->getProviderFilePath($providerName);
                $fileToWrite[$providerName] = $this->openFileForWriting($filePath);
            }
            fwrite($fileToWrite[$providerName], $content);
        };
        $addPhpArrayAsSubArrayToFile = static function (string $providerName, array $subArray) use ($addToFile) {
            $content = "\n    " . var_export($subArray, true) . ",";
            $addToFile($providerName, $content);
        };

        $metrics = new JobMetrics('Static Lite LLM Data Updater');

        $metrics->announceStart();

        // Download everything before touching the existing files, so a failed request leaves them intact
        try {
            $catalog = $this->apiClient->fetchRawCatalog()->catalog;
        } catch (\Throwable $e) {
            $metrics->error($this->describeFetchFailure($e));
            $metrics->info('The existing static LiteLLM data files were left untouched.');
            $metrics->announceCompletion();
            return $metrics;
        }

        $clearedExistingFiles = false;
        foreach ($this->apiClient->normalizeCatalog($catalog) as $row) {
            // Only delete the old files once the catalog actually yielded a record, so that a
            // catalog without any recognizable model records also leaves the existing files intact
            if (!$clearedExistingFiles) {
                $this->clearExistingStaticLiteLlmDataFiles($metrics);
                $clearedExistingFiles = true;
            }

            try {
                $addPhpArrayAsSubArrayToFile($row['provider'], $row);

                $metrics->increment('Models of "' . $row['provider'] . '" processed');
            } catch (\Throwable $e) {
                $metrics->error('Error processing row', ['row' => $row, 'exception' => $e]);
            }
        }

        $metrics->debug('Closing all open files');
        foreach ($fileToWrite as $providerName => $fileHandle) {
            $this->closeFile($fileHandle);
            $metrics->debug('Closed file for provider ' . $providerName);
        }

        if ($fileToWrite === []) {
            $metrics->error(sprintf(
                'The LiteLLM catalog at %s contained no model records. The URL may be wrong or the response format may have changed.',
                $this->apiClient->getUrl()
            ));
            $metrics->info('The existing static LiteLLM data files were left untouched.');
        }

        $metrics->announceCompletion();

        return $metrics;
    }

    /** Turns a failed catalog download into a human-readable message for the command output. */
    private function describeFetchFailure(\Throwable $e): string
    {
        $url = $this->apiClient->getUrl();

        if ($e instanceof RequestException) {
            return sprintf('The LiteLLM catalog at %s responded with HTTP %d.', $url, $e->response->status());
        }

        return sprintf('Failed to download the LiteLLM catalog from %s: %s', $url, $e->getMessage());
    }

    /** Deletes the entire static data directory so stale provider files do not persist across runs. */
    private function clearExistingStaticLiteLlmDataFiles(JobMetrics $metrics): void
    {
        $metrics->debug('Clearing existing static Lite LLM data files');
        $storagePath = $this->dataStore->getStorageDirectoryPath();
        $this->filesystem->deleteDirectory($storagePath);
    }

    /**
     * Creates any missing parent directories, opens $filePath for writing, and writes the
     * PHP file header (opening `return [` statement).
     *
     * @return resource
     */
    private function openFileForWriting(string $filePath)
    {
        $directory = dirname($filePath);
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $fileHandle = fopen($filePath, 'wb');
        if ($fileHandle === false) {
            throw new \RuntimeException("Failed to open file for writing: $filePath");
        }

        $header = <<<HEADER
<?php
declare(strict_types=1);
/**
 * This file is auto-generated by StaticLiteLlmDataUpdater.
 * Do not edit this file manually.
 */

return [
HEADER;

        fwrite($fileHandle, $header);

        return $fileHandle;
    }

    /**
     * Writes the closing `];` of the PHP array and closes the file handle.
     *
     * @param resource $fileHandle
     */
    private function closeFile($fileHandle): void
    {
        fwrite($fileHandle, "\n];\n");
        fclose($fileHandle);
    }
}
