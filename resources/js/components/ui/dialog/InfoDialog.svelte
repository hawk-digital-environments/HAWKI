<!--
  @component Informational dialog with a single OK action.

  Closable via the X button, Escape, or clicking outside. Use for notifications
  where the user only needs to acknowledge the information.
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
        /** Label for the OK button. @default "OK" */
        okLabel?: string;
        /** Called when the user clicks OK. */
        onOk?: () => void;
    }

    let {
        open = $bindable(false),
        onOpenChange,
        title,
        description,
        okLabel = __('ui.dialog.okLabel'),
        onOk
    }: Props = $props();

    function handleOpenChange(isOpen: boolean) {
        open = isOpen;
        onOpenChange?.(isOpen);
    }

    function handleOk() {
        handleOpenChange(false);
        onOk?.();
    }
</script>

<Dialog
    {open}
    onOpenChange={handleOpenChange}
    {title}
    {description}
    closable={true}
    contentProps={{ class: 'info-dialog-content' }}
>
    {#snippet footer()}
        <Button variant="fill" size="sm" onclick={handleOk}>{okLabel}</Button>
    {/snippet}
</Dialog>

<style>
    :global(.info-dialog-content.info-dialog-content) {
        max-width: 22rem;
    }
</style>
