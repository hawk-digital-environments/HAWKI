<!--
  @component The backup-code step of the registration: the generated code, kept as the
  one thing the user has to keep, and the download / finish actions that complete the
  registration (the completion call itself stays with the page).
-->
<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** The generated backup code to show and download. */
        backupCode: string;
        /** While the registration is being saved. */
        pending?: boolean;
        /** Completes the registration. */
        onComplete: () => void;
    }

    let {backupCode, pending = false, onComplete}: Props = $props();
    const {__} = useTranslator();

    function downloadBackup() {
        const blob = new Blob([backupCode + '\n'], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'hawki-backup-code.txt';
        a.click();
        // Deferred: revoking in the same tick can abort the download in some browsers (Safari).
        setTimeout(() => URL.revokeObjectURL(url), 0);
    }
</script>

<output class="backup-code" aria-label={__('ui.auth.register.backupCode')}>{backupCode}</output>
<div class="auth-actions">
    <Button onclick={downloadBackup} variant="stroke">{__('ui.auth.register.downloadBackup')}</Button>
    <Button onclick={onComplete} variant="fill" busy={pending}><span class="finish-label">{pending ? __('ui.auth.register.saving') : __('ui.auth.register.finish')}</span></Button>
</div>

<style>
    /* The saving status can be longer than the button; cut it off with an ellipsis instead of wrapping. */
    .finish-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
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
