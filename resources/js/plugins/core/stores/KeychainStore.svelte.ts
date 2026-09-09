import {createKeychainHandle, type KeychainHandle, type RoomKeys} from '$lib/kernel/keychain/keychainHandle.js';
import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {decryptSymmetric, encryptSymmetric, loadSymmetricCryptoValueFromObject} from '$lib/kernel/encryption/symmetric.js';
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
    private generation = 0;
    private sessionUsername: string | null = null;
    private unlocking: Promise<boolean> | null = null;
    public cryptoReady = $state(false);

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

    /** Locks the keychain and cancels pending unlocks while retaining the encrypted browser passkey. */
    public lock(): void {
        this.generation++;
        this.unlocking = null;
        this.cryptoReady = false;
        this._handle?.clear();
        this.publicKey = null;
        this.privateKey = null;
        this.aiConvKey = null;
        this.roomKeys = {};
        this.sessionUsername = null;
        this._app?.passkeySession.clear();
    }

    /** Forgets this account's saved passkey and locks its keychain, for explicit removal or a profile reset. */
    public clearLocalSession(): void {
        const connection = this._app?.connectionOrNull;
        const username = this.sessionUsername ?? (connection?.hasUserInfo ? connection.userinfo.username : null);
        if (username) this._app?.localStorage.removeItem(`${username}PK`);
        this.lock();
    }

    /** Saves an encrypted passkey for future logins by this account in the same browser. */
    public async persistPasskey(passkey: string): Promise<void> {
        const app = this._app!;
        const generation = this.generation;
        const connection = app.connection;
        if (!connection.hasUserInfo) throw new Error('No user available for passkey persistence.');
        const salt = app.config.get().salts?.passkey;
        if (!salt) throw new Error('Passkey salt is missing.');
        const key = await deriveKey(connection.userinfo.email, connection.userinfo.username, salt);
        const value = (await encryptSymmetric(passkey, key)).toJson();
        if (generation !== this.generation) throw new Error('Keychain session was cleared.');
        this.sessionUsername = connection.userinfo.username;
        const name = `${connection.userinfo.username}PK`;
        app.localStorage.setItem(name, value);
        if (app.localStorage.getItem(name) !== value) throw new Error('Passkey could not be saved in browser storage.');
    }

    /** Every unlock, including local restoration, uses this migration barrier. */
    public async unlock(passkey: string): Promise<boolean> {
        if (this.unlocking) return this.unlocking;
        const unlocking = this.doUnlock(passkey).finally(() => {
            if (this.unlocking === unlocking) this.unlocking = null;
        });
        this.unlocking = unlocking;
        return this.unlocking;
    }

    private async doUnlock(passkey: string): Promise<boolean> {
        const app = this._app!;
        const connection = app.connection;
        if (!connection.isAuthenticated || !['initialized', 'legacy_migration_required'].includes(connection.keychain_state)) {
            throw new Error('This keychain requires account setup or manual recovery.');
        }
        const generation = this.generation;
        this.sessionUsername = connection.userinfo.username;
        this.cryptoReady = false;
        try {
            const valid = await this.validateKeychainPassword(passkey);
            if (generation !== this.generation) throw new Error('Keychain session was cleared.');
            if (!valid) {
                this.clearLocalSession();
                return false;
            }
            app.passkeySession.passkey = passkey;
            await app.migration.apply('after_passkey');
            if (generation !== this.generation) throw new Error('Keychain session was cleared.');
            await this.handle.load();
            if (generation !== this.generation) throw new Error('Keychain session was cleared.');
            if (!this.publicKey || !this.privateKey || !this.aiConvKey) throw new Error('The keychain is incomplete.');
            this.cryptoReady = true;
            return true;
        } catch (error) {
            if (generation === this.generation) this.lock();
            throw error;
        }
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

    /**
     * Creates the keychain handle as soon as the app is assembled — before any
     * bootstrap stage. The legacy handshake page blocks the `migration` stage
     * until a passkey was entered and validates that passkey through this
     * store, so the handle must exist without waiting for {@link loadData}.
     */
    public ready(app: HawkiApp) {
        this._app = app;
        const handle = this._handle = createKeychainHandle(app, () => {
            const currentPasskey = app.passkeySession.passkey;
            if (!currentPasskey) {
                throw new Error('No passkey available to create keychain handle!');
            }
            return currentPasskey;
        });

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

        app.events.async.on('logout', () => {
            this.lock();
        });
    }

    public async loadData(app: HawkiApp) {
        const generation = this.generation;
        this._waitingToLoad = (async () => {
            try {
                const connection = app.connection;
                if (!connection.isAuthenticated || !['initialized', 'legacy_migration_required'].includes(connection.keychain_state)) return;

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
                    const restored = await decryptSymmetric(encryptedPasskey, wrappingKey);
                    if (generation !== this.generation) return;
                    app.passkeySession.passkey = restored;
                }

                await this.unlock(app.passkeySession.passkey!);
            } catch (error) {
                if (generation === this.generation) this.lock();
                console.warn('Could not restore the local HAWKI keychain session.', error);
            }
        })();

        await this._waitingToLoad;
    }
}
