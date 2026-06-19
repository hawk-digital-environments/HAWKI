import {getConnection} from '$lib/data/connection/connection.js';
import {createKeychainHandle, type KeychainHandle, type RoomKeys} from '$lib/data/keychain/keychainHandle.js';
import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';


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
                console.log('Keychain handle changed, but there are no keys in the keychain, skipping reload (this is expected if the migration hasn\'t run yet)...');
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

    public readonly waitingToLoad: Promise<void>;
    public publicKey: CryptoKey | null = $state(null);
    public privateKey: CryptoKey | null = $state(null);
    public aiConvKey: CryptoKey | null = $state(null);
    public roomKeys = $state({} as Record<string, RoomKeys>);

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

    public async createNewRoomKey(slug: string) {
        return await this.handle.createRoomKeys(slug);
    }

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
