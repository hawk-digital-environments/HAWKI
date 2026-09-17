import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import UniversalRouter from 'universal-router';
import generateUrls from 'universal-router/generateUrls';
import type {IconComponent} from '../../../resources/js/components/ui/icons/index.js';
import {RouteRegistrar} from '../../../resources/js/components/ui/routing/logistics/RouteRegistrar.js';
import {RouteHttpError, RouteRedirect} from '../../../resources/js/components/ui/routing/logistics/signals.js';
import {authMetaGuards} from '../../../resources/js/kernel/routing/middlewares/AuthMiddleware.js';
import type {HawkiModuleWithPlugin} from '../../../resources/js/kernel/modules/types.js';
import {InternalAuthenticatedConnectionSchema} from '../../../resources/js/app/schemas/resources/connections.schema.js';
import type {AdminWorkspaceRegistrar} from '../../../resources/js/plugins/admin/api.js';
import {AdminRegistry} from '../../../resources/js/plugins/admin/registry.js';
import {registerAdminRoutes} from '../../../resources/js/plugins/admin/routes.js';
import {builtInSections, builtInWorkspaces} from '../../../resources/js/plugins/admin/workspaces.js';
import {authRouter} from '../auth/routerFixture.js';

const loader = async () => ({}) as any;
const icon = {} as IconComponent;

function moduleWithPlugin<T extends {name: string}>(module: T, plugin: {name: string; isCorePlugin: boolean}) {
    return Object.assign(module, {plugin}) as T & HawkiModuleWithPlugin;
}

function populatedRegistry() {
    const registry = new AdminRegistry();
    // Declares what AdminModule declares, from the same data; the real module pulls Svelte components into Node.
    const adminModule = moduleWithPlugin({
        name: 'admin',
        adminWorkspaces({section, workspace}: AdminWorkspaceRegistrar) {
            for (const id of builtInSections) section({id, icon, title: `admin.groups.${id}`});
            for (const entry of builtInWorkspaces) {
                workspace({...entry, title: `admin.sections.${entry.id}`, description: `admin.descriptions.${entry.id}`, page: loader});
            }
        }
    }, {name: 'admin', isCorePlugin: true});
    const chatModule = moduleWithPlugin({
        name: 'chat',
        adminWorkspaces({workspace}: AdminWorkspaceRegistrar) {
            workspace({
                id: 'prompts',
                section: 'ai',
                title: 'chat.admin.prompts',
                description: 'chat.admin.prompts_description',
                page: loader
            });
        }
    }, {name: 'core', isCorePlugin: true});
    const billingModule = moduleWithPlugin({
        name: 'billing',
        adminWorkspaces({section, workspace}: AdminWorkspaceRegistrar) {
            section({id: 'billing', icon, title: 'billing.admin.title'});
            workspace({
                id: 'invoices',
                section: 'billing',
                title: 'billing.admin.invoices',
                description: 'billing.admin.invoices_description',
                page: loader
            });
        }
    }, {name: 'Billing', isCorePlugin: false});
    registry.collect([adminModule, chatModule, billingModule]);
    return registry;
}

const registry = populatedRegistry();

function connection(isAdmin: boolean) {
    return InternalAuthenticatedConnectionSchema.parse({
        id: 'hawki', type: 'internal_authenticated', version: 'test', locale: 'en_US',
        userinfo: {id: 5, username: 'tester', name: 'Tester', email: 'tester@example.test', avatar: null, bio: null, hash: 'test-hash', isAdmin},
        keychain_state: 'initialized',
    });
}

function router(isAdmin: boolean, authenticated = true) {
    Object.assign(globalThis, {window: {location: {pathname: '/new/admin', search: '', hash: ''}}});
    const registrar = new RouteRegistrar({metaGuards: authMetaGuards});
    registrar.group('/admin', (nested) => registerAdminRoutes(nested, registry));
    return new UniversalRouter(registrar.build(), {baseUrl: '/new', context: {
        app: {isAdmin: authenticated && isAdmin, connectionOrNull: authenticated ? connection(isAdmin) : null, cryptoReady: false, router: authRouter()},
    }});
}

