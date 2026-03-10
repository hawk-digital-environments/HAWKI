<?php
declare(strict_types=1);

namespace App\Services\System\Health\Value;

/**
 * Persists lightweight health-check state in a JSON marker file.
 *
 * This storage keeps:
 * - whether the last execution failed
 * - a quick-test counter
 *
 * It also caches values in memory and only reloads from disk when the
 * underlying file modification time changes.
 */
class HealthTimerStorage
{
    /**
     * Absolute path to the JSON marker file.
     */
    private readonly string $filePath;

    /**
     * Cached file modification timestamp used for stale-check detection.
     */
    private int|null $fileLastModified = null;

    /**
     * Cached state flag indicating if the last execution failed.
     */
    private bool|null $lastExecutionFailed = null;

    /**
     * Cached quick-test counter value.
     */
    private int|null $quickTestCounter = null;

    /**
     * @param string|null $filePath Optional custom marker file path.
     *                              Defaults to the system temp directory.
     */
    public function __construct(
        string|null $filePath = null
    )
    {
        if ($filePath === null) {
            $this->filePath = sys_get_temp_dir() . '/hawki_health_timer_marker.json';
        } else {
            $this->filePath = $filePath;
        }
    }

    /**
     * Marks the most recent execution as failed and persists the change.
     */
    public function markAsFailed(): void
    {
        $this->load();
        if ($this->lastExecutionFailed === true) {
            return;
        }
        $this->lastExecutionFailed = true;
        $this->save();
    }

    /**
     * Marks the most recent execution as healthy and persists the change.
     */
    public function markAsHealthy(): void
    {
        $this->load();
        if ($this->lastExecutionFailed === false) {
            return;
        }
        $this->lastExecutionFailed = false;
        $this->save();
    }

    /**
     * Returns whether the last recorded execution failed.
     */
    public function hasLastExecutionFailed(): bool
    {
        $this->load();
        return $this->lastExecutionFailed === true;
    }

    /**
     * Returns the current quick-test counter value.
     */
    public function getQuickTestCounter(): int
    {
        $this->load();
        return $this->quickTestCounter ?? 0;
    }

    /**
     * Increments the quick-test counter, persists it, and returns the new value.
     */
    public function increaseCounter(): int
    {
        $this->load();
        $this->quickTestCounter = ($this->quickTestCounter ?? 0) + 1;
        $this->save();
        return $this->quickTestCounter;
    }

    /**
     * Resets the quick-test counter to zero and persists it.
     */
    public function resetCounter(): void
    {
        $this->load();
        $this->quickTestCounter = 0;
        $this->save();
    }

    /**
     * Loads state from disk if needed.
     *
     * Uses the marker file mtime to skip disk reads when cached values are still
     * fresh. If the file is missing or unreadable/invalid, defaults are applied.
     */
    private function load(): void
    {
        if ($this->fileLastModified !== null && is_file($this->filePath)) {
            $currentLastModified = filemtime($this->filePath);
            if ($currentLastModified !== false && $currentLastModified === $this->fileLastModified) {
                return;
            }
        }

        $this->fileLastModified = null;
        $this->lastExecutionFailed = false;
        $this->quickTestCounter = 0;

        if (!is_file($this->filePath)) {
            return;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            return;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return;
            }
        } catch (\JsonException) {
            return;
        }

        $this->fileLastModified = filemtime($this->filePath);
        $this->lastExecutionFailed = $data['last_execution_failed'] ?? false;
        $this->quickTestCounter = $data['quick_test_counter'] ?? 0;
    }

    /**
     * Persists the current state to disk as JSON and refreshes cached mtime.
     */
    private function save(): void
    {
        $data = [
            'last_execution_failed' => $this->lastExecutionFailed,
            'quick_test_counter' => $this->quickTestCounter,
        ];

        file_put_contents($this->filePath, json_encode($data, JSON_THROW_ON_ERROR));
        $this->fileLastModified = filemtime($this->filePath);
    }
}
