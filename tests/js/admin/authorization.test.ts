import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { readFileSync } from 'node:fs';
import { AdminContentSchema, AdminFieldSchema } from '../../../resources/js/plugins/admin/schemas/admin-content.js';
import { AdminToolSchema } from '../../../resources/js/plugins/admin/schemas/resources/admin-tools.schema.js';
import { AdminUserSchema } from '../../../resources/js/plugins/admin/schemas/resources/admin-users.schema.js';
import {
    permissionChoices,
    permissionGroups,
    changePermission,
    roleLabel
} from '../../../resources/js/plugins/admin/forms/authorization.js';
import { editorSchema } from '../../../resources/js/plugins/admin/forms/schemas.js';

const catalog = AdminContentSchema.parse({
    rows: [],
    permission_catalog: [
        {
            name: 'tools.use',
            group: 'tools',
            title_label: 'admin.permissions.tools.use.title',
            description_label: 'admin.permissions.tools.use.description',
            grantable: true
        },
        {
            name: 'roles.manage',
            group: 'administration',
            title_label: 'admin.permissions.roles.manage.title',
            description_label: 'admin.permissions.roles.manage.description',
            grantable: false
        }
    ]
}).permission_catalog;

test('permission controls preserve existing ungrantable and retired grants without allowing new ones', () => {
    const selected = ['roles.manage', 'retired.permission'];
    const options = permissionChoices(catalog, selected);
    assert.deepEqual(
        options.map((entry) => entry.name),
        ['tools.use', 'roles.manage', 'retired.permission']
    );
    assert.equal(options[2].grantable, false);
    assert.deepEqual(changePermission(catalog, selected, 'roles.manage', false), selected);
    assert.deepEqual(changePermission(catalog, [], 'roles.manage', true), []);
    assert.deepEqual(changePermission(catalog, selected, 'tools.use', true), [...selected, 'tools.use']);
    assert.deepEqual(changePermission(catalog, [...selected, 'tools.use'], 'tools.use', false), selected);
});

test('permission groups follow the catalog instead of a hardcoded list, with administration first', () => {
    assert.deepEqual(permissionGroups(catalog), ['administration', 'tools']);
    assert.deepEqual(permissionGroups(permissionChoices(catalog, ['retired.permission'])), ['administration', 'tools']);
    // An unseen group still gets a section of its own, in the order the backend sent it.
    const extended = [...catalog, { ...catalog[0], name: 'labs.use', group: 'labs' }] as typeof catalog;
    assert.deepEqual(permissionGroups(extended), ['administration', 'tools', 'labs']);
    assert.deepEqual(permissionGroups([]), []);
});

test('admin contracts retain authorization metadata and fail closed for absent tool rules', () => {
    const content = AdminContentSchema.parse({
        rows: [],
        permission_catalog: catalog,
        access_rules: [
            {
                name: 'web_search',
                title_label: 'admin.tool_access_rules.web_search.title',
                description_label: 'admin.tool_access_rules.web_search.description',
                permissions: ['tools.use', 'ai.capabilities.web_search.use'],
                grantable: false
            }
        ],
        role_catalog: [
            { id: 7, name: 'Researchers', slug: 'researchers', is_system: false },
            { id: 1, name: 'Administrator', slug: 'admin', is_system: true, title_label: 'admin.role_labels.admin' }
        ]
    });
    assert.equal(content.access_rules[0].grantable, false);
    assert.equal(content.role_catalog[0].name, 'Researchers');
    // Older payloads without the key stay parseable and simply have no translation.
    assert.equal(content.role_catalog[0].title_label, null);
    assert.equal(content.role_catalog[1].title_label, 'admin.role_labels.admin');
    const row = {
        id: '1',
        name: 'Search',
        kind: 'php',
        mcp_server_id: null,
        active: true,
        mapped_capability: null,
        description: null,
        models: []
    };
    assert.equal(AdminToolSchema.parse(row).access_rule, 'unavailable');
    const rowWithoutMcpServerId = { ...row };
    delete rowWithoutMcpServerId.mcp_server_id;
    assert.equal(AdminToolSchema.safeParse(rowWithoutMcpServerId).success, false);
    assert.equal(AdminToolSchema.safeParse({ ...row, mcp_server_id: 1 }).success, true);
    assert.equal(AdminToolSchema.safeParse({ ...row, access_rule: 'everyone' }).success, false);
    assert.equal(
        AdminContentSchema.safeParse({ rows: [], permission_catalog: [{ ...catalog[0], grantable: 'yes' }] }).success,
        false
    );
});

