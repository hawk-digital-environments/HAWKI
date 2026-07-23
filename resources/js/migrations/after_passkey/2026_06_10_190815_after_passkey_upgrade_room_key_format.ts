import type {MigrationContext} from '$lib/data/migrations/migrator.js';
import {createKeychainHandle} from '$lib/data/keychain/keychainHandle.js';
import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';

export async function migrate({name}: MigrationContext) {
    const keychainHandle = createKeychainHandle(() => oldUiBridge.passkey!);
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
