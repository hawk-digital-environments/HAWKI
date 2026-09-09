<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button, { type ButtonVariant } from '$lib/components/ui/button/Button.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import { authErrorKey } from './authHelpers.js';

    let { label, disabled = false, variant = 'delete' }: {
        label: string;
        disabled?: boolean;
        variant?: ButtonVariant;
    } = $props();

    const app = useApp();
    const { __ } = useTranslator();
    let open = $state(false);
    let pending = $state(false);
    let error = $state('');
    let trigger = $state<HTMLButtonElement | null>(null);

    async function reset() {
        pending = true;
        error = '';
        try {
            await app.restApi.postToResourceAction('users', 'actions/reset-profile', {});
            app.stores.get('keychain').clearLocalSession();
            window.location.assign('/new/auth/register');
        } catch (e) {
            error = __(authErrorKey(e));
        } finally {
            pending = false;
        }
    }
</script>

{#if error}<p class="auth-error" role="alert">{error}</p>{/if}
<Button
    type="button"
    {variant}
    disabled={disabled || pending}
    bind:ref={trigger}
    onclick={() => { error = ''; open = true; }}
>{label}</Button>
<ConfirmDialog
    bind:open
    busy={pending}
    title={__('ui.auth.inconsistent.confirmTitle')}
    description={app.config.get().security.passkeyAutoGenerate ? __('ui.auth.inconsistent.automaticConfirmDescription') : __('ui.auth.inconsistent.confirmDescription')}
    okLabel={__('ui.auth.inconsistent.confirm')}
    confirmVariant="delete"
    restoreFocusTo={() => trigger}
    onConfirm={reset}
/>
