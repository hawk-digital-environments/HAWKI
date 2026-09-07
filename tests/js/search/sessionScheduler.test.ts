import {strict as assert} from 'node:assert';
import test, {describe} from 'node:test';
import {
    ConcurrencyGate,
    DynamicQueryRunner,
    type DynamicProviderOutcome,
    type DynamicProviderTask,
    type SchedulerTimers
} from '$lib/kernel/search/sessionScheduler.js';
import type {SearchEntry} from '$lib/kernel/search/types.js';

const TIMING = {debounceMs: 250, deadlineMs: 5000};

/** Lets every already-scheduled promise callback run. */
async function flush(rounds = 8): Promise<void> {
    for (let round = 0; round < rounds; round++) {
        await new Promise(resolve => setImmediate(resolve));
    }
}

class FakeTimers implements SchedulerTimers {
    private readonly pending = new Map<number, {due: number; handler: () => void}>();
    private nextHandle = 1;
    private now = 0;

    public setTimeout(handler: () => void, ms: number): number {
        const handle = this.nextHandle++;
        this.pending.set(handle, {due: this.now + ms, handler});
        return handle;
    }

    public clearTimeout(handle: number): void {
        this.pending.delete(handle);
    }

    /** Fires everything due within `ms`, in order, flushing promises in between. */
    public async advance(ms: number): Promise<void> {
        const target = this.now + ms;
        for (;;) {
            const due = [...this.pending.entries()]
                .filter(([, timer]) => timer.due <= target)
                .sort((a, b) => a[1].due - b[1].due)[0];
            if (!due) {
                break;
            }
            this.pending.delete(due[0]);
            this.now = due[1].due;
            due[1].handler();
            await flush();
        }
        this.now = target;
        await flush();
    }
}

interface Recorder {
    settled: {groupId: string; outcomes: readonly DynamicProviderOutcome[]}[];
    pending: boolean[];
}

function makeRunner(gate = new ConcurrencyGate(4)): {runner: DynamicQueryRunner; timers: FakeTimers; recorder: Recorder} {
    const timers = new FakeTimers();
    const recorder: Recorder = {settled: [], pending: []};
    const runner = new DynamicQueryRunner(gate, {
        onGroupSettled: (groupId, outcomes) => recorder.settled.push({groupId, outcomes}),
        onPendingChange: pending => recorder.pending.push(pending)
    }, TIMING, timers);
    return {runner, timers, recorder};
}

function task(providerId: string, groupId: string, search: DynamicProviderTask['search']): DynamicProviderTask {
    return {providerId, groupId, search};
}

const noEntries: readonly SearchEntry[] = [];

describe('ConcurrencyGate', () => {
    test('never runs more than its limit at once and drains every task', async () => {
        const gate = new ConcurrencyGate(4);
        const release: (() => void)[] = [];
        let running = 0;
        let peak = 0;

        const runs = Array.from({length: 10}, () => gate.run(() => {
            running++;
            peak = Math.max(peak, running);
            return new Promise<void>(resolve => release.push(() => {
                running--;
                resolve();
            }));
        }));

        await flush();
        assert.equal(peak, 4);

        while (release.length > 0) {
            release.shift()!();
            await flush();
        }
        await Promise.all(runs);
        assert.equal(peak, 4);
        assert.equal(gate.inFlight, 0);
    });
});

