<script module lang="ts">
    import { configurePage } from '$lib/components/ui/routing/index.js';
    import { authPageUrl, sanitizeNext } from '$lib/kernel/auth/navigation.js';
    export const config = configurePage({
        cacheKey: false,
        loadData: async ({ app, restApi, redirect }) => {
            const next = sanitizeNext(app.router, new URLSearchParams(window.location.search).get('next'));
            const connection = app.connection;
            if (connection.type === 'internal_registering_user') redirect('auth.register', next ? { next } : undefined);
            if (connection.isAuthenticated) {
                const state = connection.keychain_state;
                redirect(
                    state === 'setup_required' ? 'auth.register'
                    : state === 'inconsistent' ? 'auth.inconsistent'
                    : 'auth.handshake',
                    next ? { next } : undefined
                );
            }
            return { auth: await restApi.getResource('auth', 'hawki') };
        }
    });
</script>
<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import AuthFrame from './AuthFrame.svelte';
    import { authErrorCodeKey, authErrorKey, loginReason, nextDestination } from './authHelpers.js';
    import { LoginResponseSchema } from '$plugins/auth/schemas/resources/auth.schema.js';
    import { untrack } from 'svelte';
    const { data }: RouteProps<typeof config> = $props();
    const app = useApp();
    const { __ } = useTranslator();
    let account = $state('');
    let password = $state('');
    let pending = $state(false);
    let errorElement = $state<HTMLParagraphElement | null>(null);
    $effect(() => { if (error) errorElement?.focus(); });
    const initialError = untrack(() => data.auth.last_error ?? loginReason(new URLSearchParams(window.location.search).get('reason')));
    let error = $state(initialError ? __(authErrorCodeKey(initialError)) : '');
    function redirectUrl(startUrl: string) {
        const url = new URL(startUrl, window.location.origin);
        const next = nextDestination(app);
        if (next) url.searchParams.set('next', next);
        return url.pathname + url.search + url.hash;
    }
    async function submit() {
        if (pending) return;
        pending = true;
        error = '';
        try {
            const response = await app.restApi.postToResourceAction(
                'auth',
                'actions/login',
                { account, password },
                { schema: LoginResponseSchema }
            );
            window.location.assign(authPageUrl(app.router, response.meta.next, nextDestination(app)));
        } catch (e) {
            error = __(authErrorKey(e));
        } finally {
            pending = false;
        }
    }
    function beginRedirect() {
        if (data.auth.mode === 'redirect') window.location.assign(redirectUrl(data.auth.start_url));
    }
</script>
<AuthFrame canvas>
    <div class="auth-intro">
        <h1 id="auth-title">{__('ui.auth.login.title')}</h1>
        <p class="auth-copy">{data.auth.mode === 'credentials' ? __('ui.auth.login.description') : __('ui.auth.login.redirectDescription')}</p>
    </div>
    {#if error}<p class="auth-error" role="alert" tabindex="-1" bind:this={errorElement}>{error}</p>{/if}
    {#if data.auth.mode === 'credentials'}
        <form class="auth-form" onsubmit={(e) => { e.preventDefault(); void submit(); }}>
            <div class="auth-field">
                <label for="auth-account">{__('ui.auth.login.account')}</label>
                <Input id="auth-account" bind:value={account} autocomplete="username" required readonly={pending} aria-disabled={pending} aria-busy={pending}/>
            </div>
            <div class="auth-field">
                <label for="auth-password">{__('ui.auth.login.password')}</label>
                <Input id="auth-password" type="password" bind:value={password} autocomplete="current-password" required readonly={pending} aria-disabled={pending} aria-busy={pending}/>
            </div>
            <Button type="submit" variant="accent" aria-disabled={pending} aria-busy={pending} block>{pending ? __('ui.auth.login.signingIn') : __('ui.auth.login.submit')}</Button>
        </form>
    {:else}
        <Button onclick={beginRedirect} variant="accent" block>{__('ui.auth.login.redirect')}</Button>
    {/if}
</AuthFrame>
