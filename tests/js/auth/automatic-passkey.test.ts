import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {webcrypto} from 'node:crypto';
import {deriveKey, generatePasskey} from '../../../resources/js/kernel/encryption/utils.js';
import {encryptSymmetric, decryptSymmetric} from '../../../resources/js/kernel/encryption/symmetric.js';

test('automatically generated passkeys can be recovered from the encrypted backup and unlock data', async () => {
    Object.assign(globalThis, {window: {crypto: webcrypto}});
    const passkey = generatePasskey();
    assert.match(passkey, /^[a-f0-9]{64}$/);
    assert.notEqual(generatePasskey(), passkey);

    const keychainKey = await deriveKey(passkey, 'keychain_encryptor', 'userdata-salt');
    const encryptedData = await encryptSymmetric('private user data', keychainKey);
    const backupKey = await deriveKey('abcd-1234-5678-90ef', 'alice_backup', 'backup-salt');
    const encryptedBackup = await encryptSymmetric(passkey, backupKey);
    const recovered = await decryptSymmetric(encryptedBackup, backupKey);
    assert.equal(recovered, passkey);
    const recoveredKey = await deriveKey(recovered, 'keychain_encryptor', 'userdata-salt');
    assert.equal(await decryptSymmetric(encryptedData, recoveredKey), 'private user data');
});