describe('DynamicQueryRunner', () => {
    test('debounces, so fast typing dispatches only the last input', async () => {
        const {runner, timers, recorder} = makeRunner();
        const calls: string[] = [];
        const provider = (query: string) => task('p', 'g', async () => {
            calls.push(query);
            return noEntries;
        });

        runner.start([provider('ch')]);
        await timers.advance(100);
        runner.start([provider('cha')]);
        await timers.advance(100);
        runner.start([provider('chat')]);
        assert.deepEqual(calls, []);

        await timers.advance(250);
        assert.deepEqual(calls, ['chat']);
        assert.equal(recorder.settled.length, 1);
    });

    test('publishes a group only once all its providers settled, failures included', async () => {
        const {runner, timers, recorder} = makeRunner();

        runner.start([
            task('ok', 'g', async () => [{id: 'a', entityKey: 'e/a', title: 'A', onSelect: () => undefined}]),
            task('bad', 'g', async () => {
                throw new Error('boom');
            })
        ]);
        await timers.advance(250);

        assert.equal(recorder.settled.length, 1, 'the group is published exactly once');
        const outcomes = [...recorder.settled[0].outcomes].sort((a, b) => a.providerId.localeCompare(b.providerId));
        assert.deepEqual(outcomes.map(outcome => outcome.status), ['failed', 'fulfilled']);
        assert.equal(outcomes[0].message, 'boom');
        assert.equal(outcomes[1].entries.length, 1);
    });

    test('turns the deadline into a retryable failure and aborts the request', async () => {
        const {runner, timers, recorder} = makeRunner();
        let aborted = false;

        runner.start([task('slow', 'g', signal => {
            signal.addEventListener('abort', () => {
                aborted = true;
            });
            return new Promise<readonly SearchEntry[]>(() => undefined);
        })]);

        await timers.advance(250);
        assert.equal(recorder.settled.length, 0);

        await timers.advance(5000);
        assert.equal(aborted, true);
        assert.equal(recorder.settled[0].outcomes[0].status, 'failed');
        assert.equal(runner.pending, false, 'a dead request must not hold the session pending');
    });

    test('settles a cancelled dispatch even when the provider ignores its signal', async () => {
        const {runner, timers, recorder} = makeRunner();
        let resolveLate: ((entries: readonly SearchEntry[]) => void) | null = null;

        runner.start([task('stubborn', 'g', () => new Promise<readonly SearchEntry[]>(resolve => {
            resolveLate = resolve;
        }))]);
        await timers.advance(250);
        assert.equal(runner.pending, true);

        runner.cancel();
        await flush();
        assert.equal(runner.pending, false);

        resolveLate!([{id: 'late', entityKey: 'e/late', title: 'Late', onSelect: () => undefined}]);
        await flush();
        assert.deepEqual(recorder.settled, [], 'a reply for a superseded query never reaches the session');
    });

    test('drops a reply that belongs to a superseded query', async () => {
        const {runner, timers, recorder} = makeRunner();
        const gates: ((entries: readonly SearchEntry[]) => void)[] = [];
        const provider = (id: string) => task('p', 'g', () => new Promise<readonly SearchEntry[]>(resolve => {
            gates.push(entries => resolve(entries.map(entry => ({...entry, title: id}))));
        }));

        runner.start([provider('old')]);
        await timers.advance(250);
        runner.start([provider('new')]);
        await timers.advance(250);

        gates[0]([{id: 'x', entityKey: 'e/x', title: '', onSelect: () => undefined}]);
        gates[1]([{id: 'x', entityKey: 'e/x', title: '', onSelect: () => undefined}]);
        await flush();

        assert.equal(recorder.settled.length, 1);
        assert.equal(recorder.settled[0].outcomes[0].entries[0].title, 'new');
    });

    test('suspension holds back dispatch and resuming flushes it at once', async () => {
        const {runner, timers} = makeRunner();
        let calls = 0;
        const composing = task('p', 'g', async () => {
            calls++;
            return noEntries;
        });

        runner.start([composing]);
        runner.suspend(true);
        await timers.advance(2000);
        assert.equal(calls, 0, 'nothing may be dispatched while an IME composition is open');
        assert.equal(runner.pending, true);

        runner.suspend(false);
        await flush();
        assert.equal(calls, 1, 'the finished composition is not made to wait out another debounce');
    });

    test('retries only the failed provider and republishes the whole group', async () => {
        const {runner, timers, recorder} = makeRunner();
        let attempts = 0;
        const flaky = task('flaky', 'g', async () => {
            attempts++;
            if (attempts === 1) {
                throw new Error('boom');
            }
            return [{id: 'b', entityKey: 'e/b', title: 'B', onSelect: () => undefined}];
        });
        let stableCalls = 0;
        const stable = task('stable', 'g', async () => {
            stableCalls++;
            return [{id: 'a', entityKey: 'e/a', title: 'A', onSelect: () => undefined}];
        });

        runner.start([stable, flaky]);
        await timers.advance(250);
        assert.equal(recorder.settled.length, 1);

        runner.retry(flaky);
        await flush();

        assert.equal(stableCalls, 1, 'a healthy sibling is not queried again');
        assert.equal(recorder.settled.length, 2);
        const outcomes = [...recorder.settled[1].outcomes].sort((a, b) => a.providerId.localeCompare(b.providerId));
        assert.deepEqual(outcomes.map(outcome => outcome.status), ['fulfilled', 'fulfilled']);
    });
});
