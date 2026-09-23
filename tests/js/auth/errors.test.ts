import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {authErrorCodeKey, loginReason, registrationErrorPage} from '../../../resources/js/plugins/auth/pages/authHelpers.js';
import {LogoutResponseSchema} from '../../../resources/js/kernel/auth/schemas.js';

test('expected auth failures select translated messages without displaying server prose', () => {
    for (const code of ['invalid_credentials', 'provider_failed', 'session_expired', 'auth_redirect_required', 'registration_policy_unavailable']) {
        assert.equal(authErrorCodeKey(code), `ui.auth.errors.${code}`);
    }
    assert.equal(authErrorCodeKey('untrusted prose'), 'ui.auth.errors.generic');
    assert.equal(loginReason('session_expired'), 'session_expired');
    for (const reason of ['provider_failed', 'arbitrary text', null, ['session_expired']]) assert.equal(loginReason(reason), undefined);
});

test('registration state conflicts select the appropriate auth page', () => {
    assert.equal(registrationErrorPage('registration_keychain_inconsistent'), 'inconsistent');
    assert.equal(registrationErrorPage('registration_not_in_progress'), 'login');
    assert.equal(registrationErrorPage('registration_already_completed'), 'login');
    assert.equal(registrationErrorPage('registration_policy_changed'), undefined);
});

test('logout uses a JSON:API meta document', () => {
    assert.deepEqual(LogoutResponseSchema.parse({meta: {redirect_url: null}}), {meta: {redirect_url: null}});
    assert.equal(LogoutResponseSchema.safeParse({redirect_url: null}).success, false);
});
