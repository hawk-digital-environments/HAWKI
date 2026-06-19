import z from 'zod';

export const userKeychainValueTypes = ['private_key', 'public_key', 'room_key', 'room_ai', 'room_ai_legacy', 'ai_conv'] as const;

const UserKeychainValuesSchema = z.object({
    id: z.string(),
    user_id: z.number(),
    key: z.string(),
    value: z.string(),
    type: z.enum(userKeychainValueTypes)
});

export default UserKeychainValuesSchema;

export type UserKeychainValue = z.infer<typeof UserKeychainValuesSchema>;
export type UserKeychainValueType = UserKeychainValue['type'];

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'user-keychain-values': UserKeychainValue;
    }
}
