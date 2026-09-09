import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import UniversalRouter from 'universal-router';
import generateUrls from 'universal-router/generateUrls';
import {RouteRegistrar} from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';

const Page = () => {};

test('built-in plugins sharing a route prefix register each callback once', async () => {
    const calls: string[] = [];
    const registrar = new RouteRegistrar();
    for (const [plugin, path, name] of [
        ['auth', '/auth/login', 'auth.login'],
        ['core', '/', 'home'],
        ['extra', '/extra', 'extra.index']
    ]) {
        registrar.group('', child => {
            calls.push(plugin);
            child.route(path, Page, {name});
        }, {name: `plugin.${plugin}`});
    }

    const router = new UniversalRouter(registrar.build(), {baseUrl: '/new'});
    assert.deepEqual(calls, ['auth', 'core', 'extra']);
    for (const [path, name] of [
        ['/new/auth/login', 'auth.login'],
        ['/new/', 'home'],
        ['/new/extra', 'extra.index']
    ]) {
        const result = await router.resolve(path);
        assert.equal(result.context.route.name, name);
    }
    const urls = generateUrls(router);
    assert.equal(urls('auth.login'), '/new/auth/login');
    assert.equal(urls('home'), '/new');
});

test('metadata guards execute only for the matched leaf, including nested routes and 404', async () => {
    const calls: string[] = [];
    const registrar = new RouteRegistrar({metaGuards: meta => async (_ctx, next) => {
        calls.push(String(meta.access ?? 'crypto-ready'));
        return next();
    }});
    registrar.route('/', Page);
    registrar.group('', core => core.route('/auth/login', Page, {name: 'auth.login', meta: {access: 'public'}}));
    registrar.group('/chat', child => child.route('/:id', Page, {name: 'chat.show'}));
    registrar.route('/*unmatched', Page, {name: 'not-found', catchAll: true});
    const router = new UniversalRouter(registrar.build(), {baseUrl: '/new'});
    await router.resolve('/new/auth/login');
    assert.deepEqual(calls.splice(0), ['public']);
    const chat = await router.resolve('/new/chat/hello');
    assert.equal(chat.context.route.name, 'chat.show');
    assert.deepEqual(calls.splice(0), ['crypto-ready']);
    const missing = await router.resolve('/new/missing/path');
    assert.equal(missing.context.route.name, 'not-found');
    assert.deepEqual(calls, ['crypto-ready']);
    const urls = generateUrls(router, {stringifyQueryParams: params => new URLSearchParams(params).toString()});
    assert.equal(urls('auth.login', {next: '/new/chat'}), '/new/auth/login?next=%2Fnew%2Fchat');
});
