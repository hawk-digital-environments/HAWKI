import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {readFileSync} from 'node:fs';
import {createContext, runInContext} from 'node:vm';

const source = readFileSync(new URL('../../../public/js/handshake_functions.js', import.meta.url), 'utf8');

function fixture(failOnce = false) {
    const elements = new Map<string, {textContent: string; innerText: string; hidden: boolean; focus: () => void}>();
    let focused = '';
    for (const selector of ['#automatic-passkey-status', '#automatic-passkey-error', '#automatic-passkey-retry', '#backup-hash']) {
        elements.set(selector, {textContent: '', innerText: '', hidden: true, focus: () => { focused = selector; }});
    }
    const calls: string[] = [];
    const secrets: string[] = [];
    const slides: number[] = [];
    const context = createContext({
        crypto,
        Uint8Array,
        console: {error: () => {}},
        __: (key: string) => key,
        window: {
            getConfig: () => ({security: {passkeyAutoGenerate: true}, salts: {backup: 'test-salt'}}),
            getConnectionWithUserInfo: () => ({userinfo: {username: 'test-user'}})
        },
        document: {
            querySelector: (selector: string) => selector.startsWith('meta')
                ? {getAttribute: () => 'test-csrf'}
                : elements.get(selector)
        },
        generatePasskeyBackupHash: () => 'abcd-1234-5678-90ef',
        deriveKey: async () => 'backup-key',
        encryptWithSymKey: async (_key: string, passkey: string) => {
            secrets.push(passkey);
            return {ciphertext: 'encrypted-passkey', iv: 'iv', tag: 'tag'};
        },
        fetch: async (url: string) => {
            calls.push(url);
            if (failOnce) {
                failOnce = false;
                throw new Error('Network unavailable');
            }
            return {ok: true, json: async () => ({success: true})};
        },
        setPassKey: async () => { calls.push('persist'); },
        recordSlide: (slide: number) => slides.push(slide)
    });
    runInContext(source + '\nswitchSlide = recordSlide;', context);
    return {context, calls, secrets, slides, elements, focused: () => focused};
}

test('legacy automatic registration skips input validation and prepares only one backup for concurrent clicks', async () => {
    const {context, calls, secrets, slides} = fixture();
    await runInContext('Promise.all([generateRegistrationPasskey(), generateRegistrationPasskey()])', context);
    assert.deepEqual(calls, ['/req/profile/backupPassKey', 'persist']);
    assert.equal(secrets.length, 1);
    assert.match(secrets[0], /^[a-f0-9]{64}$/);
    assert.deepEqual(slides, [6]);
});

test('legacy automatic registration retains its passkey and offers a focused retry after backup failure', async () => {
    const {context, calls, secrets, slides, elements, focused} = fixture(true);
    await runInContext('generateRegistrationPasskey()', context);
    assert.deepEqual(slides, []);
    assert.equal(elements.get('#automatic-passkey-retry')!.hidden, false);
    assert.equal(focused(), '#automatic-passkey-retry');
    assert.equal(elements.get('#automatic-passkey-error')!.textContent, 'ui.auth.errors.generic');

    await runInContext('generateRegistrationPasskey()', context);
    assert.equal(secrets[0], secrets[1]);
    assert.deepEqual(calls, ['/req/profile/backupPassKey', '/req/profile/backupPassKey', 'persist']);
    assert.deepEqual(slides, [6]);
    assert.equal(elements.get('#automatic-passkey-error')!.textContent, '');
});
