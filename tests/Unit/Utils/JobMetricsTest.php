<?php
declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\JobMetrics;
use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tests\TestCase;

#[CoversClass(JobMetrics::class)]
class JobMetricsTest extends TestCase
{
    private JobMetrics $sut;

    protected function setUp(): void
    {
        parent::setUp();
        JobMetrics::resetCounter();
        $this->sut = new JobMetrics('test-job');
    }

    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(JobMetrics::class, new JobMetrics('test'));
    }

    public function testItConstructExposesJobName(): void
    {
        static::assertSame('My Job', (new JobMetrics('My Job'))->jobName);
    }

    // =========================================================================

    public function testItReturnsZeroForUnknownKey(): void
    {
        static::assertSame(0, $this->sut->get('unknown'));
    }

    public function testItIncrementInitialisesKeyToOneOnFirstCall(): void
    {
        $this->sut->increment('created');

        static::assertSame(1, $this->sut->get('created'));
    }

    public function testItIncrementAccumulatesMultipleCalls(): void
    {
        $this->sut->increment('created');
        $this->sut->increment('created');
        $this->sut->increment('created');

        static::assertSame(3, $this->sut->get('created'));
    }

    public function testItIncrementReturnsSameInstance(): void
    {
        $result = $this->sut->increment('created');

        static::assertSame($this->sut, $result);
    }

    public function testItIncrementTracksMultipleKeysIndependently(): void
    {
        $this->sut->increment('created');
        $this->sut->increment('created');
        $this->sut->increment('updated');

        static::assertSame(2, $this->sut->get('created'));
        static::assertSame(1, $this->sut->get('updated'));
    }

    // =========================================================================

    public function testItDecrementInitialisesKeyToNegativeOneOnFirstCall(): void
    {
        $this->sut->decrement('deleted');

        static::assertSame(-1, $this->sut->get('deleted'));
    }

    public function testItDecrementAccumulatesMultipleCalls(): void
    {
        $this->sut->decrement('deleted');
        $this->sut->decrement('deleted');

        static::assertSame(-2, $this->sut->get('deleted'));
    }

    public function testItDecrementReturnsSameInstance(): void
    {
        $result = $this->sut->decrement('deleted');

        static::assertSame($this->sut, $result);
    }

    public function testItDecrementReducesPreviouslyIncrementedKey(): void
    {
        $this->sut->increment('count');
        $this->sut->increment('count');
        $this->sut->decrement('count');

        static::assertSame(1, $this->sut->get('count'));
    }

    // =========================================================================

    public function testItGetAllReturnsEmptyArrayWhenNothingTracked(): void
    {
        static::assertSame([], $this->sut->getAll());
    }

    public function testItGetAllReturnsAllTrackedCounters(): void
    {
        $this->sut->increment('created');
        $this->sut->increment('created');
        $this->sut->increment('updated');
        $this->sut->decrement('deleted');

        static::assertSame(
            ['created' => 2, 'updated' => 1, 'deleted' => -1],
            $this->sut->getAll()
        );
    }

    public function testItGetAllDoesNotIncludeKeysThatWereOnlyRead(): void
    {
        $this->sut->get('read_only_key');

        static::assertSame([], $this->sut->getAll());
    }

    // =========================================================================

    public function testItHasErrorsReturnsFalseWhenNoErrorsRecorded(): void
    {
        static::assertFalse($this->sut->hasErrors());
    }

    public function testItHasErrorsReturnsTrueAfterFirstError(): void
    {
        $this->sut->error('something went wrong');

        static::assertTrue($this->sut->hasErrors());
    }

    public function testItGetErrorsReturnsEmptyArrayWhenNoErrors(): void
    {
        static::assertSame([], $this->sut->getErrors());
    }

    public function testItGetErrorsReturnsAllRecordedErrorsInInsertionOrder(): void
    {
        $this->sut->error('first error');
        $this->sut->error('second error');
        $this->sut->error('third error');

        static::assertSame(
            ['first error', 'second error', 'third error'],
            $this->sut->getErrors()
        );
    }

    // =========================================================================

    public function testItHasLogsReturnsFalseWhenNoLogsRecorded(): void
    {
        static::assertFalse($this->sut->hasLogs());
    }

    public function testItHasLogsReturnsTrueAfterFirstLog(): void
    {
        $this->sut->info('something happened');

        static::assertTrue($this->sut->hasLogs());
    }

    public function testItGetLogsReturnsEmptyArrayWhenNoLogs(): void
    {
        static::assertSame([], $this->sut->getLogs());
    }

    public function testItGetLogsReturnsAllRecordedLogsInInsertionOrder(): void
    {
        $this->sut->info('first');
        $this->sut->info('second');
        $this->sut->info('third');

        static::assertSame(
            ['[INFO] first', '[INFO] second', '[INFO] third'],
            $this->sut->getLogs()
        );
    }

    public function testItLogRoutesErrorLevelToErrorsChannel(): void
    {
        $this->sut->error('oh no');

        static::assertSame(['oh no'], $this->sut->getErrors());
        static::assertSame([], $this->sut->getLogs());
    }

    public function testItLogRoutesCriticalAlertEmergencyLevelsToErrorsChannel(): void
    {
        $this->sut->critical('critical');
        $this->sut->alert('alert');
        $this->sut->emergency('emergency');

        static::assertSame(['critical', 'alert', 'emergency'], $this->sut->getErrors());
        static::assertSame([], $this->sut->getLogs());
    }

    public function testItLogRoutesNonErrorLevelsToLogsChannelWithUppercasePrefix(): void
    {
        $this->sut->debug('trace detail');
        $this->sut->info('all good');
        $this->sut->warning('heads up');
        $this->sut->notice('fyi');

        static::assertSame([], $this->sut->getErrors());
        static::assertSame(
            ['[DEBUG] trace detail', '[INFO] all good', '[WARNING] heads up', '[NOTICE] fyi'],
            $this->sut->getLogs()
        );
    }

    public function testItLogForwardsToInjectedLoggerWithJobNamePrefix(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('log')
            ->with(LogLevel::INFO, '[my-job] hello', []);

        (new JobMetrics('my-job', $logger))->info('hello');
    }

    public function testItLogForwardsContextToInjectedLogger(): void
    {
        $context = ['key' => 'value'];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('log')
            ->with(LogLevel::ERROR, '[job] msg', $context);

        (new JobMetrics('job', $logger))->error('msg', $context);
    }

    public function testItLogDoesNotRequireInjectedLogger(): void
    {
        // Should not throw when no logger is injected.
        $this->sut->info('hello');
        static::assertTrue(true);
    }

    // =========================================================================

    public function testItJsonSerializesEmptyState(): void
    {
        static::assertSame(
            ['counts' => [], 'errors' => [], 'logs' => []],
            $this->sut->jsonSerialize()
        );
    }

    public function testItJsonSerializesWithAllData(): void
    {
        // Counter starts at 0 (resetCounter() called in setUp).
        $this->sut->increment('created')->increment('created');
        $this->sut->error('something went wrong'); // stored at key 0
        $this->sut->info('something happened');     // stored at key 1

        static::assertSame(
            [
                'counts' => ['created' => 2],
                'errors' => [0 => 'something went wrong'],
                'logs'   => [1 => '[INFO] something happened'],
            ],
            $this->sut->jsonSerialize()
        );
    }

    // =========================================================================

    public function testItMergeWithReturnsNewInstance(): void
    {
        $result = $this->sut->mergeWith(new JobMetrics('other'));

        static::assertNotSame($this->sut, $result);
    }

    public function testItMergeWithDoesNotMutateOriginal(): void
    {
        $this->sut->increment('created');
        $other = (new JobMetrics('other'))->increment('created')->increment('created');

        $this->sut->mergeWith($other);

        static::assertSame(1, $this->sut->get('created'));
    }

    public function testItMergeWithSumsSharedCounters(): void
    {
        $this->sut->increment('created');
        $other = (new JobMetrics('other'))->increment('created')->increment('created');

        $result = $this->sut->mergeWith($other);

        static::assertSame(3, $result->get('created'));
    }

    public function testItMergeWithPreservesCountersNotPresentInOther(): void
    {
        $this->sut->increment('created');
        $other = (new JobMetrics('other'))->increment('updated');

        $result = $this->sut->mergeWith($other);

        static::assertSame(1, $result->get('created'));
        static::assertSame(1, $result->get('updated'));
    }

    public function testItMergeWithCombinesJobNamesWhenDifferent(): void
    {
        $result = $this->sut->mergeWith(new JobMetrics('other-job'));

        static::assertSame('test-job + other-job', $result->jobName);
    }

    public function testItMergeWithKeepsSameJobNameWhenEqual(): void
    {
        $result = $this->sut->mergeWith(new JobMetrics('test-job'));

        static::assertSame('test-job', $result->jobName);
    }

    public function testItMergeWithPrefersThisLogger(): void
    {
        $loggerA = $this->createMock(LoggerInterface::class);
        $loggerA->expects(static::once())->method('log');

        $loggerB = $this->createMock(LoggerInterface::class);
        $loggerB->expects(static::never())->method('log');

        (new JobMetrics('a', $loggerA))
            ->mergeWith(new JobMetrics('b', $loggerB))
            ->info('test');
    }

    public function testItMergeWithFallsBackToOtherLoggerWhenThisHasNone(): void
    {
        $loggerB = $this->createMock(LoggerInterface::class);
        $loggerB->expects(static::once())->method('log');

        $this->sut->mergeWith(new JobMetrics('b', $loggerB))->info('test');
    }

    public function testItMergeWithAppendsErrorsFromOther(): void
    {
        $this->sut->error('error A');         // key 0
        $other = new JobMetrics('other');
        $other->error('error B');              // key 1

        $result = $this->sut->mergeWith($other);

        static::assertSame(['error A', 'error B'], $result->getErrors());
    }

    public function testItMergeWithAppendsLogsFromOther(): void
    {
        $this->sut->info('log A');             // key 0
        $other = new JobMetrics('other');
        $other->info('log B');                 // key 1

        $result = $this->sut->mergeWith($other);

        static::assertSame(['[INFO] log A', '[INFO] log B'], $result->getLogs());
    }

    public function testItMergeWithPreservesInsertionOrderAcrossInstances(): void
    {
        // Simulates two sub-jobs logging interleaved messages.
        $jobA = new JobMetrics('A');
        $jobB = new JobMetrics('B');

        $jobA->info('A: step 1');   // key 0
        $jobB->info('B: step 1');   // key 1
        $jobA->error('A: failed');  // key 2
        $jobB->info('B: step 2');   // key 3

        $result = $jobA->mergeWith($jobB);

        static::assertSame(['A: failed'], $result->getErrors());
        static::assertSame(
            ['[INFO] A: step 1', '[INFO] B: step 1', '[INFO] B: step 2'],
            $result->getLogs()
        );
    }

    // =========================================================================

    public function testItResetCounterResetsStaticCounter(): void
    {
        $m1 = new JobMetrics('test');
        $m1->info('first');         // stored at key 0; counter advances to 1

        JobMetrics::resetCounter(); // counter reset to 0

        $m2 = new JobMetrics('test');
        $m2->info('second');        // also stored at key 0; counter advances to 1 again

        // Both instances recorded at key 0. The union operator keeps the left side on
        // key collision, so only one message survives the merge.
        $result = $m1->mergeWith($m2);
        static::assertCount(1, $result->getLogs());
    }

    // =========================================================================

    public function testItWriteToCliOutputsJobNameAsTitle(): void
    {
        $output = $this->createMock(OutputStyle::class);
        $output->expects(static::once())->method('title')->with('test-job');

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliAppendErrorsSuffixToTitleWhenErrorsPresent(): void
    {
        $this->sut->error('oops');

        $output = $this->createMock(OutputStyle::class);
        $output->expects(static::once())->method('title')->with('test-job (with errors)');
        $output->method('section');
        $output->method('error');

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliOutputsStatisticsSectionWithRawKey(): void
    {
        $this->sut->increment('model_count');

        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->expects(static::once())->method('section')->with('Statistics:');
        $output->expects(static::once())->method('writeln')->with('- model_count: 1');

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliReturnsZeroWhenNoErrors(): void
    {
        $output = $this->createMock(OutputStyle::class);
        $output->method('title');

        static::assertSame(0, $this->sut->writeToCli($output));
    }

    public function testItWriteToCliReturnsOneWhenErrorsPresent(): void
    {
        $this->sut->error('oops');

        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->method('section');
        $output->method('error');

        static::assertSame(1, $this->sut->writeToCli($output));
    }

    public function testItWriteToCliOutputsLogsSection(): void
    {
        $this->sut->info('something happened');

        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->expects(static::once())->method('section')->with('Logs:');
        $output->expects(static::once())->method('writeln')->with('- [INFO] something happened');

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliColorizesDebugMessagesGray(): void
    {
        $this->sut->debug('trace detail');

        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->method('section');
        $output->expects(static::once())->method('writeln')->with('- <fg=gray>[DEBUG] trace detail</>');

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliColorizesWarningMessagesYellow(): void
    {
        $this->sut->warning('heads up');

        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->method('section');
        $output->expects(static::once())->method('writeln')->with('- <fg=yellow>[WARNING] heads up</>');

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliDoesNotColorizeInfoAndNoticeMessages(): void
    {
        $this->sut->notice('fyi');

        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->method('section');
        $output->expects(static::once())->method('writeln')->with('- [NOTICE] fyi');

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliOutputsErrorsSection(): void
    {
        $this->sut->error('something failed');

        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->expects(static::once())->method('section')->with('Errors:');
        $output->expects(static::once())->method('error')->with(['- something failed']);

        $this->sut->writeToCli($output);
    }

    public function testItWriteToCliSkipsAllSectionsWhenEmpty(): void
    {
        $output = $this->createMock(OutputStyle::class);
        $output->method('title');
        $output->expects(static::never())->method('section');
        $output->expects(static::never())->method('writeln');
        $output->expects(static::never())->method('error');

        $this->sut->writeToCli($output);
    }

    // =========================================================================

    public function testItAnnounceStartForwardsToInjectedLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with('[my-job] Starting job');

        (new JobMetrics('my-job', $logger))->announceStart();
    }

    public function testItAnnounceStartDoesNothingWithoutLogger(): void
    {
        // Should not throw when no logger is injected.
        $this->sut->announceStart();
        static::assertTrue(true);
    }

    public function testItAnnounceStartReturnsSameInstance(): void
    {
        static::assertSame($this->sut, $this->sut->announceStart());
    }

    public function testItAnnounceStartDoesNotRecordToInternalLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info');

        (new JobMetrics('job', $logger))->announceStart();

        // announceStart() bypasses the internal log channel.
        static::assertFalse($this->sut->hasLogs());
    }

    public function testItAnnounceCompletionForwardsSuccessMessageToInjectedLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with('[my-job] Job completed successfully', ['counts' => []]);

        (new JobMetrics('my-job', $logger))->announceCompletion();
    }

    public function testItAnnounceCompletionForwardsErrorMessageWhenErrorsPresent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with(
                '[my-job] Job completed with errors',
                ['counts' => [], 'error_count' => 1]
            );

        $metrics = new JobMetrics('my-job', $logger);
        $metrics->error('something failed');
        $metrics->announceCompletion();
    }

    public function testItAnnounceCompletionIncludesCountsInContext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with(
                '[my-job] Job completed successfully',
                ['counts' => ['processed' => 3]]
            );

        $metrics = new JobMetrics('my-job', $logger);
        $metrics->increment('processed');
        $metrics->increment('processed');
        $metrics->increment('processed');
        $metrics->announceCompletion();
    }

    public function testItAnnounceCompletionDoesNothingWithoutLogger(): void
    {
        // Should not throw when no logger is injected.
        $this->sut->announceCompletion();
        static::assertTrue(true);
    }

    public function testItAnnounceCompletionReturnsSameInstance(): void
    {
        static::assertSame($this->sut, $this->sut->announceCompletion());
    }

    public function testItAnnounceCompletionDoesNotRecordToInternalLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info');

        (new JobMetrics('job', $logger))->announceCompletion();

        static::assertFalse($this->sut->hasLogs());
    }
}
