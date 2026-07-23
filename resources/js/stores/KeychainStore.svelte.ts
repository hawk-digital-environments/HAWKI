import {getConnection} from '$lib/data/connection/connection.js';
import {createKeychainHandle, type KeychainHandle, type RoomKeys} from '$lib/data/keychain/keychainHandle.js';
import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';


/**
 * Reactive store for the user's end-to-end encryption keychain.
 *
 * Holds the user's asymmetric keypair (`publicKey` / `privateKey`), the AI
 * conversation key (`aiConvKey`), and a map of per-room symmetric keys
 * (`roomKeys`). All values start as `null` / empty and are populated
 * asynchronously once a passkey becomes available on the legacy bridge.
 *
 * Loading is deferred: the store subscribes to the legacy bridge's passkey
 * via a reactive `$effect` inside the constructor and only calls
 * `handle.load()` once both the passkey and an authenticated connection are
 * present. The `waitingToLoad` promise resolves when that initial load
 * completes, allowing callers to await readiness before performing
 * cryptographic operations.
 *
 * Use `keychainStore` (the exported singleton) rather than constructing
 * this class directly.
 */
export class KeychainStore {
    constructor(
        private handle: KeychainHandle,
        passkeyProvider: () => string | null,
        connectionProvider: () => ReturnType<typeof getConnection>
    ) {

        handle.onChange(() => {
            // Before our migration upgrades the old keychain values,
            // this whole callback would fail as soon as the passkey becomes available.
            // If the keychain is completely empty, we fail silently (we assume the migration needs to run)
            // Otherwise, we let the error bubble up, as it likely means something went wrong with loading the existing keychain values.
            if (handle.listKeys().length === 0) {
                return;
            }
            this.publicKey = handle.publicKey();
            this.privateKey = handle.privateKey();
            this.roomKeys = handle.roomKeys();
            this.aiConvKey = handle.aiConvKey();
        });

        let resolveLoad: () => void;
        this.waitingToLoad = new Promise(res => resolveLoad = res);

        // Since we must wait for the passkey to be available on the old bridge before we can create the handle,
        // we also have to wait to load the keychain values until the handle is ready.
        // The $effect.root is used to allow this constructor to listen on reactive changes to the old bridge's passkey without causing issues with the store's own reactivity.
        $effect.root(() => {
            $effect(() => {
                if (!passkeyProvider()) {
                    return;
                }

                if (connectionProvider().type !== 'internal_authenticated') {
                    return;
                }

                handle.load().then(resolveLoad);
            });
        });
    }

    /** Resolves when the initial keychain load has completed (or was skipped
     *  because the connection is unauthenticated). Await this before reading keys. */
    public readonly waitingToLoad: Promise<void>;
    /** The user's public key. `null` until the keychain has loaded. */
    public publicKey: CryptoKey | null = $state(null);
    /** The user's private key. `null` until the keychain has loaded. */
    public privateKey: CryptoKey | null = $state(null);
    /** The shared AI conversation key. `null` until the keychain has loaded. */
    public aiConvKey: CryptoKey | null = $state(null);
    /** Per-room symmetric keys keyed by room slug. Empty until the keychain has loaded. */
    public roomKeys = $state({} as Record<string, RoomKeys>);

    /** Returns `true` when `passkey` successfully decrypts the stored keychain. */
    public async validateKeychainPassword(passkey: string) {
        return await this.handle.validateKeychainPassword(passkey);
    }

    /**
     * Initializes a new keychain for the user with the provided passkey. This is only necessary if the user is starting with a fresh account and doesn't have an existing keychain to migrate.
     * After the keychain is initialized, it also loads the (empty) keychain values into the store.
     */
    public async initializeNewKeychain() {
        await this.handle.initializeNewKeychain();
    }

    /** Generates a fresh symmetric key pair for `slug` and persists it in the keychain. */
    public async createNewRoomKey(slug: string) {
        return await this.handle.createRoomKeys(slug);
    }

    /** Imports an externally-received `key` for `slug` into the keychain (e.g.
     *  when a user is invited to an existing room and receives its key). */
    public async importRoomKey(slug: string, key: CryptoKey) {
        return await this.handle.importRoomKey(slug, key);
    }
}

const handle = createKeychainHandle(() => {
    const currentPasskey = oldUiBridge.passkey;
    if (!currentPasskey) {
        throw new Error('No passkey available to create keychain handle!');
    }
    return currentPasskey;
});

export const keychainStore = new KeychainStore(
    handle,
    () => oldUiBridge.passkey,
    () => getConnection()
);
