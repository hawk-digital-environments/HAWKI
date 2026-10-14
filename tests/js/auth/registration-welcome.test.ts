import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {readFileSync} from 'node:fs';
import UniversalRouter from 'universal-router';
import {RouteRegistrar} from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';
import {welcomeSteps} from '../../../resources/js/plugins/auth/pages/welcome/welcomeSteps.js';
import {registerWelcomeRoutes, welcomeRouteName, type WelcomeStepMeta} from '../../../resources/js/plugins/auth/pages/welcome/welcomeRoutes.js';

const ids = (steps: {id: string}[]) => steps.map(step => step.id);

test('the onboarding ends with the passkey step matching the configured mode', () => {
    assert.deepEqual(ids(welcomeSteps(false)), ['encryption', 'groups', 'passkey']);
    assert.deepEqual(ids(welcomeSteps(true)), ['encryption', 'groups', 'automaticPasskey']);
});

test('configured steps come first, in their order, and the passkey step still ends the onboarding', () => {
    const custom = {type: 'custom' as const, emoji: null, title: 'House rules', text: 'Be kind.', action: null};
    const steps = welcomeSteps(false, [custom, {type: 'builtin', step: 'groups', title: 'Together', text: null}]);
    assert.deepEqual(ids(steps), ['step-1', 'groups', 'passkey']);
    assert.deepEqual(steps[0].custom, {emoji: null, title: 'House rules', text: 'Be kind.', action: null});
    assert.equal(steps[1].builtin, 'groups');
    // A built-in step carries the administrator's texts; unset ones fall back to the translation.
    assert.deepEqual(steps[1].overrides, {title: 'Together', text: null});
    assert.deepEqual(ids(welcomeSteps(true, [])), ['automaticPasskey']);
});

test('the onboarding routes start at the root and link every step to its neighbours', async () => {
    const steps = welcomeSteps(true);
    const page = () => {};
    const registrar = new RouteRegistrar();
    registerWelcomeRoutes(registrar, steps, page as any);
    const router = new UniversalRouter(registrar.build());

    const root = await router.resolve('/');
    assert.equal((root.context.route.meta as WelcomeStepMeta).step, 'encryption');
    assert.equal(root.component, page);

    const metas: WelcomeStepMeta[] = [];
    for (const step of ids(steps)) {
        const result = await router.resolve(`/${step}`);
        assert.equal(result.context.route.name, welcomeRouteName(step));
        metas.push(result.context.route.meta as WelcomeStepMeta);
    }
    assert.deepEqual(metas.map(meta => [meta.previous, meta.next]), [
        [undefined, 'welcome.groups'],
        ['welcome.encryption', 'welcome.automaticPasskey'],
        ['welcome.groups', undefined]
    ]);
});

test('every onboarding step is translated in each language file', () => {
    for (const locale of ['de_DE', 'en_US']) {
        const messages = JSON.parse(readFileSync(new URL(`../../../resources/language/ui_${locale}.json`, import.meta.url), 'utf8'));
        const welcome = messages.ui.auth.register.welcome;
        assert.equal(typeof welcome.back, 'string', `${locale}: back label`);
        assert.equal(typeof welcome.next, 'string', `${locale}: default label of a custom step`);
        for (const step of new Set([...ids(welcomeSteps(false)), ...ids(welcomeSteps(true))])) {
            for (const part of ['title', 'body', 'action']) {
                assert.equal(typeof welcome[step]?.[part], 'string', `${locale}: ${step}.${part}`);
            }
        }
    }
});
