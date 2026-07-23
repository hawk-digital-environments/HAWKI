<?php
declare(strict_types=1);

namespace App\Utils;

use Illuminate\Console\OutputStyle;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * Collects numeric counters, informational log messages, and error messages
 * produced during a background job or sync operation.
 *
 * Implements {@see LoggerInterface} (via {@see LoggerTrait}), so the instance can be
 * passed directly wherever a PSR-3 logger is expected. Every logged message is
 * routed to one of two internal channels based on its severity:
 * - **Errors channel** — `error`, `critical`, `alert`, `emergency` levels.
 *   Presence is exposed via {@see hasErrors()}.
 * - **Logs channel** — all other levels, stored as `[LEVEL] message`.
 *
 * A third, independent channel holds named integer **counters** (e.g. "created",
 * "updated"). Every unknown key is implicitly initialised to zero on first access,
 * and counter methods (`increment`, `decrement`) return `$this` for chaining.
 *
 * An optional PSR-3 `$logger` can be injected at construction time. Every call to
 * {@see log()} will then also be forwarded to that logger with the job name prepended
 * as `[{jobName}] message`, enabling live monitoring alongside internal capture.
 *
 * **Typical usage inside a sync/background-job service:**
 * ```php
 * public function sync(LoggerInterface $logger): JobMetrics
 * {
 *     $metrics = new JobMetrics('AI Config Sync', $logger);
 *
 *     foreach ($this->items as $item) {
 *         try {
 *             $this->process($item);
 *             $metrics->increment('processed');
 *             $metrics->info(sprintf('Processed item %s', $item->id));
 *         } catch (\Throwable $e) {
 *             $metrics->error(sprintf('Failed: %s — %s', $item->id, $e->getMessage()));
 *         }
 *     }
 *
 *     return $metrics;
 * }
 *
 * // In a console command, render the collected metrics:
 * $metrics->writeToCli($output);
 *
 * // Combine results from independent sub-jobs, preserving chronological order:
 * $combined = $metricsA->mergeWith($metricsB);
 * ```
 */
final class JobMetrics implements \JsonSerializable, LoggerInterface
{
    use LoggerTrait;

    /**
     * An internal counter to track errors and logs, so, when merged the order of messages is preserved.
     * @var int
     */
    private static int $i = 0;

    private array $counts = [];
    private array $errors = [];
    private array $logs = [];

    /**
     * @param string $jobName Human-readable job identifier. Used as the CLI title in
     *                                      {@see writeToCli()}, prepended to messages forwarded to
     *                                      `$logger` as `[{jobName}] message`, and combined with the
     *                                      other instance's name in {@see mergeWith()} when they differ.
     * @param LoggerInterface|null $logger Optional PSR-3 logger to forward every logged message to.
     *                                      When provided, each {@see log()} call additionally writes
     *                                      to this logger, enabling real-time monitoring alongside
     *                                      internal capture for later summary reporting.
     */
    public function __construct(
        public readonly string                $jobName,
        private readonly LoggerInterface|null $logger = null
    )
    {
    }

    /**
     * Increments the counter identified by `$key` by one.
     *
     * If the key does not exist yet it is initialised to 0 before incrementing,
     * so the first call for a new key results in a value of 1.
     *
     * @param string $key Arbitrary counter identifier.
     * @return $this
     */
    public function increment(string $key): self
    {
        $this->initializeCount($key);
        $this->counts[$key]++;
        return $this;
    }

    /**
     * Decrements the counter identified by `$key` by one.
     *
     * If the key does not exist yet it is initialised to 0 before decrementing,
     * so the first call for a new key results in a value of -1.
     *
     * @param string $key Arbitrary counter identifier.
     * @return $this
     */
    public function decrement(string $key): self
    {
        $this->initializeCount($key);
        $this->counts[$key]--;
        return $this;
    }

    /**
     * Returns the current value of the counter identified by `$key`.
     *
     * Returns 0 if the key has never been incremented or decremented.
     *
     * @param string $key Arbitrary counter identifier.
     */
    public function get(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }

    /**
     * Returns all counters as an associative array keyed by their identifier.
     *
     * Only keys that have been touched via {@see increment()} or {@see decrement()}
     * are present; keys that were only read via {@see get()} are not included.
     *
     * @return array<string, int>
     */
    public function getAll(): array
    {
        return $this->counts;
    }

