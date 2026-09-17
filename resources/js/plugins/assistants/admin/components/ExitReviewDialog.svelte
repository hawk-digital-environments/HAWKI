<!--
  @component Decision shown when an admin leaves the assistant detail page
  with unresolved review flags that were never sent, discarded, or acted on
  (mirrors the assistant builder's own exit guard — see
  `modules/builder/components/ConfirmBuilderExit.svelte` /
  `ExitDraftDialog.svelte` for the sibling pattern this one follows).

  Three outcomes:
  - Send to creator — denies the pending review with the flagged feedback as
    the reason (same effect as the "Ask for edit" action).
  - Discard flags — deletes every unresolved flag; the review itself is left
    untouched (still pending).
  - Save as draft — leaves everything exactly as it is (flags stay
    unresolved, review stays pending) and simply lets the navigation proceed;
    the Publishing Center table then shows this assistant as a draft in
    progress so an admin can pick it back up later.

  Escape and outside clicks dismiss the dialog (stay on the page), reported
  through onDismiss. The owner controls `open` entirely, same contract as
  ExitDraftDialog: on any of the three choices the caller settles the
  decision once its own work is done and the guard's verdict navigates away.
-->
<script lang="ts">
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';

    const { __ } = useTranslator();

    interface Props {
        /** Whether the dialog is open. Owned by the caller — see the component comment. */
        open?: boolean;
        /** Disables the actions and close requests while a choice is being carried out. */
        busy?: boolean;
        /** Number of unresolved flags, interpolated into the description. */
        flagCount: number;
        onSend?: () => unknown | Promise<unknown>;
        onDiscard?: () => unknown | Promise<unknown>;
        onDraft?: () => unknown | Promise<unknown>;
        /** Dismissed via Escape or an outside click: stay on the page. */
        onDismiss?: () => void;
    }

    let {
        open = $bindable(false),
        busy = false,
        flagCount,
        onSend,
        onDiscard,
        onDraft,
        onDismiss
    }: Props = $props();

    function handleOpenChange(isOpen: boolean): void {
        if (!isOpen && busy) {
            return;
        }
        open = isOpen;
        if (!isOpen) {
            onDismiss?.();
        }
    }
</script>

<Dialog
    {open}
    onOpenChange={handleOpenChange}
    title={__('admin.detail.exit_dialog_title')}
    description={__('admin.detail.exit_dialog_description', { count: String(flagCount) })}
    closable={false}
    contentProps={{ class: 'exit-review-dialog-content' }}
>
    {#snippet footer()}
        <Button variant="delete" size="sm" disabled={busy} onclick={onDiscard}>
            {__('admin.detail.exit_dialog_discard')}
        </Button>
        <Button variant="stroke" size="sm" disabled={busy} onclick={onDraft}>
            {__('admin.detail.exit_dialog_draft')}
        </Button>
        <Button variant="fill" size="sm" disabled={busy} autofocus onclick={onSend}>
            {__('admin.detail.exit_dialog_send')}
        </Button>
    {/snippet}
</Dialog>

<style>
    /* Three buttons with their German labels need more room than ConfirmDialog's default. */
    :global(.exit-review-dialog-content.exit-review-dialog-content) {
        max-width: 32rem;
    }

    /* Safety net for narrow viewports, where the three buttons exceed the available width. */
    :global(.exit-review-dialog-content .dialog-footer) {
        flex-wrap: wrap;
    }
</style>
