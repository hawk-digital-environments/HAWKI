<script module lang="ts">
    import { configurePage } from '$lib/components/ui/routing/index.js';
    import { sanitizeNext } from '$lib/kernel/auth/navigation.js';
    export const config = configurePage({
        cacheKey: false,
        loadData: async ({ app, redirect }) => {
            const next = sanitizeNext(app.router, new URLSearchParams(window.location.search).get('next'));
            if (app.cryptoReady) redirect(next ?? app.router.getPath('chat.index'));
            const connection = app.connection;
            if (connection.isAuthenticated && connection.keychain_state === 'setup_required')
                redirect('auth.register', next ? { next } : undefined);
            if (connection.isAuthenticated && connection.keychain_state === 'inconsistent')
                redirect('auth.inconsistent', next ? { next } : undefined);
            return {};
        }
    });
</script>
<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import AuthField from '../components/AuthField.svelte';
    import AuthError from '../components/AuthError.svelte';
    import { deriveKey } from '$lib/kernel/encryption/utils.js';
    import { decryptSymmetric, loadSymmetricCryptoValueFromObject } from '$lib/kernel/encryption/symmetric.js';
    import { ApiTransportError } from '$lib/kernel/api/errors.js';
    import { tick } from 'svelte';
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    import AuthFrame from './AuthFrame.svelte';
    import ResetProfileButton from './ResetProfileButton.svelte';
    import { authErrorKey, goAfterUnlock, nextDestination } from './authHelpers.js';
    const {}: RouteProps<typeof config> = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const keychain = app.stores.get('keychain');
    const autoGenerate = app.config.get().security.passkeyAutoGenerate;
    let passkey = $state('');
    let backupCode = $state('');
    let mode = $state<'passkey' | 'backup'>(autoGenerate ? 'backup' : 'passkey');
    let passkeyInput = $state<HTMLInputElement | null>(null);
    let backupInput = $state<HTMLInputElement | null>(null);
    async function switchMode(next: typeof mode) {
        if (pending) return;
        mode = next;
        error = '';
        // The form is swapped out under the user; land focus on the new field so the change is announced.
        await tick();
        (next === 'passkey' ? passkeyInput : backupInput)?.focus();
    }
    let pending = $state(false);
    let errorElement = $state<HTMLParagraphElement | null>(null);
    let error = $state('');
    $effect(() => { if (error) errorElement?.focus(); });
    const normalizedBackupCode = $derived(backupCode.replace(/\s+/g, '').toLowerCase());
    async function unlock(value: string) {
        if (pending) return;
        pending = true;
        error = '';
        try {
            if (!(await keychain.unlock(value))) {
                error = __('ui.auth.handshake.wrongPasskey');
                return;
            }
            await keychain.persistPasskey(value);
            goAfterUnlock(app);
        } catch (e) {
            error = __(authErrorKey(e));
        } finally {
            pending = false;
        }
    }
    async function recover() {
        if (pending) return;
        pending = true;
        error = '';
        try {
            const backup = await app.restApi.getResource('passkey-backups', 'me');
            const salts = app.config.get().salts;
            const connection = app.connection;
            if (!salts || !connection.hasUserInfo) throw new Error('Recovery configuration is unavailable.');
            const key = await deriveKey(normalizedBackupCode, `${connection.userinfo.username}_backup`, salts.backup);
            const recovered = await decryptSymmetric(loadSymmetricCryptoValueFromObject(backup), key);
            if (!(await keychain.unlock(recovered))) {
                error = __('ui.auth.handshake.wrongPasskey');
                return;
            }
            await keychain.persistPasskey(recovered);
            goAfterUnlock(app, nextDestination(app));
        } catch (e) {
            error =
                e instanceof ApiTransportError && e.status === 404 ?
                    __('ui.auth.handshake.backupMissing')
                :   __(authErrorKey(e));
        } finally {
            pending = false;
        }
    }
</script>
<AuthFrame>
    <div class="auth-intro">
        <h1 id="auth-title">{__('ui.auth.handshake.title')}</h1>
        <p class="auth-copy">{mode === 'passkey' ? (autoGenerate ? __('ui.auth.handshake.ownPasskeyDescription') : __('ui.auth.handshake.description')) : autoGenerate ? __('ui.auth.handshake.backupUnlockDescription') : __('ui.auth.handshake.recoveryDescription')}</p>
    </div>
    {#if error}<AuthError message={error} bind:ref={errorElement}/>{/if}
    {#if mode === 'passkey'}
        <form class="auth-form" onsubmit={(e) => { e.preventDefault(); void unlock(passkey); }}>
            <AuthField id="passkey" label={__('ui.auth.handshake.passkey')} type="password" bind:value={passkey} bind:ref={passkeyInput} autocomplete="current-password" required pending={pending}/>
            <Button type="submit" variant="fill" busy={pending} block>{pending ? __('ui.auth.handshake.unlocking') : __('ui.auth.handshake.unlock')}</Button>
        </form>
        <div class="switch">
            <Button type="button" variant="ghost" size="sm" busy={pending} onclick={() => void switchMode('backup')}>{__('ui.auth.handshake.recoveryTitle')}</Button>
        </div>
    {:else}
        <form class="auth-form" onsubmit={(e) => { e.preventDefault(); void recover(); }}>
            <AuthField id="backup-code" label={__('ui.auth.handshake.backupCode')} class="code" bind:value={backupCode} bind:ref={backupInput} pattern={'[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}'} placeholder="xxxx-xxxx-xxxx-xxxx" autocomplete="off" autocapitalize="off" spellcheck={false} required pending={pending}/>
            <Button type="submit" variant="fill" busy={pending} block>{pending ? __('ui.auth.handshake.unlocking') : autoGenerate ? __('ui.auth.handshake.unlock') : __('ui.auth.handshake.recover')}</Button>
        </form>
        <div class="reset-recovery">
            <ResetProfileButton label={__('ui.auth.handshake.lostBackup')} disabled={pending} variant="ghost"/>
        </div>
        <div class="switch">
            <Button type="button" variant="ghost" size="sm" busy={pending} onclick={() => void switchMode('passkey')}>{autoGenerate ? __('ui.auth.handshake.useOwnPasskey') : __('ui.auth.handshake.usePasskey')}</Button>
        </div>
    {/if}
</AuthFrame>
<style>
    .reset-recovery {
        display: grid;
        justify-items: center;
        gap: var(--space-2);
    }
    .switch {
        display: flex;
        justify-content: center;
    }
    .switch :global(.btn) {
        color: var(--color-text-muted);
    }
    .switch :global(.btn:not(:disabled):hover) {
        color: var(--color-text);
    }
    /* The field markup lives in AuthField, so the monospace styling for the backup code
       reaches it through a global selector scoped by the field's own classes. */
    :global(.auth-field .code) {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        letter-spacing: 0.06em;
    }
</style>