    /**
     * Returns `true` when at least one error has been recorded.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Returns all recorded error messages in insertion order.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return array_values($this->errors);
    }

    /**
     * Returns `true` when at least one log message has been recorded.
     */
    public function hasLogs(): bool
    {
        return !empty($this->logs);
    }

    /**
     * Records a message and routes it to the appropriate internal channel based on `$level`.
     *
     * **Level routing:**
     * - `error`, `critical`, `alert`, `emergency` → stored in the **errors** channel (see {@see getErrors()}).
     * - All other levels → stored in the **logs** channel as `[LEVEL] message` (see {@see getLogs()}).
     *
     * When a `$logger` was injected at construction, the message is also forwarded to it
     * with the job name prepended: `[{jobName}] message`.
     *
     * A process-wide static counter is used as the storage key so that messages from
     * different instances can be merged back in their original chronological order
     * (see {@see mergeWith()}). Call {@see resetCounter()} between tests to avoid
     * counter state leaking across test cases.
     *
     * Called internally by all {@see LoggerTrait} convenience methods (`error()`, `info()`, etc.).
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger?->log($level, $this->extendLoggerMessage($message), $context);

        if (is_string($level)) {
            $levelStr = $level;
        } else if (method_exists($level, '__toString') || $level instanceof \Stringable) {
            $levelStr = (string)$level;
        } else {
            $levelStr = get_debug_type($level);
        }

        $i = self::$i++;

        if (in_array($levelStr, [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY], true)) {
            $this->errors[$i] = $message;
            return;
        }

        $levelStr = strtoupper($levelStr);
        $this->logs[$i] = "[$levelStr] $message";
    }

    /**
     * Returns all recorded log messages in insertion order.
     *
     * @return array<string>
     */
    public function getLogs(): array
    {
        return array_values($this->logs);
    }

    /**
     * Serialises all three data channels into a plain array suitable for JSON encoding.
     *
     * Structure: `{ "counts": { "created": 3 }, "errors": ["…"], "logs": ["…"] }`
     */
    public function jsonSerialize(): array
    {
        return [
            'counts' => $this->counts,
            'errors' => $this->errors,
            'logs' => $this->logs,
        ];
    }

    /**
     * Creates a new instance that combines the counters, errors, and logs of this
     * instance with those of `$other`. Neither source instance is mutated.
     *
     * - **Counters** — same-key values are summed; keys unique to either instance are carried over.
     * - **Job name** — kept unchanged when both instances share the same name; otherwise combined
     *   as `"{this} + {other}"`.
     * - **Logger** — `$this->logger` takes precedence; falls back to `$other->logger` when absent.
     * - **Errors and logs** — interleaved by their original static counter keys, so the true
     *   chronological order of messages recorded across both instances is preserved.
     */
    public function mergeWith(self $other): self
    {
        $jobName = $this->jobName === $other->jobName ? $this->jobName : ($this->jobName . ' + ' . $other->jobName);
        $logger = $this->logger ?? $other->logger;

        $new = new self($jobName, $logger);
        $new->counts = $this->counts;

        foreach ($other->getAll() as $key => $value) {
            $this->initializeCount($key, $new);
            $new->counts[$key] += $value;
        }

        /**
         * Merges two lists that are assumed indexed by an auto-incrementing integer key, while preserving the order of messages.
         * Example: $list1 = [0 => 'a', 2 => 'b'], $list2 = [1 => 'c', 8 => 'd'] → merged = [0 => 'a', 1 => 'c', 2 => 'b', 8 => 'd']
         * @param array $list1
         * @param array $list2
         * @return array
         */
        $mergeListsWhileKeepingKeyOrder = static function (array $list1, array $list2): array {
            $merged = $list1 + $list2;
            ksort($merged);
            return $merged;
        };

        $new->errors = $mergeListsWhileKeepingKeyOrder($this->errors, $other->errors);
        $new->logs = $mergeListsWhileKeepingKeyOrder($this->logs, $other->logs);

        return $new;
    }

