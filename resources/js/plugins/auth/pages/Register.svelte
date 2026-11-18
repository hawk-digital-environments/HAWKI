<script module lang="ts">
    import { configurePage } from '$lib/components/ui/routing/index.js';
    import { assignAuthPage, sanitizeNext } from '$lib/kernel/auth/navigation.js';
    import { ApiTransportError } from '$lib/kernel/api/errors.js';
    import { registrationErrorPage } from './authHelpers.js';
    export const config = configurePage({
        cacheKey: false,
        loadData: async ({ app, restApi, redirect }) => {
            const next = sanitizeNext(app.router, new URLSearchParams(window.location.search).get('next'));
            const connection = app.connection;
            if (!(
                connection.type === 'internal_registering_user' ||
                (connection.isAuthenticated && connection.keychain_state === 'setup_required')
            )) {
                redirect('auth.login', next ? { next } : undefined);
            }
            try {
                return {policy: await restApi.getResource('registrations', 'hawki')};
            } catch (error) {
                if (error instanceof ApiTransportError) {
                    const page = registrationErrorPage(error.code);
                    if (page) redirect(`auth.${page}`, next ? {next} : undefined);
                    if (error.code === 'registration_policy_unavailable') return {policy: null};
                }
                throw error;
            }
        }
    });
