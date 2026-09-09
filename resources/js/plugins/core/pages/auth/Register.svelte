<script module lang="ts">
    import { configurePage } from '$lib/components/ui/routing/index.js';
    import { sanitizeNext } from '$lib/kernel/auth/navigation.js';
    import { RegistrationPolicySchema } from '$plugins/core/schemas/resources/auth.schema.js';
    export const config = configurePage({
        cacheKey: false,
        loadData: async ({ app, restApi, redirect }) => {
            const next = sanitizeNext(new URLSearchParams(window.location.search).get('next'));
            const connection = app.connection;
            if (!(
                connection.type === 'internal_registering_user' ||
                (connection.isAuthenticated && connection.keychain_state === 'setup_required')
            )) {
                redirect('auth.login', next ? { next } : undefined);
            }
            return {
                policy: await restApi.getFromResourceAction('announcements', 'actions/registration-policy', {
                    schema: RegistrationPolicySchema
                })
            };
        }
    });
</script>
<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import { deriveKey, exportCryptoKeyToString } from '$lib/kernel/encryption/utils.js';
    import { encryptSymmetric } from '$lib/kernel/encryption/symmetric.js';
    import {
        exportPrivateKeyToString,
        exportPublicKeyToString,
        generateAsymmetricKeyPair
    } from '$lib/kernel/encryption/asymmetric.js';
    import { generateSymmetricKey } from '$lib/kernel/encryption/symmetric.js';
    import { ApiTransportError } from '$lib/kernel/api/errors.js';
    import { onMount, tick, untrack } from 'svelte';
    import AuthFrame from './AuthFrame.svelte';
    import { authErrorKey, nextDestination } from './authHelpers.js';

    const { data }: RouteProps<typeof config> = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const restricted = app.config.get().security.passkeyRestrictCharacters;
    let currentPolicy = $state(untrack(() => data.policy));
    // The policy text is served in the current locale; follow language switches made on this page.
    let policyLocale = untrack(() => app.localization.locale.lang);
    $effect(() => {
        const lang = app.localization.locale.lang;
        if (lang === policyLocale) return;
        policyLocale = lang;
        void app.restApi
            .getFromResourceAction('announcements', 'actions/registration-policy', { schema: RegistrationPolicySchema })
            .then((refreshed) => { currentPolicy = refreshed; })
            .catch((e) => console.error('Failed to reload the registration policy', e));
    });
    const policy = $derived('policy' in currentPolicy ? null : currentPolicy);
    let accepted = $state(untrack(() => policy === null));
    let policyOpen = $state(false);
    let policyConsent = $state(false);
    let consentInput = $state<HTMLInputElement | null>(null);
    let passkey = $state('');
    let repeated = $state('');
    let stage = $state<'form' | 'policy' | 'backup'>('form');
    let error = $state('');
    let pending = $state(false);
    let payload = $state<Record<string, unknown> | null>(null);
    let backupCode = $state('');
    let committed = $state(false);
    let invalidField = $state<'policy' | 'passkey' | 'repeat' | null>(null);
    let title: HTMLHeadingElement;
    onMount(() => {
        if (policy) policyOpen = true;
    });
    const passkeyPattern = $derived(restricted ? '[A-Za-z0-9!@#$%^&*()_+-]+' : undefined);
    function validPasskey() {
        return passkey.length >= 8 && (!restricted || /^[A-Za-z0-9!@#$%^&*()_+-]+$/.test(passkey));
    }
    function createBackupCode() {
        const bytes = crypto.getRandomValues(new Uint8Array(8));
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        return hex.match(/.{1,4}/g)!.join('-');
    }
    async function prepare() {
        if (pending) return;
        error = '';
        invalidField = null;
        if (!accepted) {
            policyOpen = true;
            return;
        }
        if (!validPasskey()) {
            invalidField = 'passkey';
            error = __('ui.auth.register.passkeyInvalid');
            return;
        }
        if (passkey !== repeated) {
            invalidField = 'repeat';
            error = __('ui.auth.register.passkeyMismatch');
            return;
        }
        pending = true;
        try {
            const salts = app.config.get().salts;
            const connection = app.connection;
            if (!salts || !connection.hasUserInfo) throw new Error('Registration configuration is unavailable.');
            const user = connection.userinfo;
            const keychainKey = await deriveKey(passkey, 'keychain_encryptor', salts.userdata);
            const pair = await generateAsymmetricKeyPair();
            const aiConvKey = await generateSymmetricKey();
            const backup = createBackupCode();
            const set = [
                {
                    key: 'privateKey',
                    value: (
                        await encryptSymmetric(await exportPrivateKeyToString(pair.privateKey), keychainKey)
                    ).toString(),
                    type: 'private_key'
                },
                {
                    key: 'publicKey',
                    value: (await encryptSymmetric(await exportPublicKeyToString(pair.publicKey), keychainKey)).toString(),
                    type: 'public_key'
                },
                {
                    key: 'aiConvKey',
                    value: (await encryptSymmetric(await exportCryptoKeyToString(aiConvKey), keychainKey)).toString(),
                    type: 'ai_conv'
                }
            ];
            const backupKey = await deriveKey(backup, `${user.username}_backup`, salts.backup);
            const encryptedBackup = (await encryptSymmetric(passkey, backupKey)).toObject();
            payload = {
                policy: policy ? { id: policy.id, hash: policy.hash } : null,
                keychain: { set, publicKey: await exportPublicKeyToString(pair.publicKey) },
                backup: encryptedBackup
            };
            backupCode = backup;
            stage = 'backup';
            await tick();
            title?.focus();
        } catch (e) {
            error = __(authErrorKey(e));
        } finally {
            pending = false;
        }
    }
    function downloadBackup() {
        const blob = new Blob([backupCode + '\n'], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'hawki-backup-code.txt';
        a.click();
        URL.revokeObjectURL(url);
    }
    async function complete() {
        if (!payload) return;
        pending = true;
        error = '';
        try {
            if (!committed) {
                await app.restApi.postToResourceAction('auth', 'actions/complete-registration', payload);
                committed = true;
            }
            try {
                await app.stores.get('keychain').persistPasskey(passkey);
            } catch {
                error = __('ui.auth.errors.persistPasskey');
                return;
            }
            window.location.assign(
                `/new/auth/handshake${nextDestination() ? `?${new URLSearchParams({ next: nextDestination()! })}` : ''}`
            );
        } catch (e) {
            if (e instanceof ApiTransportError && e.code === 'policy_changed') {
                try {
                    currentPolicy = await app.restApi.getFromResourceAction(
                        'announcements',
                        'actions/registration-policy',
                        { schema: RegistrationPolicySchema }
                    );
                    accepted = 'policy' in currentPolicy;
                    if (payload)
                        payload = {
                            ...payload,
                            policy: 'policy' in currentPolicy ? null : { id: currentPolicy.id, hash: currentPolicy.hash }
                        };
                    policyConsent = false;
                    stage = accepted ? 'backup' : 'policy';
                    policyOpen = !accepted;
                } catch (refreshError) {
                    error = __(authErrorKey(refreshError));
                }
                return;
            }
            if (e instanceof ApiTransportError && e.code === 'registration_already_completed') {
                window.location.assign('/new/auth/login');
                return;
            }
            error = __(authErrorKey(e));
        } finally {
            pending = false;
        }
    }
    function acceptPolicy() {
        if (!policyConsent) {
            invalidField = 'policy';
            error = __('ui.auth.register.policyRequired');
            consentInput?.focus();
            return;
        }
        accepted = true;
        error = '';
        invalidField = null;
        policyOpen = false;
        if (stage === 'policy') stage = 'backup';
    }
</script>
<AuthFrame>
    <div class="auth-intro">
        <h1 id="auth-title" tabindex="-1" bind:this={title}>{stage === 'backup' ? __('ui.auth.register.backupTitle') : __('ui.auth.register.title')}</h1>
        <p class="auth-copy">{stage === 'backup' ? __('ui.auth.register.backupDescription') : __('ui.auth.register.description')}</p>
    </div>
    {#if error && invalidField === null}<p class="auth-error" role="alert">{error}</p>{/if}
    {#if stage === 'form'}
        <form class="auth-form" onsubmit={(e) => { e.preventDefault(); void prepare(); }}>
            <div class="auth-field">
                <label for="new-passkey">{__('ui.auth.register.passkey')}</label>
                <Input id="new-passkey" type="password" bind:value={passkey} pattern={passkeyPattern} autocomplete="new-password" required disabled={pending} aria-invalid={invalidField === 'passkey'} aria-describedby={invalidField === 'passkey' ? 'passkey-error' : 'passkey-help'}/>
                {#if invalidField === 'passkey'}
                    <small id="passkey-error" class="auth-error" role="alert">{error}</small>
                {:else}
                    <small id="passkey-help" class="auth-hint">{__('ui.auth.register.passkeyHint')}</small>
                {/if}
            </div>
            <div class="auth-field">
                <label for="repeat-passkey">{__('ui.auth.register.repeatPasskey')}</label>
                <Input id="repeat-passkey" type="password" bind:value={repeated} pattern={passkeyPattern} autocomplete="new-password" required disabled={pending} aria-invalid={invalidField === 'repeat'} aria-describedby={invalidField === 'repeat' ? 'repeat-passkey-error' : undefined}/>
                {#if invalidField === 'repeat'}<small id="repeat-passkey-error" class="auth-error" role="alert">{error}</small>{/if}
            </div>
            {#if pending}<p role="status" class="auth-hint">{__('ui.auth.register.preparing')}</p>{/if}
            {#if policy}
                <Button type="button" variant="ghost" disabled={pending} onclick={() => policyOpen = true}>{__('ui.auth.register.readPolicy')}</Button>
            {/if}
            <Button type="submit" variant="accent" disabled={pending} block>{__('ui.auth.register.continue')}</Button>
        </form>
    {:else if stage === 'policy'}
        <Button onclick={() => policyOpen = true} variant="accent" block>{__('ui.auth.register.readPolicy')}</Button>
    {:else}
        <output class="backup-code" aria-label={__('ui.auth.register.backupCode')}>{backupCode}</output>
        <div class="auth-actions">
            <Button onclick={downloadBackup} variant="stroke">{__('ui.auth.register.downloadBackup')}</Button>
            <Button onclick={() => void complete()} variant="accent" disabled={pending}>{pending ? __('ui.auth.register.saving') : __('ui.auth.register.finish')}</Button>
        </div>
    {/if}
</AuthFrame>
{#if policy}
    <Dialog
        open={policyOpen}
        onOpenChange={(open) => policyOpen = open}
        title={__('ui.auth.register.policyTitle')}
        description={__('ui.auth.register.policyDescription')}
        contentProps={{
            class: 'registration-policy-dialog',
            onCloseAutoFocus: (event) => { event.preventDefault(); title?.focus({preventScroll: true}); }
        }}
        footerProps={{class: 'registration-policy-footer'}}
    >
        <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable policy must be reachable by keyboard.) -->
        <div class="policy-document" lang={policy.locale.replace('_', '-')} role="region" aria-label={__('ui.auth.register.policyTitle')} tabindex="0">
            <Markdown message={policy.text} headingBaseLevel={3}/>
        </div>
        {#snippet footer()}
            <form class="policy-confirmation" onsubmit={(event) => { event.preventDefault(); acceptPolicy(); }}>
                <label class="consent">
                    <input type="checkbox" bind:this={consentInput} bind:checked={policyConsent} aria-invalid={invalidField === 'policy'} aria-describedby={invalidField === 'policy' ? 'policy-error' : undefined}/>
                    <span>{__('ui.auth.register.acceptPolicy')}</span>
                </label>
                {#if invalidField === 'policy'}<p id="policy-error" class="auth-error" role="alert">{error}</p>{/if}
                <div class="policy-actions">
                    <Button type="button" variant="stroke" onclick={() => policyOpen = false}>{__('ui.dialog.cancelLabel')}</Button>
                    <Button type="submit" variant="accent">{__('ui.auth.register.confirmPolicy')}</Button>
                </div>
            </form>
        {/snippet}
    </Dialog>
{/if}
<style>
    :global(.registration-policy-dialog.registration-policy-dialog) {
        box-sizing: border-box;
        width: calc(100% - 2rem);
        max-width: 48rem;
        max-height: calc(100dvh - 3rem);
        grid-template-rows: auto minmax(0, 1fr) auto;
        gap: var(--space-5);
        overflow: hidden;
    }
    :global(.registration-policy-dialog .dialog-header) {
        padding-right: var(--space-6);
        gap: var(--space-2);
    }
    :global(.registration-policy-dialog .dialog-title) {
        font-size: 1.375rem;
        line-height: 1.3;
    }
    :global(.registration-policy-dialog .dialog-description) {
        font-size: 0.9375rem;
        line-height: 1.5;
    }
    .policy-document {
        min-height: 0;
        overflow: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        padding-right: var(--space-4);
        color: var(--color-text);
    }
    .policy-document :global(.markstream-svelte) {
        font-size: 1rem;
        line-height: 1.65;
        color: inherit;
    }
    .policy-document :global(.heading-node.heading-1) {
        font-size: 1.5rem;
        line-height: 1.3;
    }
    .policy-document :global(.heading-node.heading-2) {
        font-size: 1.25rem;
        line-height: 1.4;
    }
    .policy-document :global(.heading-node.heading-3) {
        font-size: 1.125rem;
    }
    .policy-document :global(.node-content:first-child .heading-node) {
        margin-top: 0;
    }
    .policy-document:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -2px;
    }
    :global(.registration-policy-footer) {
        padding-top: var(--space-5);
        border-top: var(--border);
    }
    .policy-confirmation {
        display: grid;
        gap: var(--space-4);
        width: 100%;
    }
    .policy-confirmation .auth-error {
        margin: 0;
        color: var(--color-error);
        font-size: 0.9375rem;
        line-height: 1.5;
    }
    .policy-actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--space-3);
    }
    .consent {
        display: flex;
        gap: var(--space-3);
        align-items: start;
        font-size: 0.9375rem;
        line-height: 1.5;
        cursor: pointer;
    }
    .consent input {
        flex-shrink: 0;
        width: 1.125rem;
        height: 1.125rem;
        margin: 0.125rem 0 0;
        accent-color: var(--color-accent-fill);
    }
    .consent input:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }
    @media (max-width: 36rem) {
        :global(.registration-policy-dialog.registration-policy-dialog) {
            max-height: calc(100dvh - 1rem);
            width: calc(100% - 1rem);
            padding: var(--space-4);
            gap: var(--space-4);
        }
        .policy-document {
            padding-right: var(--space-2);
        }
        .policy-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }
    /* The one thing on this page the user has to keep: a single click selects it whole. */
    .backup-code {
        display: block;
        padding: var(--space-5) var(--space-4);
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-light);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: var(--font-size-xl);
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.08em;
        text-align: center;
        user-select: all;
    }
</style>
