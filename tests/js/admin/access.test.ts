import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import UniversalRouter from 'universal-router';
import generateUrls from 'universal-router/generateUrls';
import {RouteRegistrar} from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';
import {RouteHttpError, RouteRedirect} from '../../../resources/js/components/ui/routing/logistics/signals.js';
import {authMetaGuards} from '../../../resources/js/kernel/routing/middlewares/AuthMiddleware.js';
import {can} from '../../../resources/js/kernel/auth/permissions.js';
import {InternalAuthenticatedConnectionSchema} from '../../../resources/js/app/schemas/resources/connections.schema.js';
import {registerAdminRoutes} from '../../../resources/js/plugins/admin/routes.js';
import {sections} from '../../../resources/js/plugins/admin/sections.js';
import {authRouter} from '../auth/routerFixture.js';

function connection(permissions: string[]) {
    return InternalAuthenticatedConnectionSchema.parse({
        id: 'hawki', type: 'internal_authenticated', version: 'test', locale: 'en_US',
        userinfo: {id: 5, username: 'tester', name: 'Tester', email: 'tester@example.test', avatar: null, bio: null, hash: 'test-hash', permissions},
        keychain_state: 'initialized',
    });
}

function router(permissions: string[], authenticated = true) {
    Object.assign(globalThis, {window: {location: {pathname: '/new/admin', search: '', hash: ''}}});
    const registrar = new RouteRegistrar({metaGuards: authMetaGuards});
    registrar.group('/admin', registerAdminRoutes);
    return new UniversalRouter(registrar.build(), {baseUrl: '/new', context: {
        app: {connectionOrNull: authenticated ? connection(permissions) : null, cryptoReady: false, router: authRouter()},
    }});
}

test('every admin link resolves without chat keys, with its own section permission', async () => {
    const instance = router(['admin.access', ...sections.map(section => section.permission)]);
    const urls = generateUrls(instance);
    assert.equal(urls('admin.index'), '/new/admin');
    assert.equal((await instance.resolve(urls('admin.index'))).context.route.name, 'admin.index');
    for (const section of sections) {
        const result = await instance.resolve(urls(`admin.${section.id}`));
        assert.equal(result.context.route.name, `admin.${section.id}`);
        assert.equal(result.context.route.meta.permission, section.permission);
    }
});

test('panel permission and section permission are independently enforced', async () => {
    for (const section of sections) {
        for (const permissions of [['admin.access'], [section.permission]]) {
            await assert.rejects(router(permissions).resolve(`/new/admin/${section.id}`), error => error instanceof RouteHttpError && error.status === 403);
        }
    }
    await assert.rejects(router([], false).resolve('/new/admin'), error => error instanceof RouteRedirect && error.target === 'auth.login');
});

test('permission checks react to revocation and require an authenticated connection', () => {
    const session = connection(['admin.access']);
    assert.equal(can(session, 'admin.access'), true);
    session.userinfo.permissions = [];
    assert.equal(can(session, 'admin.access'), false);
    assert.equal(can(null, 'admin.access'), false);
});
