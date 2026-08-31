import {createKeychainHandle, type KeychainHandle, type RoomKeys} from '$lib/kernel/keychain/keychainHandle.js';
import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {decryptSymmetric, loadSymmetricCryptoValueFromObject} from '$lib/kernel/encryption/symmetric.js';
import {deriveKey} from '$lib/kernel/encryption/utils.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'keychain': KeychainStore;
    }
}

/**
 * Reactive store for the user's end-to-end encryption keychain.
 *
 * Holds the user's asymmetric keypair (`publicKey` / `privateKey`), the AI
 * conversation key (`aiConvKey`), and a map of per-room symmetric keys
 * (`roomKeys`). All values start as `null` / empty and are populated
 * asynchronously once a passkey becomes available in the frontend session.
 *
 * On the SPA shell the store restores the session passkey from the same local
 * encrypted value used by the legacy UI. It then loads the keychain once an
 * authenticated connection is present. The `waitingToLoad` promise resolves
 * when that initial attempt completes, allowing routed features to decide
 * whether to continue or send the user to the handshake screen.
 *
 * Access via `useStore('keychain')` rather than constructing this class directly.
 */
export class KeychainStore implements DataStore {
    public readonly name = 'keychain';

    private _handle: KeychainHandle | null = null;
    private _app: HawkiApp | null = null;

    /** Resolves when the initial keychain load has completed (or was skipped
     *  because the connection is unauthenticated). Await this before reading keys. */
    private _waitingToLoad: Promise<void> | null = null;
    /** The user's public key. `null` until the keychain has loaded. */
    public publicKey: CryptoKey | null = $state(null);
    /** The user's private key. `null` until the keychain has loaded. */
    public privateKey: CryptoKey | null = $state(null);
    /** The shared AI conversation key. `null` until the keychain has loaded. */
    public aiConvKey: CryptoKey | null = $state(null);
    /** Per-room symmetric keys keyed by room slug. Empty until the keychain has loaded. */
    public roomKeys = $state({} as Record<string, RoomKeys>);

    public get waitingToLoad() {
        if (!this._waitingToLoad) {
            throw new Error('KeychainStore.waitToLoad was accessed before the store was initialized.');
        }
        return this._waitingToLoad;
    }

    private get handle() {
        if (!this._handle) {
            throw new Error('KeychainStore.handle was accessed before the store was initialized.');
        }
        return this._handle;
    }

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

    /**
     * Removes the locally stored passkey session (encrypted localStorage blob +
     * in-memory value). Server-side data is untouched.
     */
    public clearLocalSession(): void {
        try {
            const connection = this._app?.connection;
            if (connection?.isAuthenticated) {
                this._app?.localStorage.removeItem(`${connection.userinfo.username}PK`);
            }
        } catch (error) {
            // Connection not loaded yet — nothing to clean up.
        }
        this._app?.passkeySession.clear();
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

    public async loadData(app: HawkiApp) {
        this._app = app;
        this._handle = createKeychainHandle(app, () => {
            const currentPasskey = app.passkeySession.passkey;
            if (!currentPasskey) {
                throw new Error('No passkey available to create keychain handle!');
            }
            return currentPasskey;
        });

        const handle = this.handle;

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

        this._waitingToLoad = (async () => {
            try {
                const connection = app.connection;
                if (!connection.isAuthenticated) {
                    throw new Error('Current connection is not authenticated');
                }

                if (!app.passkeySession.passkey) {
                    const storedPasskey = app.localStorage.getItem(`${connection.userinfo.username}PK`);
                    const passkeySalt = app.config.get().salts?.passkey;
                    if (!storedPasskey || !passkeySalt) {
                        return;
                    }

                    const wrappingKey = await deriveKey(
                        connection.userinfo.email,
                        connection.userinfo.username,
                        passkeySalt
                    );
                    const encryptedPasskey = loadSymmetricCryptoValueFromObject(JSON.parse(storedPasskey));
                    app.passkeySession.passkey = await decryptSymmetric(encryptedPasskey, wrappingKey);
                }

                await handle.load();
            } catch (error) {
                app.passkeySession.clear();
                console.warn('Could not restore the local HAWKI keychain session.', error);
            }
        })();

        await this._waitingToLoad;
    }
}
