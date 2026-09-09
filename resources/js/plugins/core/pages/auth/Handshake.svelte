<script module lang="ts">
    import { configurePage } from '$lib/components/ui/routing/index.js';
    import { sanitizeNext } from '$lib/kernel/auth/navigation.js';
    export const config = configurePage({
        cacheKey: false,
        loadData: async ({ app, redirect }) => {
            const next = sanitizeNext(new URLSearchParams(window.location.search).get('next'));
            if (app.cryptoReady) redirect(next ?? '/new/chat');
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
    import Input from '$lib/components/ui/input/Input.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { deriveKey } from '$lib/kernel/encryption/utils.js';
    import { decryptSymmetric, loadSymmetricCryptoValueFromObject } from '$lib/kernel/encryption/symmetric.js';
    import { ApiTransportError } from '$lib/kernel/api/errors.js';
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    import AuthFrame from './AuthFrame.svelte';
    import ResetProfileButton from './ResetProfileButton.svelte';
    import { authErrorKey, goAfterUnlock, nextDestination } from './authHelpers.js';
    const {}: RouteProps<typeof config> = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const keychain = app.stores.get('keychain');
    let passkey = $state('');
    let backupCode = $state('');
    let mode = $state<'passkey' | 'backup'>('passkey');
    let passkeyInput = $state<HTMLInputElement | null>(null);
    let backupInput = $state<HTMLInputElement | null>(null);
    function switchMode(next: typeof mode) {
        mode = next;
        error = '';
        // The form is swapped out under the user; land focus on the new field so the change is announced.
        setTimeout(() => (next === 'passkey' ? passkeyInput : backupInput)?.focus(), 0);
    }
    let pending = $state(false);
    let error = $state('');
    function updateBackupCode(event: Event & {currentTarget: HTMLInputElement}) {
        backupCode = event.currentTarget.value.replace(/\s+/g, '').toLowerCase();
        event.currentTarget.value = backupCode;
    }
    async function unlock(value: string) {
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
        pending = true;
        error = '';
        try {
            const backup = await app.restApi.getResource('passkey-backups', 'me');
            const salts = app.config.get().salts;
            const connection = app.connection;
            if (!salts || !connection.hasUserInfo) throw new Error('Recovery configuration is unavailable.');
            const key = await deriveKey(backupCode.trim().toLowerCase(), `${connection.userinfo.username}_backup`, salts.backup);
            const recovered = await decryptSymmetric(loadSymmetricCryptoValueFromObject(backup), key);
            if (!(await keychain.unlock(recovered))) {
                error = __('ui.auth.handshake.wrongPasskey');
                return;
            }
            await keychain.persistPasskey(recovered);
            goAfterUnlock(app, nextDestination());
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
        <p class="auth-copy">{mode === 'passkey' ? __('ui.auth.handshake.description') : __('ui.auth.handshake.recoveryDescription')}</p>
    </div>
    {#if error}<p class="auth-error" role="alert">{error}</p>{/if}
    {#if mode === 'passkey'}
        <form class="auth-form" onsubmit={(e) => { e.preventDefault(); void unlock(passkey); }}>
            <div class="auth-field">
                <label for="passkey">{__('ui.auth.handshake.passkey')}</label>
                <Input id="passkey" type="password" bind:value={passkey} bind:ref={passkeyInput} autocomplete="current-password" required disabled={pending}/>
            </div>
            <Button type="submit" variant="accent" disabled={pending} block>{pending ? __('ui.auth.handshake.unlocking') : __('ui.auth.handshake.unlock')}</Button>
        </form>
        <div class="switch">
            <Button type="button" variant="ghost" size="sm" disabled={pending} onclick={() => switchMode('backup')}>{__('ui.auth.handshake.recoveryTitle')}</Button>
        </div>
    {:else}
        <form class="auth-form" onsubmit={(e) => { e.preventDefault(); void recover(); }}>
            <div class="auth-field">
                <label for="backup-code">{__('ui.auth.handshake.backupCode')}</label>
                <Input id="backup-code" class="code" bind:value={backupCode} bind:ref={backupInput} oninput={updateBackupCode} pattern={'[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}'} placeholder="xxxx-xxxx-xxxx-xxxx" autocomplete="off" autocapitalize="off" spellcheck={false} required disabled={pending}/>
            </div>
            <Button type="submit" variant="accent" disabled={pending} block>{pending ? __('ui.auth.handshake.unlocking') : __('ui.auth.handshake.recover')}</Button>
        </form>
        <div class="reset-recovery">
            <ResetProfileButton label={__('ui.auth.handshake.lostBackup')} disabled={pending} variant="ghost"/>
        </div>
        <div class="switch">
            <Button type="button" variant="ghost" size="sm" disabled={pending} onclick={() => switchMode('passkey')}>{__('ui.auth.handshake.usePasskey')}</Button>
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
    .auth-field :global(.code) {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        letter-spacing: 0.06em;
    }
</style>
