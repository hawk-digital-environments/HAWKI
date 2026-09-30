import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {readFileSync} from 'node:fs';
import UniversalRouter from 'universal-router';
import {RouteRegistrar} from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';
import {welcomeSteps} from '../../../resources/js/plugins/auth/pages/welcome/welcomeSteps.js';
import {registerWelcomeRoutes, welcomeRouteName, type WelcomeStepMeta} from '../../../resources/js/plugins/auth/pages/welcome/welcomeRoutes.js';

test('the onboarding ends with the passkey step matching the configured mode', () => {
    assert.deepEqual(welcomeSteps(false), ['encryption', 'groups', 'passkey']);
    assert.deepEqual(welcomeSteps(true), ['encryption', 'groups', 'automaticPasskey']);
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
    for (const step of steps) {
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
        for (const step of new Set([...welcomeSteps(false), ...welcomeSteps(true)])) {
            for (const part of ['title', 'body', 'action']) {
                assert.equal(typeof welcome[step]?.[part], 'string', `${locale}: ${step}.${part}`);
            }
        }
    }
});
