<!--
  @component Small confirmation dialog for destructive settings actions.
  Renders a title, a warning text and cancel/confirm buttons; `onConfirm` is
  awaited while the confirm button shows a busy state via the `busy` prop.
-->
<script lang="ts">
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        open?: boolean;
        onOpenChange?: (open: boolean) => void;
        title: string;
        description: string;
        confirmLabel: string;
        /** Disables the buttons while the confirm action runs. */
        busy?: boolean;
        onConfirm: () => void | Promise<void>;
    }

    let {open = $bindable(false), onOpenChange, title, description, confirmLabel, busy = false, onConfirm}: Props = $props();
    const {__} = useTranslator();
</script>

<Dialog bind:open {onOpenChange} {title} {description} contentProps={{class: 'confirm-dialog-content'}}>
    {#snippet footer()}
        <div class="confirm-actions">
            <Button size="sm" variant="ghost" disabled={busy} onclick={() => (open = false)}>
                {__('ui.settings.common.cancel')}
            </Button>
            <Button size="sm" variant="delete" disabled={busy} onclick={() => onConfirm()}>
                {confirmLabel}
            </Button>
        </div>
    {/snippet}
</Dialog>

<style>
    :global(.confirm-dialog-content.confirm-dialog-content) {
        width: min(24rem, calc(100vw - 2 * var(--space-4)));
    }

    .confirm-actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--space-2);
    }
</style>
