<!--
  @component Exit guard for the assistant builder. Any navigation leaving the
  builder's routes is intercepted (via the router's navigation-guard API) and,
  when the session has something undecided, the user is asked what should
  happen to their work before they go.

  What "discard" means depends on where the session came from — see
  `BuilderContext.ownsDraftRecord`:

  - **create / remix** mint a brand-new record. Leaving without a decision
    would strand it in the user's drafts, so the exit is always confirmed and
    discarding *deletes* the record.
  - **edit** opened an assistant that already existed (possibly a published
    one), so deleting it is never on the table. The exit is only confirmed
    when this session actually changed something, and discarding *reverts*
    those changes — the assistant goes back to how it was when the builder
    opened it (`BuilderContext.revertToSessionOrigin`).

  The guard never redirects: it only decides whether the navigation may
  proceed. Where "leaving the builder" goes is the sidebar's "Zurück" row to
  choose (it returns to the page the builder was opened from), which keeps a
  deliberate navigation elsewhere — the module selector, say — going where
  the user actually pointed it.

  Router guards can only cover in-app navigation. Hard document exits —
  editing the address bar, reload, tab close — are deliberately not
  confirmed: they tear this component down before any JS runs, and the
  draft autosaves server-side anyway, so they act as an implicit "keep".

  Must live inside the builder layout's subtree: it reads the BuilderContext
  owned by `pages/advanced/layout.svelte` via useBuilderContext(). The guard
  is registered for exactly as long as this component — and therefore the
  builder session — is mounted. Renders nothing except the decision dialog
  itself (which portals when open).
-->
<script lang="ts">
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import {clearBuilderReturnPath} from '$plugins/assistants/modules/builder/contexts/builderReturn.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {ApiError} from '$plugins/assistants/api/errors';
    import ExitDraftDialog from '$plugins/assistants/modules/builder/components/ExitDraftDialog.svelte';

    const builder = useBuilderContext();
    const router = useRouter();
    const toast = useToastContext();
    const {__} = useTranslator();

    /** Path prefix every builder route lives under, e.g. `/new/assistants/builder/advanced`. */
    const builderBasePath = router.getPath('assistants.builder.index');

    function isBuilderPath(path: string): boolean {
        return path === builderBasePath || path.startsWith(builderBasePath + '/');
    }

    /** Whether the builder session has something undecided whose exit needs
     *  confirming — the state half of the decision; the router guard adds the
     *  path-aware half. */
    function shouldConfirmExit(): boolean {
        // Nothing left to decide: the record is already gone, or the user
        // committed this session's outcome from the Publish tab.
        if (builder.isDiscarded || builder.isCommitted) {
            return false;
        }
        // Not a session still initializing.
        if (builder.loading || builder.mode === "init" || !builder.draft.id) {
            return false;
        }
        // create / remix minted the record; leaving without a decision would
        // strand it in the user's drafts.
        if (builder.ownsDraftRecord) {
            return true;
        }
        // edit opened an assistant that already existed — only worth asking
        // about when this session actually changed it.
        return builder.hasSessionChanges;
    }

    /** Which decision the user is being asked to make — see the component
     *  comment: a new record can be thrown away, an existing one can only
     *  have this session's changes rolled back. */
    const dialogVariant = $derived(builder.ownsDraftRecord ? 'draft' : 'changes');

    /** The user's answer once the dialog (and any keep/discard work behind
     *  it) has settled: leave having kept the draft, leave having discarded
     *  it, or stay in the builder. */
    type ExitDecision = 'keep' | 'discard' | 'stay';

    let exitDialogOpen = $state(false);
    let exitBusy = $state(false);
    /** The pending decision's resolver — one shared answer for every
     *  navigation that arrives while the dialog is already open. */
    let exitDecision: ((decision: ExitDecision) => void) | null = null;
    let pendingDecision: Promise<ExitDecision> | null = null;

    function askUser(): Promise<ExitDecision> {
        if (!pendingDecision) {
            exitBusy = false;
            exitDialogOpen = true;
            pendingDecision = new Promise<ExitDecision>((resolve) => {
                exitDecision = resolve;
            });
        }
        return pendingDecision;
    }

    function settleExit(decision: ExitDecision): void {
        exitDialogOpen = false;
        exitBusy = false;
        pendingDecision = null;
        const decide = exitDecision;
        exitDecision = null;
        decide?.(decision);
    }

    /** Keep: flush pending edits first. A failed save settles as "stay" —
     *  turning "keep" into silently lost edits is worse than staying. */
    async function keepDraft(): Promise<ExitDecision> {
        exitBusy = true;
        try {
            await builder.flushSave();
        } finally {
            exitBusy = false;
        }
        if (builder.isDirty) {
            // The autosave pipeline already reported why it couldn't save
            // (toast / inline field error).
            toast.error(__('assistants.builder.exit_dialog.keep_failed'));
            return 'stay';
        }
        return 'keep';
    }

    /**
     * Discard: for a record this session minted, delete it permanently; for
     * an existing assistant, roll this session's changes back instead.
     *
     * Either failure settles as "stay" — the record still exists (or still
     * carries the unwanted changes), so the user must not be navigated away
     * believing it was dealt with.
     */
    async function discardSession(): Promise<ExitDecision> {
        exitBusy = true;
        try {
            if (builder.ownsDraftRecord) {
                await builder.discardDraft();
            } else {
                await builder.revertToSessionOrigin();
                if (builder.isDirty) {
                    // The save pipeline already reported why it couldn't
                    // write (toast / inline field error).
                    toast.error(__('assistants.builder.exit_dialog.revert_failed'));
                    return 'stay';
                }
            }
        } catch (err) {
            const apiErr = ApiError.from(err);
            const message = builder.ownsDraftRecord
                ? __('assistants.builder.exit_dialog.discard_failed')
                : __('assistants.builder.exit_dialog.revert_failed');
            toast.error(`${message} ${apiErr.userMessage}`);
            return 'stay';
        } finally {
            exitBusy = false;
        }
        return 'discard';
    }

    /** Dialog wiring: a button settles the shared decision with the outcome
     *  of its work; dismissing (Escape / outside click) settles "stay". */
    async function chooseKeep(): Promise<void> {
        settleExit(await keepDraft());
    }

    async function chooseDiscard(): Promise<void> {
        settleExit(await discardSession());
    }

    // Registered for this component's lifetime: $effect's cleanup return
    // unregisters the guard when the builder layout unmounts.
    $effect(() => {
        return router.registerNavigationGuard(async ({to, from}) => {
            // Only real exits of the builder need the decision: not entering
            // it, and not navigating within it.
            if (!isBuilderPath(from) || isBuilderPath(to)) {
                return true;
            }
            if (shouldConfirmExit()) {
                const decision = await askUser();
                if (decision === 'stay') {
                    return false;
                }
            }
            // The session is over: the origin it remembered belongs to it and
            // must not be inherited by the next one. Whoever started this
            // navigation already resolved where it goes (the sidebar's
            // "Zurück" row reads the origin itself), so the guard just lets
            // it through rather than redirecting somewhere of its own.
            clearBuilderReturnPath();
            return true;
        });
    });
</script>

<ExitDraftDialog
    bind:open={exitDialogOpen}
    busy={exitBusy}
    variant={dialogVariant}
    onKeep={chooseKeep}
    onDiscard={chooseDiscard}
    onDismiss={() => settleExit('stay')}
/>
