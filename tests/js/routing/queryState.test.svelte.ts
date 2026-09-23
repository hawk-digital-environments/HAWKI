import assert from 'node:assert/strict';
import { test } from 'node:test';
import { flushSync } from 'svelte';
import { createRouter } from '../../../resources/js/components/ui/routing/logistics/router.js';
import { configurePage } from '../../../resources/js/components/ui/routing/logistics/routeConfig.js';
import { createQueryState } from '../../../resources/js/components/ui/routing/hooks/useQueryState.svelte.js';
import { createTransientRoutingStrategy } from '../../../resources/js/components/ui/routing/strategy/transientRoutingStrategy.svelte.js';
import type { RouteComponent } from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';

const Page: RouteComponent = () => {};

function fixture() {
    let loads = 0;
    const strategy = createTransientRoutingStrategy();
    const router = createRouter(
        'test',
        (routes) => {
            routes.route('/models', Page, {
                name: 'models',
                config: configurePage({ loadData: async () => ({ n: ++loads }) })
            });
            routes.route('/providers', Page, { name: 'providers' });
        },
        { strategy }
    );
    return { router, handle: router.handle, strategy, loads: () => loads };
}

async function settled(router: ReturnType<typeof fixture>['router']) {
    for (let i = 0; i < 50 && router.state === 'loading'; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
    assert.equal(router.state, 'waiting');
}

test('reads, writes and removes a string parameter without touching path or fragment', () => {
    const { handle } = fixture();
    void handle.goTo('/models#top');
    const seen: (string | null)[] = [];
    const stop = $effect.root(() => {
        const provider = createQueryState(handle, 'provider_id');
        $effect(() => {
            seen.push(provider.current);
        });
        flushSync();
        assert.equal(provider.current, null);

        provider.current = 'open ai';
        flushSync();
        assert.equal(provider.current, 'open ai');
        assert.equal(handle.query.get('provider_id'), 'open ai');

        provider.current = null;
        flushSync();
        assert.equal(handle.query.has('provider_id'), false);
    });
    stop();
    assert.deepEqual(seen, [null, 'open ai', null]);
});

test('setQuery merges into the existing query and keeps the fragment', () => {
    const { handle, strategy } = fixture();
    void handle.goTo('/models?sort=label#top');
    handle.setQuery({ provider_id: 'x', page: '2' });
    assert.equal(strategy.get(), '/models?sort=label&provider_id=x&page=2#top');
    assert.deepEqual(
        [...handle.query.entries()],
        [
            ['sort', 'label'],
            ['provider_id', 'x'],
            ['page', '2']
        ]
    );
    handle.setQuery({ sort: null, page: undefined });
    assert.deepEqual([...handle.query.entries()], [['provider_id', 'x']]);
    // Each read is a fresh copy; mutating it is not a write.
    handle.query.set('provider_id', 'y');
    assert.equal(handle.query.get('provider_id'), 'x');
});

test('assigning query state inside an effect does not subscribe it to navigation', () => {
    const { handle } = fixture();
    void handle.goTo('/models?sort=label');
    let selected = $state<string | null>('first');
    let writes = 0;
    const stop = $effect.root(() => {
        const provider = createQueryState(handle, 'provider_id');
        $effect(() => {
            writes++;
            provider.current = selected;
        });
    });
    try {
        flushSync();
        assert.equal(writes, 1);
        assert.equal(handle.query.get('provider_id'), 'first');

        handle.setQuery({ sort: 'model_id' });
        flushSync();
        assert.equal(writes, 1);

        selected = 'second';
        flushSync();
        assert.equal(writes, 2);
        assert.equal(handle.query.get('provider_id'), 'second');

        void handle.goTo('/providers');
        flushSync();
        assert.equal(writes, 2);
        assert.equal(
            handle.query.has('provider_id'),
            false,
            'leaving the page must not copy its filter to the next URL'
        );
    } finally {
        stop();
    }
});

test('multiple query states share updates and read external navigation immediately', () => {
    const { handle } = fixture();
    void handle.goTo('/models?provider_id=first');
    const stop = $effect.root(() => {
        const provider = createQueryState(handle, 'provider_id');
        const anotherProvider = createQueryState(handle, 'provider_id');
        const sort = createQueryState(handle, 'sort', { defaultValue: 'label' });
        assert.equal(provider.current, 'first');
        provider.current = 'a & b/+?';
        sort.current = 'model_id';
        assert.equal(anotherProvider.current, 'a & b/+?');
        assert.equal(sort.current, 'model_id');

        void handle.goTo('/models?provider_id=back');
        assert.equal(provider.current, 'back');
        assert.equal(anotherProvider.current, 'back');
        assert.equal(sort.current, 'label');
    });
    stop();
});

test('query state replaces history by default and can push entries', (t) => {
    const { handle, strategy } = fixture();
    void handle.goTo('/models#top');
    const set = t.mock.method(strategy, 'set');
    const stop = $effect.root(() => {
        const provider = createQueryState(handle, 'provider_id');
        provider.current = 'first';
        assert.deepEqual(set.mock.calls.at(-1)?.arguments, ['/models?provider_id=first#top', { replace: true }]);

        const tab = createQueryState(handle, 'tab', { history: 'push' });
        tab.current = 'pricing';
        assert.deepEqual(set.mock.calls.at(-1)?.arguments, [
            '/models?provider_id=first&tab=pricing#top',
            { replace: false }
        ]);
    });
    stop();
});

test('parses and serializes typed values and keeps the default out of the URL', () => {
    const { handle } = fixture();
    void handle.goTo('/models');
    const stop = $effect.root(() => {
        const page = createQueryState(handle, 'page', {
            defaultValue: 1,
            parse: (raw) => (/^\d+$/.test(raw) ? Number(raw) : null)
        });
        assert.equal(page.current, 1);
        page.current = 3;
        flushSync();
        assert.equal(handle.query.get('page'), '3');
        assert.equal(page.current, 3);
        page.current = 1;
        flushSync();
        assert.equal(handle.query.has('page'), false);
        assert.equal(page.current, 1);
        handle.setQuery({ page: 'abc' });
        flushSync();
        assert.equal(page.current, 1, 'an unparsable value falls back to the default');
    });
    stop();
});

test('a query change neither re-resolves the route nor re-runs its loader', async () => {
    const { router, handle, loads } = fixture();
    const stop = $effect.root(() => {
        router.bind();
    });
    try {
        void handle.goTo('/models');
        flushSync();
        await settled(router);
        assert.equal(loads(), 1);
        assert.equal(router.path, '/models');

        let resolutions = 0;
        const stopWatch = $effect.root(() => {
            $effect(() => {
                void router.state;
                resolutions++;
            });
        });
        flushSync();
        resolutions = 0;

        handle.setQuery({ provider_id: 'x' });
        flushSync();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(router.state, 'waiting');
        assert.equal(resolutions, 0, 'router state never left waiting');
        assert.equal(loads(), 1);
        assert.equal(router.path, '/models', 'the published path carries no query');
        assert.equal(handle.query.get('provider_id'), 'x');

        // `goTo()` with the same path and another query is a query change too.
        void handle.goTo('/models?provider_id=y');
        flushSync();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(loads(), 1);
        assert.equal(handle.query.get('provider_id'), 'y');

        // A different path resolves as before and takes the query along.
        void handle.goTo('/providers?tab=2');
        flushSync();
        await settled(router);
        assert.equal(router.path, '/providers');
        assert.equal(handle.query.get('tab'), '2');
        stopWatch();
    } finally {
        stop();
    }
});
