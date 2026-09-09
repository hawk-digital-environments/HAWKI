import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import UniversalRouter from 'universal-router';
import generateUrls from 'universal-router/generateUrls';
import {RouteRegistrar} from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';

const Page = () => {};

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
