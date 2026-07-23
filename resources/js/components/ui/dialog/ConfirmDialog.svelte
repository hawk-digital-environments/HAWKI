<!--
  @component Blocking confirmation dialog with OK and Cancel actions.

  Non-closable — Escape and outside clicks are suppressed so the user must
  explicitly confirm or cancel. The confirm button receives autofocus.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import Dialog from './Dialog.svelte';
    import Button from '../button/Button.svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        /** Whether the dialog is open. Supports bind:open for two-way binding. */
        open?: boolean;
        /** Called when the dialog open state changes. */
        onOpenChange?: (open: boolean) => void;
        /** The title displayed in the dialog header. */
        title?: string | Snippet;
        /** A description shown below the title. */
        description?: string | Snippet;
        /** Label for the confirm button. @default "OK" */
        okLabel?: string;
        /** Label for the cancel button. @default "Abbrechen" */
        cancelLabel?: string;
        /** Called when the user clicks the confirm button. */
        onConfirm?: () => void;
        /** Called when the user clicks the cancel button. */
        onCancel?: () => void;
    }

    let {
        open = $bindable(false),
        onOpenChange,
        title,
        description,
        okLabel = __('ui.dialog.okLabel'),
        cancelLabel = __('ui.dialog.cancelLabel'),
        onConfirm,
        onCancel
    }: Props = $props();

    function handleOpenChange(isOpen: boolean) {
        open = isOpen;
        onOpenChange?.(isOpen);
    }

    function handleConfirm() {
        handleOpenChange(false);
        onConfirm?.();
    }

    function handleCancel() {
        handleOpenChange(false);
        onCancel?.();
    }
</script>

<Dialog
    {open}
    onOpenChange={handleOpenChange}
    {title}
    {description}
    closable={false}
    contentProps={{
        class: 'confirm-dialog-content',
        onEscapeKeydown: (e) => e.preventDefault(),
        onInteractOutside: (e) => e.preventDefault()
    }}
>
    {#snippet footer()}
        <Button variant="stroke" size="sm" onclick={handleCancel}>{cancelLabel}</Button>
        <Button variant="fill" size="sm" autofocus onclick={handleConfirm}>{okLabel}</Button>
    {/snippet}
</Dialog>

<style>
    :global(.confirm-dialog-content.confirm-dialog-content) {
        max-width: 22rem;
    }
</style>