</script>
<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import BookOpen01Icon from '$lib/components/ui/icons/iconset/BookOpen01Icon.svelte';
    import AuthField from '../components/AuthField.svelte';
    import AuthError from '../components/AuthError.svelte';
    import PolicyConsentDialog from './PolicyConsentDialog.svelte';
    import BackupStage from './BackupStage.svelte';
    import { deriveKey, exportCryptoKeyToString, generatePasskey } from '$lib/kernel/encryption/utils.js';
    import { encryptSymmetric } from '$lib/kernel/encryption/symmetric.js';
    import {
        exportPrivateKeyToString,
        exportPublicKeyToString,
        generateAsymmetricKeyPair
    } from '$lib/kernel/encryption/asymmetric.js';
    import { generateSymmetricKey } from '$lib/kernel/encryption/symmetric.js';
    import { tick, untrack } from 'svelte';
    import AuthFrame from './AuthFrame.svelte';
    import RegistrationWelcome from './welcome/RegistrationWelcome.svelte';
    import { authErrorKey, nextDestination } from './authHelpers.js';

    const { data }: RouteProps<typeof config> = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const restricted = app.config.get().security.passkeyRestrictCharacters;
    const autoGenerate = app.config.get().security.passkeyAutoGenerate;
    let currentPolicy = $state(untrack(() => data.policy));
    // The policy text is served in the current locale; follow language switches made on this page.
    let policyLocale = untrack(() => app.localization.locale.lang);
    $effect(() => {
        const lang = app.localization.locale.lang;
        if (lang === policyLocale) return;
        policyLocale = lang;
        void app.restApi
            .getResource('registrations', 'hawki')
            .then((refreshed) => { currentPolicy = refreshed; })
            .catch(showRegistrationError);
    });
    const policy = $derived(currentPolicy?.policy ?? null);
    let accepted = $state(untrack(() => currentPolicy?.policy == null));
    // The reading/consent session lives inside PolicyConsentDialog; the page only sees
    // open/closed, acceptance, decline, and a revoked consent (which re-locks Continue).
    let policyOpen = $state(false);
    let passkey = $state('');
    let repeated = $state('');
    let passkeyInput = $state<HTMLInputElement | null>(null);
    let repeatInput = $state<HTMLInputElement | null>(null);
    // Starts with the onboarding (see `welcome/RegistrationWelcome.svelte`); the setup itself begins at 'form'.
    let stage = $state<'welcome' | 'form' | 'policy' | 'backup'>('welcome');
    let error = $state('');
    let pending = $state(false);
    let errorElement = $state<HTMLParagraphElement | null>(null);
    $effect(() => { if (error || !currentPolicy) errorElement?.focus(); });
    let payload = $state<Record<string, unknown> | null>(null);
    let backupCode = $state('');
    let committed = $state(false);
    let invalidField = $state<'passkey' | 'repeat' | null>(null);
    let title = $state<HTMLHeadingElement | null>(null);
    async function focusTitle() {
        await tick();
        title?.focus();
    }
    function finishWelcome() {
        stage = 'form';
        if (policy) policyOpen = true;
        else if (autoGenerate) void prepare();
        else void focusTitle();
    }
    const passkeyPattern = $derived(restricted ? '[A-Za-z0-9!@#$%^&*()_+-]+' : undefined);
    function validPasskey() {
        return passkey.length >= 8 && (!restricted || /^[A-Za-z0-9!@#$%^&*()_+-]+$/.test(passkey));
    }
    // Continue stays disabled until the policy is accepted and both passkey fields are
    // filled; length and match are still checked on submit so their errors can show.
    // With auto-generated passkeys the button opens the policy instead, so it stays enabled.
    const continueDisabled = $derived(!autoGenerate && (!accepted || !passkey || !repeated));
    /** Why Continue is disabled, shown under the button; the first missing piece wins. */
    const continueBlockedReason = $derived(
        !continueDisabled ? null
            : !accepted ? __('ui.auth.register.continueBlocked.policy')
            : __('ui.auth.register.continueBlocked.passkey')
    );
    function createBackupCode() {
        const bytes = crypto.getRandomValues(new Uint8Array(8));
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        return hex.match(/.{1,4}/g)!.join('-');
    }
    async function prepare() {
        if (pending || !currentPolicy) return;
        error = '';
        invalidField = null;
        if (!accepted) {
            policyOpen = true;
            return;
        }
        if (!autoGenerate && !validPasskey()) {
            invalidField = 'passkey';
            passkeyInput?.focus();
            error = __('ui.auth.register.passkeyInvalid');
            return;
        }
        if (!autoGenerate && passkey !== repeated) {
            invalidField = 'repeat';
            repeatInput?.focus();
            error = __('ui.auth.register.passkeyMismatch');
            return;
        }
        pending = true;
        try {
            if (autoGenerate && !passkey) passkey = generatePasskey();
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
    async function complete() {
        if (pending || !payload) return;
        pending = true;
        error = '';
        try {
            if (!committed) {
                await app.restApi.createResource('registrations', payload);
                committed = true;
            }
            try {
                await app.stores.get('keychain').persistPasskey(passkey);
            } catch {
                error = __('ui.auth.errors.persistPasskey');
                return;
            }
            assignAuthPage(app.router, 'handshake', nextDestination(app));
        } catch (e) {
            if (e instanceof ApiTransportError && e.code === 'registration_policy_changed') {
                try {
                    currentPolicy = await app.restApi.getResource('registrations', 'hawki');
                    accepted = currentPolicy?.policy == null;
                    if (payload)
                        payload = {
                            ...payload,
                            policy: currentPolicy?.policy ? { id: currentPolicy.policy.id, hash: currentPolicy.policy.hash } : null
                        };
                    stage = accepted ? 'backup' : 'policy';
                    policyOpen = !accepted;
                } catch (refreshError) {
                    showRegistrationError(refreshError);
                }
                return;
            }
            if (e instanceof ApiTransportError && registrationErrorPage(e.code)) {
                assignAuthPage(app.router, registrationErrorPage(e.code)!);
                return;
            }
            error = __(authErrorKey(e));
        } finally {
            pending = false;
        }
    }
    function showRegistrationError(failure: unknown) {
        if (failure instanceof ApiTransportError) {
            const page = registrationErrorPage(failure.code);
            if (page) {
                assignAuthPage(app.router, page);
                return;
            }
            if (failure.code === 'registration_policy_unavailable') {
                currentPolicy = null;
                return;
            }
        }
        error = __(authErrorKey(failure));
    }
    /** Declining the usage policy ends the registration: the user is logged out. */
    function declinePolicy() {
        void app.logout().catch(() => { /* The root layout shows the retry action. */ });
    }
    // Unchecking the consent box revokes an earlier acceptance (reported by the dialog),
    // so Continue is blocked again until the terms are re-accepted.
    function revokeConsent() {
        accepted = false;
    }
    /** Called by the dialog after the user confirmed consent to the current policy. */
    function acceptPolicy() {
        accepted = true;
        error = '';
        invalidField = null;
        if (stage === 'policy') stage = 'backup';
        else if (autoGenerate) void prepare();
    }
</script>
<AuthFrame>
    {#if !(stage === 'welcome' && currentPolicy)}
        <div class="auth-intro">
            <h1 id="auth-title" tabindex="-1" bind:this={title}>{stage === 'backup' ? __('ui.auth.register.backupTitle') : __('ui.auth.register.title')}</h1>
            <p class="auth-copy">{stage === 'backup' ? __('ui.auth.register.backupDescription') : autoGenerate ? __('ui.auth.register.automaticDescription') : __('ui.auth.register.description')}</p>
        </div>
    {/if}
    {#if error && invalidField === null}<AuthError message={error} bind:ref={errorElement}/>{/if}
    {#if !currentPolicy}
        <AuthError message={__('ui.auth.errors.registration_policy_unavailable')} bind:ref={errorElement}/>
    {:else if stage === 'welcome'}
        <RegistrationWelcome onFinish={finishWelcome}/>
    {:else if stage === 'form'}
        <form class="auth-form" onsubmit={(e) => { e.preventDefault(); void prepare(); }}>
            {#if !autoGenerate}
                <AuthField id="new-passkey" label={__('ui.auth.register.passkey')} type="password" bind:value={passkey} bind:ref={passkeyInput} pattern={passkeyPattern} autocomplete="new-password" required pending={pending} error={invalidField === 'passkey' ? error : undefined} hint={__('ui.auth.register.passkeyHint')}/>
                <AuthField id="repeat-passkey" label={__('ui.auth.register.repeatPasskey')} type="password" bind:value={repeated} bind:ref={repeatInput} pattern={passkeyPattern} autocomplete="new-password" required pending={pending} error={invalidField === 'repeat' ? error : undefined}/>
            {/if}
            {#if pending}<p role="status" class="auth-hint">{__('ui.auth.register.preparing')}</p>{/if}
            {#if policy && !autoGenerate}
                <Button type="button" variant="ghost" iconLeft={BookOpen01Icon} busy={pending} onclick={() => { if (!pending) policyOpen = true; }}>{__('ui.auth.register.readPolicy')}</Button>
            {/if}
            <Button type="submit" variant="fill" iconLeft={!accepted && autoGenerate ? BookOpen01Icon : undefined} disabled={continueDisabled} busy={pending} aria-describedby={continueBlockedReason ? 'continue-blocked' : undefined} block>{!accepted && autoGenerate ? __('ui.auth.register.readPolicy') : __('ui.auth.register.continue')}</Button>
            {#if continueBlockedReason}<small id="continue-blocked" class="auth-hint continue-blocked">{continueBlockedReason}</small>{/if}
        </form>
    {:else if stage === 'policy'}
        <Button onclick={() => { if (!pending) policyOpen = true; }} variant="fill" iconLeft={BookOpen01Icon} block>{__('ui.auth.register.readPolicy')}</Button>
    {:else}
        <BackupStage backupCode={backupCode} pending={pending} onComplete={() => void complete()}/>
    {/if}
</AuthFrame>
{#if policy}
    <PolicyConsentDialog
        policy={policy}
        bind:open={policyOpen}
        onAccept={acceptPolicy}
        onDecline={declinePolicy}
        onConsentRevoked={revokeConsent}
        returnFocus={() => title?.focus({preventScroll: true})}
    />
{/if}
<style>
    .continue-blocked {
        text-align: center;
    }
</style>
