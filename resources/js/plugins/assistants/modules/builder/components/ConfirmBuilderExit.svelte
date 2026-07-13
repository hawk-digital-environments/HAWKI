<!--
  @component Draft-exit guard for the assistant builder. While the assistant
  being edited is still an unreleased draft, any navigation leaving the
  builder's routes is intercepted (via the router's navigation-guard API) and
  the user is asked to keep the draft (saved, visible under "Entwürfe"),
  discard it (permanently deleted), or continue editing. Both exits redirect
  to the drafts overview — as the guard's verdict, so the router performs the
  redirect itself; this component never navigates (and never has to suppress
  its own guard to do so).

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
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {ReleaseMode} from '$plugins/assistants/types/assistant/ReleaseMode';
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

    /** Whether the builder session holds an unreleased draft whose exit
     *  needs confirming — the state half of the decision; the router guard
     *  adds the path-aware half. */
    function shouldConfirmExit(): boolean {
        // A discarded session has nothing left to decide about.
        if (builder.isDiscarded) {
            return false;
        }
        // Not a session still initializing, and not an already-released
        // assistant — private and up is a saved, complete record whose
        // edits autosave.
        if (builder.loading || builder.mode === "init" || !builder.draft.id) {
            return false;
        }
        return builder.draft.releaseStage === ReleaseMode.DRAFT;
    }

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

    /** Discard: permanently delete the draft. A failed delete settles as
     *  "stay" — the draft still exists, so the user must not be navigated
     *  away from an assistant they meant to delete. */
    async function discardDraft(): Promise<ExitDecision> {
        exitBusy = true;
        try {
            await builder.discardDraft();
        } catch (err) {
            const apiErr = ApiError.from(err);
            toast.error(`${__('assistants.builder.exit_dialog.discard_failed')} ${apiErr.userMessage}`);
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
        settleExit(await discardDraft());
    }

    // Registered for this component's lifetime: $effect's cleanup return
    // unregisters the guard when the builder layout unmounts.
    $effect(() => {
        return router.registerNavigationGuard(async ({to, from}) => {
            // Only real exits of the builder need the decision: not
            // entering it, not navigating within it, not a session without
            // an unreleased draft (see shouldConfirmExit).
            if (!isBuilderPath(from) || isBuilderPath(to) || !shouldConfirmExit()) {
                return true;
            }
            const decision = await askUser();
            if (decision === 'stay') {
                return false;
            }
            // Both exits land on the drafts overview — the kept or discarded
            // draft is what the user will want to see next — rather than
            // wherever the intercepted navigation was headed (usually the
            // store). `replace`: the intercepted history entry never
            // rendered anything, so back from the drafts skips over it and
            // lands on the builder — the same history shape a veto's
            // pull-back would have left.
            return {to: 'assistants.dashboard.drafts', replace: true};
        });
    });
</script>

<ExitDraftDialog
    bind:open={exitDialogOpen}
    busy={exitBusy}
    onKeep={chooseKeep}
    onDiscard={chooseDiscard}
    onDismiss={() => settleExit('stay')}
/>
