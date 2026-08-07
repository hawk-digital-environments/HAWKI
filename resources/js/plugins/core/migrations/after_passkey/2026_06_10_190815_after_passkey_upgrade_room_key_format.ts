import {createKeychainHandle} from '$lib/kernel/keychain/keychainHandle.js';
import {MigrationContext} from '$lib/kernel/migrations/MigrationAspect';
import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';

export async function migrate({name, app}: MigrationContext) {
    const keychainHandle = createKeychainHandle(app, () => oldUiBridge.passkey!);
    await keychainHandle.load();

    const brokenRoomKeys = keychainHandle.brokenRoomKeys();
    if (Object.keys(brokenRoomKeys).length === 0) {
        console.log('There are no room keys that need to be migrated, skipping!');
        return;
    }

    await keychainHandle.doUpdatesDeferred(async () => {
        for (const [roomId, key] of Object.entries(brokenRoomKeys)) {
            console.log(`Generating missing AI keys for room ${roomId}...`);
            await keychainHandle.importRoomKey(roomId, key);
        }
    });
}
