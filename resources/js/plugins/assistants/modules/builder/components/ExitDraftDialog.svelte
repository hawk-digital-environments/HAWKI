<!--
  @component Decision shown when the user leaves the builder with something
  undecided. Which decision depends on `variant`:

  - `'draft'` — the session minted the assistant (create / remix): keep it
    (saved, visible under "Entwürfe") or discard it (permanently deleted).
  - `'changes'` — the session edited an assistant that already existed: keep
    this session's changes or roll them back to how the assistant was when
    the builder opened it. The assistant itself is never deleted here.

  Escape and outside clicks dismiss the dialog, meaning "neither — continue
  editing" (stay in the builder); both are reported through onDismiss.

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
        /** Which decision to present — see the component comment. */
        variant?: 'draft' | 'changes';
        /** Keep: save pending edits, then leave the builder. */
        onKeep?: () => unknown | Promise<unknown>;
        /** Discard: delete the draft (`'draft'`) or roll the session's changes back (`'changes'`), then leave. */
        onDiscard?: () => unknown | Promise<unknown>;
        /** Dismissed via Escape or an outside click: continue editing in the builder. */
        onDismiss?: () => void;
    }

    let {
        open = $bindable(false),
        busy = false,
        variant = 'draft',
        onKeep,
        onDiscard,
        onDismiss
    }: Props = $props();

    /** Each variant's copy, resolved together so the markup below stays one
     *  set of slots rather than branching per string. */
    const t = $derived(
        variant === 'changes'
            ? {
                title: __('assistants.builder.exit_dialog.changes_title'),
                description: __('assistants.builder.exit_dialog.changes_description'),
                keep: __('assistants.builder.exit_dialog.changes_keep'),
                discard: __('assistants.builder.exit_dialog.changes_discard')
            }
            : {
                title: __('assistants.builder.exit_dialog.title'),
                description: __('assistants.builder.exit_dialog.description'),
                keep: __('assistants.builder.exit_dialog.keep'),
                discard: __('assistants.builder.exit_dialog.discard')
            }
    );

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
    title={t.title}
    description={t.description}
    closable={false}
    contentProps={{class: 'exit-draft-dialog-content'}}
>
    {#snippet footer()}
        <Button variant="delete" size="sm" disabled={busy} onclick={onDiscard}>
            {t.discard}
        </Button>
        <Button variant="fill" size="sm" disabled={busy} autofocus onclick={onKeep}>
            {t.keep}
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
