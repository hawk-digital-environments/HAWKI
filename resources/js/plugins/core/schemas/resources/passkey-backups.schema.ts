import z from 'zod';

const PasskeyBackupsSchema = z.object({
    id: z.literal('me'),
    ciphertext: z.string(),
    iv: z.string(),
    tag: z.string()
});

export default PasskeyBackupsSchema;

export type PasskeyBackup = z.infer<typeof PasskeyBackupsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'passkey-backups': PasskeyBackup;
    }
}
