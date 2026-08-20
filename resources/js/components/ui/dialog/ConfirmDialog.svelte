<!--
  @component Blocking confirmation dialog with OK and Cancel actions.

  Non-closable — Escape and outside clicks are suppressed so the user must
  explicitly confirm or cancel. The confirm button receives autofocus.

  Usage — pair `bind:open` with a trigger elsewhere (e.g. a "Delete" menu
  item) and destroy on confirm:
    <ConfirmDialog
        bind:open={deleteConfirmOpen}
        title={__('chat.nameMenu.deleteConfirmTitle', {name})}
        onConfirm={() => slug && oldUiBridge.triggerDeleteChat(slug)}
    />
    <DropdownMenuItem variant="destructive" onclick={() => deleteConfirmOpen = true}>
        Delete
    </DropdownMenuItem>
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import Dialog from './Dialog.svelte';
    import Button, {type ButtonVariant} from '../button/Button.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

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
        onConfirm?: () => unknown | Promise<unknown>;
        /** Called when the user clicks the cancel button. */
        onCancel?: () => void;
        /** Prevents duplicate actions while an async confirmation is running. */
        busy?: boolean;
        /** Visual treatment for the confirmation action. */
        confirmVariant?: ButtonVariant;
    }

    let {
        open = $bindable(false),
        onOpenChange,
        title,
        description,
        okLabel = __('ui.dialog.okLabel'),
        cancelLabel = __('ui.dialog.cancelLabel'),
        onConfirm,
        onCancel,
        busy = false,
        confirmVariant = 'fill'
    }: Props = $props();

    function handleOpenChange(isOpen: boolean) {
        open = isOpen;
        onOpenChange?.(isOpen);
    }

    async function handleConfirm() {
        await onConfirm?.();
        handleOpenChange(false);
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
        <Button variant="stroke" size="sm" disabled={busy} onclick={handleCancel}>{cancelLabel}</Button>
        <Button variant={confirmVariant} size="sm" disabled={busy} autofocus onclick={handleConfirm}>{okLabel}</Button>
    {/snippet}
</Dialog>

<style>
    :global(.confirm-dialog-content.confirm-dialog-content) {
        max-width: 22rem;
    }
</style>
