<?php
declare(strict_types=1);


namespace App\Services\System\Health;


use App\Services\System\Health\Value\HealthTimerStorage;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class HealthTimer
{
    public const TEST_TYPE_QUICK = 'quick';
    public const TEST_TYPE_DEEP = 'deep';

    private readonly int $deepTestEveryXQuickTests;
    private readonly HealthTimerStorage $storage;

    /**
     * HealthTimer constructor.
     *
     * We do not want to run deep health checks on every request, as they can be resource-intensive and may impact performance.
     * Instead, we want to run quick health checks more frequently, and only run deep health checks when necessary
     * (e.g., after a failure or after a certain number of quick checks).
     *
     * Yes, this has the tradeoff, that if the system becomes unhealthy, it may take some time until the next deep check is triggered,
     * but it also ensures that we do not overload the system with deep checks when it is healthy.
     *
     * To ensure encapsulation we use our own storage class instead of relying on Laravel's cache or database, which allows usage even where they become unavailable due to system issues.
     *
     * @param string|null|HealthTimerStorage $storageFilePath Optional file path for storing health check state. If null, a default temporary file will be used. If a HealthTimerStorage instance is provided, it will be used directly (for tests).
     * @param int $deepTestEveryXQuickTests Number of quick tests to perform before forcing a deep test. Default is 10.
     */
    public function __construct(
        string|null|HealthTimerStorage $storageFilePath = null,
        int                            $deepTestEveryXQuickTests = 10

    )
    {
        $this->storage = $storageFilePath instanceof HealthTimerStorage ? $storageFilePath : new HealthTimerStorage($storageFilePath);
        $this->deepTestEveryXQuickTests = $deepTestEveryXQuickTests;
    }

    /**
     * Determines whether to perform a quick or deep health check based
     * on the last execution result and the number of quick tests performed.
     * @return string
     */
    public function getTestType(): string
    {
        if ($this->storage->hasLastExecutionFailed()) {
            return self::TEST_TYPE_DEEP;
        }

        $counter = $this->storage->getQuickTestCounter();
        if ($counter >= $this->deepTestEveryXQuickTests) {
            $this->storage->resetCounter();
            return self::TEST_TYPE_DEEP;
        }

        $this->storage->increaseCounter();
        return self::TEST_TYPE_QUICK;
    }

    /**
     * Marks the last health check execution as failed, which will trigger a deep test on the next check.
     * The idea is, to run deep checks every time until the system is healthy again, to ensure that all issues are detected and resolved.
     */
    public function markAsFailed(): void
    {
        $this->storage->markAsFailed();
    }

    /**
     * Marks the last health check execution as healthy, which will allow the system to perform quick tests again until the next deep test is due.
     * @return void
     */
    public function markAsHealthy(): void
    {
        $this->storage->markAsHealthy();
    }
}
