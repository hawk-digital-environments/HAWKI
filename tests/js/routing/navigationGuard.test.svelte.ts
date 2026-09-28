import assert from 'node:assert/strict';
import { test } from 'node:test';
import { flushSync } from 'svelte';
import { createRouter } from '../../../resources/js/components/ui/routing/logistics/router.js';
import { createTransientRoutingStrategy } from '../../../resources/js/components/ui/routing/strategy/transientRoutingStrategy.svelte.js';
import type { RouteComponent } from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';

const Page: RouteComponent = () => {};

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

test('a vetoing guard that reads and writes reactive state runs once and pulls the path back', async () => {
    const strategy = createTransientRoutingStrategy();
    const router = createRouter(
        'test',
        (routes) => {
            routes.route('/a', Page, { name: 'a' });
            routes.route('/b', Page, { name: 'b' });
        },
        { strategy }
    );
    strategy.set('/a');
    const stop = $effect.root(() => router.bind());
    flushSync();
    await tick();
    assert.equal(router.path, '/a');

    // Mirrors a guard that marks missing fields before vetoing: it reads the
    // state it then replaces.
    let errors = $state<Record<string, string>>({});
    let calls = 0;
    router.handle.registerNavigationGuard(() => {
        calls++;
        errors = { ...errors, name: 'required' };
        return false;
    });

    void router.handle.goTo('/b');
    flushSync();
    await tick();

    assert.equal(calls, 1);
    assert.equal(strategy.get(), '/a');
    assert.equal(router.path, '/a');
    stop();
});