test('every admin link resolves without chat keys, for an administrator', async () => {
    const instance = router(true);
    const urls = generateUrls(instance);
    assert.equal(urls('admin.index'), '/new/admin');
    assert.equal((await instance.resolve(urls('admin.index'))).context.route.name, 'admin.index');
    for (const workspace of builtInWorkspaces) {
        const result = await instance.resolve(urls(`admin.${workspace.id}`));
        assert.equal(result.context.route.name, `admin.${workspace.id}`);
        assert.equal(result.context.route.meta.admin, true);
    }
    assert.equal(urls('admin.prompts'), '/new/admin/prompts');
    assert.equal(urls('admin.plugins.billing.invoices'), '/new/admin/plugins/billing/invoices');
});

test('tools share the MCP workspace permission without a separate route', () => {
    assert.equal(builtInWorkspaces.some((workspace) => String(workspace.id) === 'tools'), false);
    assert.ok(builtInWorkspaces.find((workspace) => workspace.id === 'mcp'));
});

test('all admin workspaces reject non-admins and redirect anonymous visitors', async () => {
    for (const workspace of registry.workspaces) {
        await assert.rejects(router(false).resolve(`/new/admin${workspace.path}`), error => error instanceof RouteHttpError && error.status === 403);
    }
    await assert.rejects(router(false, false).resolve('/new/admin'), error => error instanceof RouteRedirect && error.target === 'auth.login');
});

test('registry orders built-in and added sections and resolves workspace names', () => {
    assert.deepEqual(registry.sections.map(section => section.name), [
        'admin:admin:ai',
        'admin:admin:people',
        'admin:admin:system',
        'Billing:billing:billing'
    ]);
    assert.equal(registry.workspace('prompts'), registry.workspace('core:chat:prompts'));
});

test('two core-plugin modules cannot claim the same workspace URL', () => {
    const duplicateRegistry = new AdminRegistry();
    const first = moduleWithPlugin({
        name: 'catalog',
        adminWorkspaces({section, workspace}: AdminWorkspaceRegistrar) {
            section({id: 'catalog', icon, title: 'catalog.title'});
            workspace({id: 'models', section: 'catalog', title: 'catalog.models', description: 'catalog.models_description', page: loader});
        }
    }, {name: 'core', isCorePlugin: true});
    const second = moduleWithPlugin({
        name: 'inventory',
        adminWorkspaces({section, workspace}: AdminWorkspaceRegistrar) {
            section({id: 'inventory', icon, title: 'inventory.title'});
            workspace({id: 'models', section: 'inventory', title: 'inventory.models', description: 'inventory.models_description', page: loader});
        }
    }, {name: 'inventory', isCorePlugin: true});
    assert.throws(() => duplicateRegistry.collect([first, second]), /core:catalog.*inventory:inventory|inventory:inventory.*core:catalog/);
});

test('a workspace cannot name an unknown section', () => {
    const unknownRegistry = new AdminRegistry();
    const module = moduleWithPlugin({
        name: 'chat',
        adminWorkspaces({workspace}: AdminWorkspaceRegistrar) {
            workspace({id: 'prompts', section: 'missing', title: 'prompts.title', description: 'prompts.description', page: loader});
        }
    }, {name: 'core', isCorePlugin: true});
    assert.throws(() => unknownRegistry.collect([module]), /core:chat.*unknown section "missing"/);
});

test('different modules cannot declare the same section id', () => {
    const duplicateRegistry = new AdminRegistry();
    const modules = ['first', 'second'].map(name => moduleWithPlugin({
        name,
        adminWorkspaces({section}: AdminWorkspaceRegistrar) {
            section({id: 'billing', icon, title: 'billing.title'});
        }
    }, {name: 'core', isCorePlugin: true}));

    assert.throws(() => duplicateRegistry.collect(modules), /core:second.*core:first/);
});

test('admin routes require a collected registry', () => {
    assert.throws(
        () => registerAdminRoutes(new RouteRegistrar({metaGuards: authMetaGuards}), new AdminRegistry()),
        /before the Admin Registry has collected Module Workspaces/
    );
});