    /**
     * Renders the collected metrics as formatted CLI output.
     *
     * The title is taken from {@see $jobName} and suffixed with "(with errors)" when
     * any errors have been recorded. The output then consists of up to three sections,
     * each omitted when its channel is empty:
     * - **Statistics** — each counter printed as `- {title}: {value}`.
     * - **Logs** — each log message printed as `- {message}`.
     * - **Errors** — all error messages rendered as an error block.
     *
     * @return int The status code to return from the console command, where `0` indicates success and any non-zero value indicates an error.
     * By convention, this method returns `0` when no errors have been recorded and `1` otherwise, but you can customise this as needed when invoking the method.
     */
    public function writeToCli(
        OutputStyle $output
    ): int
    {
        $title = $this->jobName;
        if ($this->hasErrors()) {
            $title .= ' (with errors)';
        }
        $output->title($title);

        if (!empty($this->counts)) {
            $output->section('Statistics:');
            foreach ($this->counts as $key => $value) {
                $output->writeln("- $key: $value");
            }
        }

        if ($this->hasLogs()) {
            $output->section('Logs:');
            foreach ($this->logs as $log) {
                $output->writeln('- ' . $this->colorizeCliLogMessage($log));
            }
        }

        if ($this->hasErrors()) {
            $output->section('Errors:');
            $output->error(array_map(fn($line) => "- $line", $this->getErrors()));
        }

        $output->newLine();

        return $this->hasErrors() ? 1 : 0;
    }

    /**
     * If the metrics have been passed a logger,
     * you can call this method to send a log message indicating the start of the job.
     * The generated log will not be stored in the internal logs array and will only be sent to the injected logger.
     *
     * Note: This is a convenience method, there is no sanity check to ensure that it is only called once,
     * avoid calling it multiple times for the same instance to prevent confusion in the logs.
     */
    public function announceStart(): self
    {
        $this->logger?->info($this->extendLoggerMessage('Starting job'));
        return $this;
    }

    /**
     * If the metrics have been passed a logger,
     * you can call this method to send a log message indicating the completion of the job, along with a summary of the results.
     * The generated log will not be stored in the internal logs array and will only be sent to the injected logger.
     *
     * Note: This is a convenience method, there is no sanity check to ensure that it is only called once,
     * avoid calling it multiple times for the same instance to prevent confusion in the logs.
     */
    public function announceCompletion(): self
    {
        $hasErrors = $this->hasErrors();
        $message = $hasErrors ? 'Job completed with errors' : 'Job completed successfully';
        $context = $hasErrors ? ['counts' => $this->getAll(), 'error_count' => count($this->errors)] : ['counts' => $this->getAll()];
        $this->logger?->info($this->extendLoggerMessage($message), $context);
        return $this;
    }

    /**
     * Ensures that a counter identified by `$key` exists on the target instance, initialising it to zero if not already present.
     *
     * This is used internally before incrementing or decrementing counters to guarantee that they are always defined.
     */
    private function initializeCount(string $key, self|null $target = null): void
    {
        $target ??= $this;
        if (!array_key_exists($key, $target->counts)) {
            $target->counts[$key] = 0;
        }
    }

    /**
     * Prepends the job name to a log message for forwarding to the injected logger, ensuring consistent formatting for real-time monitoring.
     */
    private function extendLoggerMessage(string|\Stringable $message): string
    {
        return "[{$this->jobName}] $message";
    }

    /**
     * Adds ANSI color tags to a log message based on its level for enhanced readability in CLI output.
     *
     * - DEBUG messages are colored gray.
     * - WARNING messages are colored yellow.
     * - ERROR, CRITICAL, ALERT, and EMERGENCY messages are colored red.
     * - All other messages are returned unmodified.
     */
    private function colorizeCliLogMessage(string $message): string
    {
        $levelStr = strtolower(substr($message, 1, strpos($message, ']') - 1));
        return match ($levelStr) {
            LogLevel::DEBUG => "<fg=gray>$message</>",
            LogLevel::WARNING => "<fg=yellow>$message</>",
            LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY => "<fg=red>$message</>",
            default => $message,
        };
    }

    /**
     * A test helper to reset the internal counter used for ordering logs and errors when merging multiple JobMetrics instances.
     */
    public static function resetCounter(): void
    {
        self::$i = 0;
    }
}
