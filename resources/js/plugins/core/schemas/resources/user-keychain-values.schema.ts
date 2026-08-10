import z from 'zod';

/**
 * Validates the `user-keychain-values` API resource — one encrypted entry in a user's keychain
 * (their asymmetric key pair, per-room symmetric keys and their AI-derived variants, and the
 * AI-conversation key), as loaded/written by `kernel/keychain/keychainHandle.ts`.
 *
 * Every value's `value` is symmetrically encrypted with a password derived from the user's
 * passkey (see `deriveKeychainPassword`); `keychainHandle.ts` decrypts entries into `CryptoKey`
 * instances after loading them.
 *
 * Registers the resource under the key `'user-keychain-values'` in `HawkiResourceSchemas` (see
 * the `declare module` augmentation below).
 */
export const userKeychainValueTypes = ['private_key', 'public_key', 'room_key', 'room_ai', 'room_ai_legacy', 'ai_conv'] as const;

const UserKeychainValuesSchema = z.object({
    id: z.string(),
    user_id: z.number(),
    /**
     * Logical name of the entry within its `type`. For `private_key`/`public_key`/`ai_conv` this
     * is a fixed literal (`'privateKey'`/`'publicKey'`/`'aiConvKey'`); for `room_key`/`room_ai`/
     * `room_ai_legacy` this is the room's slug, since a user has one such key per room.
     */
    key: z.string(),
    /** The encrypted key material, as a serialized symmetric-crypto value (see `loadSymmetricCryptoValue`/`decryptSymmetric`). Decrypt with the keychain password before use. */
    value: z.string(),
    /**
     * Which key this entry represents: `private_key`/`public_key` (the user's asymmetric pair),
     * `ai_conv` (key for encrypting/decrypting AI conversations), or `room_key`/`room_ai`/
     * `room_ai_legacy` (a room's symmetric key and its AI-derived variants — `room_ai_legacy`
     * exists only for backward compatibility with a historical key-derivation bug, see
     * `keychainHandle.ts`'s `roomKeyUpdateHelper`).
     */
    type: z.enum(userKeychainValueTypes)
});

export default UserKeychainValuesSchema;

export type UserKeychainValue = z.infer<typeof UserKeychainValuesSchema>;
export type UserKeychainValueType = UserKeychainValue['type'];

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'user-keychain-values': UserKeychainValue;
    }
}
