import assert from 'node:assert/strict';
import {test} from 'node:test';
import {flushSync} from 'svelte';
import {FormApi, FieldApi} from '@tanstack/form-core';
// Exercise the installed adapter, including our dependency patch. Its barrel
// uses extensionless imports that Node cannot resolve without Vite.
import {useSelector} from '../../../node_modules/@tanstack/svelte-store/dist/useSelector.svelte.js';

test('form and field selectors preserve object identity without proxy warnings on edits', (t) => {
    const warn = t.mock.method(console, 'warn', () => {});
    const form = new FormApi({defaultValues: {label: 'Initial', roles: ['reader']}});
    const unmountForm = form.mount();
    const label = new FieldApi({form, name: 'label'});
    const roles = new FieldApi({form, name: 'roles'});
    const unmountLabel = label.mount();
    const unmountRoles = roles.mount();
    const observed: string[] = [];
    let selectedForm;
    let selectedRoles;
    let selectedErrors;
    const stop = $effect.root(() => {
        selectedForm = useSelector(form.store, state => state);
        selectedRoles = useSelector(roles.store, state => state.value);
        selectedErrors = useSelector(label.store, state => state.meta.errorMap);
        // Field.svelte also observes this unchanged object on every field update.
        useSelector(label.store, state => state.meta.errorSourceMap);
        const labelValue = $derived(selectedForm.current.values.label);
        $effect(() => {observed.push(labelValue);});
    });
    try {
        flushSync();
        label.handleChange('Changed');
        flushSync();
        roles.handleChange(['reader', 'editor']);
        flushSync();
        label.setErrorMap({onChange: 'Invalid label'});
        flushSync();
        assert.deepEqual(observed, ['Initial', 'Changed']);
        assert.deepEqual(selectedRoles.current, ['reader', 'editor']);
        assert.equal(selectedErrors.current.onChange, 'Invalid label');
        assert.deepEqual(warn.mock.calls.flatMap(call => call.arguments).filter(argument =>
            String(argument).includes('state_proxy_equality_mismatch')
        ), [], 'Store notifications must not compare proxies with their original objects');
        assert.equal(selectedForm.current, form.store.state);
        assert.equal(selectedRoles.current, roles.state.value);
        assert.equal(selectedErrors.current, label.state.meta.errorMap);
    } finally {
        stop();
        unmountRoles();
        unmountLabel();
        unmountForm();
    }
});