test('manual and mapped assignments keep both sources, and manual edits never serialize derived assignments', () => {
    const user = AdminUserSchema.parse({
        id: '7',
        name: 'Researcher',
        username: 'r',
        email: 'r@example.test',
        employeetype: 'staff',
        admin_disabled: false,
        last_login_at: null,
        local_account: false,
        roles: [3],
        mapped_roles: [3]
    });
    assert.deepEqual(user.roles, [3]);
    assert.deepEqual(user.mapped_roles, [3]);
    const roles = [
        { id: 3, name: 'Research group', slug: 'research', is_system: false, title_label: null },
        // A system role seeded in German still resolves through its translation key.
        { id: 1, name: 'Administrator*in', slug: 'admin', is_system: true, title_label: 'admin.role_labels.admin' }
    ];
    assert.equal(
        roleLabel(3, roles, [], (label) => label),
        'Research group'
    );
    assert.equal(
        roleLabel(1, roles, [], (label) => label),
        'admin.role_labels.admin'
    );
    assert.equal(
        roleLabel(9, roles, [], (label, replacements) => `${label}:${replacements?.id}`),
        'admin.role_unknown:9'
    );
    const fields = [
        AdminFieldSchema.parse({ key: 'roles', type: 'multi', options: [{ value: 3, label: 'Research group' }] })
    ];
    assert.deepEqual(editorSchema('users', fields, user).parse({ roles: [], mapped_roles: [] }), { roles: [] });
    assert.deepEqual(user.mapped_roles, [3]);
});

test('built-in slug immutability and approved tool options remain enforced in the editor schema', () => {
    const slug = AdminFieldSchema.parse({ key: 'slug', type: 'text', immutable: true });
    const role = { id: '1', slug: 'admin' };
    assert.equal(editorSchema('roles', [slug], role).safeParse({ slug: 'changed' }).success, false);
    const access = AdminFieldSchema.parse({
        key: 'access_rule',
        type: 'select',
        options: [
            { value: 'unavailable', label: 'Unavailable' },
            { value: 'web_search', label: 'Web search' }
        ]
    });
    const schema = editorSchema('tools', [access], { id: '1', access_rule: 'unavailable' });
    assert.equal(schema.safeParse({ access_rule: 'web_search' }).success, true);
    assert.equal(schema.safeParse({ access_rule: 'everyone' }).success, false);
});

test('every code-owned permission and approved access rule has English and German labels', () => {
    const permissionSource = readFileSync('app/Services/Admin/Permission.php', 'utf8');
    const codes = [...permissionSource.matchAll(/case \w+ = '([^']+)'/g)].map((match) => match[1]);
    for (const locale of ['en_US', 'de_DE']) {
        const labels = JSON.parse(readFileSync(`resources/language/ui_${locale}.json`, 'utf8'));
        for (const code of codes) {
            const value = `admin.permissions.${code}`.split('.').reduce((entry, key) => entry?.[key], labels);
            assert.equal(typeof value?.title, 'string', `${locale} ${code} title`);
            assert.equal(typeof value?.description, 'string', `${locale} ${code} description`);
        }
        for (const rule of ['unavailable', 'web_search', 'image_generation', 'internal_search']) {
            assert.equal(typeof labels.admin.tool_access_rules[rule].title, 'string');
            assert.equal(typeof labels.admin.tool_access_rules[rule].description, 'string');
        }
    }
});
