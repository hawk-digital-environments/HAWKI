<?php
declare(strict_types=1);


namespace App\Services\System\Health;


use App\Events\HealthCheckEvent;
use App\Services\System\Health\Exception\HealthcheckFailedException;
use App\Services\System\Health\Value\HealthCheckResult;
use App\Services\System\Health\Value\HealthCheckResultCollection;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[Singleton]
readonly class HealthChecker
{
    public const CHECK_NAME_DB_QUICK = 'quick_database';
    public const CHECK_NAME_DB = 'database';
    public const CHECK_NAME_CACHE = 'cache';
    public const CHECK_NAME_REDIS = 'redis';
    public const CHECK_NAME_STORAGE = 'storage';

    public function __construct(
        private LoggerInterface          $logger,
        private EventDispatcherInterface $eventDispatcher,
        private HealthTimer              $timer
    )
    {
    }

    /**
     * Perform a health check based on the current timer state.
     * If the timer indicates a quick test, perform a quick check.
     * If the timer indicates a deep test, perform a comprehensive check of all components.
     *
     * @return HealthCheckResultCollection An array of health check results for each component.
     */
    public function check(): HealthCheckResultCollection
    {
        if ($this->timer->getTestType() === HealthTimer::TEST_TYPE_QUICK) {
            $result = $this->quickCheck();
            return new HealthCheckResultCollection(...$result);
        }

        return $this->deepCheck();
    }

    /**
     * Perform a quick health check that only verifies basic connectivity.
     * This is designed to be fast and is suitable for frequent checks (e.g., every 30 seconds).
     */
    public function quickCheck(): HealthCheckResult
    {
        try {
            $responseTime = $this->trackTime(function () {
                DB::connection()->getPdo();
            });

            // Do NOT mark the check as healthy here -> ONLY the DEEP check should mark it as healthy.

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_DB_QUICK,
                status: HealthCheckResult::STATUS_OK,
                message: 'Quick database check successful',
                responseTime: $responseTime
            );
        } catch (\Throwable $e) {
            $this->timer->markAsFailed();
            return new HealthCheckResult(
                checkName: self::CHECK_NAME_DB_QUICK,
                status: HealthCheckResult::STATUS_ERROR,
                message: 'Quick database check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Perform a deep health check that verifies connectivity and basic operations for all critical components.
     * This is more resource-intensive and should be run less frequently (e.g., after a failure or every 10 quick checks).
     */
    public function deepCheck(): HealthCheckResultCollection
    {
        /** @var HealthCheckEvent $e */
        $e = $this->eventDispatcher->dispatch(
            new HealthCheckEvent(
                new HealthCheckResultCollection(
                    $this->checkDatabase(),
                    $this->checkCache(),
                    $this->checkRedis(),
                    $this->checkStorage()
                )
            )
        );

        $results = $e->getResults();

        if ($results->isOk()) {
            $this->timer->markAsHealthy();
        } else {
            $this->timer->markAsFailed();
        }

        return $results;
    }

    /**
     * Check database connectivity and basic operations.
     */
    private function checkDatabase(): HealthCheckResult
    {
        try {
            $responseTime = $this->trackTime(function () {
                $connection = DB::connection();
                $connection->getPdo();
                $connection->select('SELECT 1');
            });

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_DB,
                status: HealthCheckResult::STATUS_OK,
                message: 'Database connection successful',
                responseTime: $responseTime
            );
        } catch (\Throwable $e) {
            $this->logger->error('Database health check failed: ' . $e->getMessage(), ['exception' => $e]);

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_DB,
                status: HealthCheckResult::STATUS_ERROR,
                message: 'Database connection failed'
            );
        }
    }

    /**
     * Check cache system functionality.
     */
    private function checkCache(): HealthCheckResult
    {
        try {
            $responseTime = $this->trackTime(function () {
                $testKey = 'health_check_' . time();
                $testValue = 'test_value';

                Cache::put($testKey, $testValue, 10);
                $retrieved = Cache::get($testKey);
                Cache::forget($testKey);

                if ($retrieved !== $testValue) {
                    throw new HealthcheckFailedException('Cache read/write verification failed');
                }
            });

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_CACHE,
                status: HealthCheckResult::STATUS_OK,
                message: 'Cache system is operational',
                responseTime: $responseTime
            );
        } catch (\Throwable $e) {
            $this->logger->error('Cache health check failed: ' . $e->getMessage(), ['exception' => $e]);

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_CACHE,
                status: HealthCheckResult::STATUS_ERROR,
                message: $e instanceof HealthcheckFailedException ? 'Cache system check failed: ' . $e->getMessage() : 'Cache system check failed'
            );
        }
    }

    /**
     * Check Redis connectivity.
     */
    private function checkRedis(): HealthCheckResult
    {
        try {
            $responseTime = $this->trackTime(function () {
                Redis::ping();
            });

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_REDIS,
                status: HealthCheckResult::STATUS_OK,
                message: 'Redis connection successful',
                responseTime: $responseTime
            );
        } catch (\Throwable $e) {
            $this->logger->error('Redis health check failed: ' . $e->getMessage(), ['exception' => $e]);

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_REDIS,
                status: HealthCheckResult::STATUS_ERROR,
                message: 'Redis connection failed'
            );
        }
    }

    /**
     */
    private function checkStorage(): HealthCheckResult
    {
        try {
            $responseTime = $this->trackTime(function () {
                $testFile = storage_path('framework/cache/health_check_' . time() . '.tmp');

                if (!is_writable(storage_path('framework/cache'))) {
                    throw new HealthcheckFailedException('Storage directory is not writable');
                }

                file_put_contents($testFile, 'health_check');

                if (!is_file($testFile)) {
                    throw new HealthcheckFailedException('Failed to write test file');
                }

                unlink($testFile);
            });

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_STORAGE,
                status: HealthCheckResult::STATUS_OK,
                message: 'Storage is writable',
                responseTime: $responseTime
            );
        } catch (\Throwable $e) {
            $this->logger->error('Storage health check failed: ' . $e->getMessage(), ['exception' => $e]);

            return new HealthCheckResult(
                checkName: self::CHECK_NAME_STORAGE,
                status: HealthCheckResult::STATUS_ERROR,
                message: $e instanceof HealthcheckFailedException ? 'Storage check failed: ' . $e->getMessage() : 'Storage check failed'
            );
        }
    }

    /**
     * Utility method to track the execution time of a given operation.
     *
     * @param callable $operation The operation to execute and time.
     * @return float The execution time in milliseconds, rounded to 2 decimal places.
     */
    private function trackTime(
        callable $operation
    ): float
    {
        $startTime = microtime(true);
        $operation();
        return round((microtime(true) - $startTime) * 1000, 2);
    }
}
