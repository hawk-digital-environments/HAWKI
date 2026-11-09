import assert from 'node:assert/strict';
import {test} from 'node:test';
import {AdminRegistry, providesAdminSections} from '../../../resources/js/plugins/admin/registry.js';

/**
 * The Admin Panel's section registry: modules declare sections through the
 * duck-typed `adminSections()` method, the panel pulls them once every module
 * exists. Pure data in/out — no Svelte, no app — so it runs in plain Node.
 */

/** A minimal module stub carrying plugin metadata like the real registrar does. */
function module(plugin, name, adminSections) {
    return {name, plugin: {name: plugin, isCorePlugin: true}, ...(adminSections ? {adminSections} : {})};
}

function section(id, visible = () => true) {
    return {
        id,
        labelKey: `x.${id}`,
        route: `admin.${id}.index`,
        visible,
        routes: () => {}
    };
}

test('registry: collects sections from every provider module', () => {
    const registry = new AdminRegistry();
    registry.collect([
        module('core', 'chat'),
        module('assistants', 'assistant-admin', ({section: add}) => add(section('assistants')))
    ]);

    assert.equal(registry.sections.length, 1);
    assert.equal(registry.sections[0].id, 'assistants');
    assert.equal(registry.sections[0].module, 'assistants:assistant-admin');
});

test('registry: the admin plugin\'s own modules declare first', () => {
    const registry = new AdminRegistry();
    registry.collect([
        module('assistants', 'assistant-admin', ({section: add}) => add(section('assistants'))),
        module('admin', 'panel', ({section: add}) => add(section('settings')))
    ]);

    assert.deepEqual(registry.sections.map(s => s.id), ['settings', 'assistants']);
});

test('registry: duplicate section ids throw and name both modules', () => {
    const registry = new AdminRegistry();

    assert.throws(
        () => registry.collect([
            module('assistants', 'one', ({section: add}) => add(section('assistants'))),
            module('other', 'two', ({section: add}) => add(section('assistants')))
        ]),
        /already registered it/
    );
});

test('registry: collecting twice throws', () => {
    const registry = new AdminRegistry();
    registry.collect([]);

    assert.throws(() => registry.collect([]), /already been collected/);
});

test('registry: registering outside collection throws', () => {
    const registry = new AdminRegistry();
    let registrar;
    registry.collect([module('assistants', 'm', (r) => {
        registrar = r;
    })]);

    assert.throws(() => registrar.section(section('late')), /outside collection/);
});

test('registry: visibleSections applies each section\'s own gate', () => {
    const registry = new AdminRegistry();
    const flags = {a: true, b: false};
    registry.collect([
        module('assistants', 'm', ({section: add}) => {
            add(section('a', () => flags.a));
            add(section('b', () => flags.b));
        })
    ]);

    assert.deepEqual(registry.visibleSections.map(s => s.id), ['a']);

    // Gates are re-evaluated per read, so a verdict flip changes visibility.
    flags.a = false;
    assert.deepEqual(registry.visibleSections.map(s => s.id), []);
});

test('registry: the duck-type guard matches only providers', () => {
    assert.equal(providesAdminSections(module('x', 'y', () => {})), true);
    assert.equal(providesAdminSections(module('x', 'y')), false);
    assert.equal(providesAdminSections(null), false);
    assert.equal(providesAdminSections({adminSections: 'not a function'}), false);
});
