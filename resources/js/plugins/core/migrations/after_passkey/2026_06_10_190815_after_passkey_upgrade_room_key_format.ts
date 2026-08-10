import {createKeychainHandle} from '$lib/kernel/keychain/keychainHandle.js';
import {MigrationContext} from '$lib/kernel/migrations/MigrationExtension';
import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';

/**
 * `after_passkey` migration: back-fills missing AI/legacy-AI room keys for rooms
 * that predate per-room AI key derivation.
 *
 * Early versions of the keychain stored only the symmetric `room_key` per room;
 * the derived `room_ai` and `room_ai_legacy` keys (used for AI operations, see
 * `KeychainHandle.roomKeyUpdateHelper`) were added later. Rooms created before
 * that change have a `room_key` but no AI keys, so {@link KeychainHandle.brokenRoomKeys}
 * reports them as broken (the room can't use AI features until fixed).
 *
 * This migration runs once after a passkey login: it loads the keychain, finds
 * every broken room, and re-imports each room key — which re-derives and stores
 * the missing AI/legacy-AI keys in a single deferred batch (see
 * `KeychainHandle.doUpdatesDeferred`). No-op when no rooms are broken.
 *
 * Contract (from {@link MigrationContext}): needs `app` (for config salts) and
 * `oldUiBridge.passkey` (the provider for the new `KeychainHandle`). It does
 * not consume `data` — broken rooms are detected purely from the current
 * keychain state.
 */
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
