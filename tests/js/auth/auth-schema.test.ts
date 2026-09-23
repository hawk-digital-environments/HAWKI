import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import AuthSchema from '../../../resources/js/plugins/auth/schemas/resources/auth.schema.js';

const common = {
    id: 'hawki',
    capabilities: { credentials: true, redirect: true },
    last_error: null
};

test('auth metadata represents local and organization sign-in together', () => {
    assert.equal(
        AuthSchema.safeParse({ ...common, mode: 'mixed', start_url: '/auth/redirect' }).success,
        true
    );
    assert.equal(AuthSchema.safeParse({ ...common, mode: 'mixed', start_url: null }).success, false);
    assert.equal(
        AuthSchema.safeParse({
            ...common,
            mode: 'credentials',
            start_url: null,
            capabilities: { credentials: true, redirect: false }
        }).success,
        true
    );
});
