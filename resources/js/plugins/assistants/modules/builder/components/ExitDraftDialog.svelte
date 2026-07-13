<!--
  @component Decision shown when the user leaves the builder while the
  assistant is still an unreleased draft: keep the draft (saved, visible
  under "Entwürfe") or discard it (permanently deleted). Escape and
  outside clicks dismiss the dialog, meaning "neither — continue editing"
  (stay in the builder); both are reported through onDismiss.

  The owner controls `open` entirely: this component never closes itself
  (on keep/discard the owner settles the decision once its work is done —
  the guard's verdict then has the router navigate away; on dismiss the
  owner settles it as "stay"), so the only prop mutation is none at all.
  Close requests arriving while `busy` are ignored — the pending
  keep/discard is about to settle the dialog one way or the other.
-->
<script lang="ts">
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** Whether the dialog is open. Owned by the caller — see the component comment. */
        open?: boolean;
        /** Disables the actions and close requests while a keep/discard request is in flight. */
        busy?: boolean;
        /** Keep the draft: save pending edits, then leave the builder. */
        onKeep?: () => unknown | Promise<unknown>;
        /** Discard the draft: permanently delete it, then leave the builder. */
        onDiscard?: () => unknown | Promise<unknown>;
        /** Dismissed via Escape or an outside click: continue editing in the builder. */
        onDismiss?: () => void;
    }

    let {
        open = $bindable(false),
        busy = false,
        onKeep,
        onDiscard,
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
    title={__('assistants.builder.exit_dialog.title')}
    description={__('assistants.builder.exit_dialog.description')}
    closable={false}
    contentProps={{class: 'exit-draft-dialog-content'}}
>
    {#snippet footer()}
        <Button variant="delete" size="sm" disabled={busy} onclick={onDiscard}>
            {__('assistants.builder.exit_dialog.discard')}
        </Button>
        <Button variant="fill" size="sm" disabled={busy} autofocus onclick={onKeep}>
            {__('assistants.builder.exit_dialog.keep')}
        </Button>
    {/snippet}
</Dialog>

<style>
    /* Wider than ConfirmDialog's 22rem: the two action buttons with their
       German labels need one row. */
    :global(.exit-draft-dialog-content.exit-draft-dialog-content) {
        max-width: 28rem;
    }

    /* Safety net for narrow viewports, where the two buttons exceed the
       available width: stack instead of overflowing. */
    :global(.exit-draft-dialog-content .dialog-footer) {
        flex-wrap: wrap;
    }
</style>
