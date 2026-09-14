<!--
  @component Blocking confirmation dialog with OK and Cancel actions.

  Rendered as `role="alertdialog"` without a close button. Outside clicks are
  suppressed so the user must explicitly confirm or cancel; Escape cancels.
  The cancel button is first in DOM order and therefore receives initial
  focus, so a destructive confirm action is never pre-focused.

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
        /** Receives focus after closing when it returns an element. */
        restoreFocusTo?: () => HTMLElement | null;
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
        restoreFocusTo,
        busy = false,
        confirmVariant = 'fill'
    }: Props = $props();

    let confirming = $state(false);
    const isBusy = $derived(busy || confirming);

    function handleOpenChange(isOpen: boolean) {
        open = isOpen;
        onOpenChange?.(isOpen);
    }

    async function handleConfirm() {
        if (isBusy) return;

        confirming = true;
        try {
            await onConfirm?.();
            handleOpenChange(false);
        } finally {
            confirming = false;
        }
    }

    function handleCancel() {
        handleOpenChange(false);
        onCancel?.();
    }

    function handleCloseAutoFocus(event: Event) {
        const target = restoreFocusTo?.();
        if (!target) return;
        event.preventDefault();
        target.focus({preventScroll: true});
    }
</script>

<Dialog
    {open}
    onOpenChange={handleOpenChange}
    {title}
    {description}
    closable={false}
    role="alertdialog"
    contentProps={{
        class: 'confirm-dialog-content',
        // bits-ui closes the dialog afterwards (via onOpenChange); Escape means cancel.
        onEscapeKeydown: () => onCancel?.(),
        onInteractOutside: (e) => e.preventDefault(),
        onCloseAutoFocus: handleCloseAutoFocus
    }}
>
    {#snippet footer()}
        <Button variant="stroke" size="sm" disabled={isBusy} onclick={handleCancel}>{cancelLabel}</Button>
        <Button variant={confirmVariant} size="sm" disabled={isBusy} onclick={handleConfirm}>{okLabel}</Button>
    {/snippet}
</Dialog>

<style>
    :global(.confirm-dialog-content.confirm-dialog-content) {
        max-width: 22rem;
    }
</style>
