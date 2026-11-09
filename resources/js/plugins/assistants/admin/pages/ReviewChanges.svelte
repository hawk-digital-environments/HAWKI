<!--
  @component Review step 1 "Changes": the changed-key chips (clickable,
  they jump to the builder step containing the field), a read-only walk
  through the builder's step layouts (a copy of their field sets over the
  review target's own wire data — see ReadonlySteps), and the published
  version history with the rollback.
-->
<script lang="ts">
    import type {RouteProps} from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import StatusPill from '$plugins/assistants/components/status/StatusPill.svelte';
    import {formatDate} from '$lib/utils/date.js';
    import {ApiError} from '$plugins/assistants/api/errors.js';
    import {rollbackAssistant} from '$plugins/assistants/admin/api/reviewClient.js';
    import {DRAFT_VERSION} from '$plugins/assistants/admin/api/schemas.js';
    import {fieldLabel} from '$plugins/assistants/admin/api/fields.js';
    import {useReviewDetailContext} from '$plugins/assistants/admin/contexts/reviewDetailContext.svelte.js';
    import ReadonlySteps from '$plugins/assistants/admin/components/readonlySteps/ReadonlySteps.svelte';
    import VersionTimeline from '$plugins/assistants/modules/dashboard/components/versionTimeline/VersionTimeline.svelte';
    import VersionCard from '$plugins/assistants/modules/dashboard/components/versionTimeline/VersionCard.svelte';

    const {__} = useTranslator();
    const toast = useToastContext();
    const review = useReviewDetailContext();

    const target = $derived(review.target.value);
    const versions = $derived(target?.assistant_versions ?? []);
    const draft = $derived(versions.find(version => String(version.version) === DRAFT_VERSION) ?? null);
    // Oldest first — with the pending draft on top, the history reads as one
    // ascending line: 0.0, 1.0, 2.0 …
    const published = $derived(
        versions
            .filter(version => Number(version.version) > 0)
            .sort((a, b) => Number(a.version) - Number(b.version))
    );
    // The newest published row is the live baseline — rolling back to it is a
    // no-op (or a mislabeled "discard working edits"), so it gets no button.
    const latestVersion = $derived(published.at(-1) ?? null);
    const changedKeys = $derived(draft?.changed_keys ?? []);

    // --- Read-only builder steps ---------------------------------------
    // A copy of the builder sections' field sets over the review target's
    // own wire data — freely styleable, no builder imports.
    let step = $state(0);

    /** Which builder step a changed field lives on — chips jump there. */
    const KEY_TO_STEP: Record<string, number> = {
        name: 0, handle: 0, description: 0, detail_description: 0, category_id: 0,
        tag_ids: 0, assistant_tags: 0, avatar_id: 0,
        model: 1, max_tokens: 1, temp: 1, top_p: 1, allow_model_select: 1,
        system_prompt: 2, greeting: 2, user_prompts: 2, assistant_user_prompts: 2, setting_values: 2,
        attachment_ids: 3, tool_ids: 3, ai_tools: 3
    };

    function jumpToKey(key: string): void {
        const next = KEY_TO_STEP[key];
        if (next !== undefined) step = next;
    }

    /** The timeline rows: the working draft first, then published versions, newest first. */
    const timelineRows = $derived.by(() => {
        const rows: Array<{
            key: string;
            version: string;
            date: string | null;
            changedKeys: string[] | null;
            note: string | null;
            isCreation: boolean;
            rollbackTarget: number | null;
        }> = [];

        if (draft) {
            rows.push({
                key: draft.id,
                version: draft.version,
                date: draft.created_at ?? null,
                changedKeys: (draft.changed_keys ?? []).map(key => fieldLabel(key, __)),
                note: draft.text || null,
                isCreation: false,
                rollbackTarget: null
            });
        }

        for (let index = 0; index < published.length; index++) {
            const version = published[index];
            rows.push({
                key: version.id,
                version: version.version,
                date: version.created_at ?? null,
                changedKeys: (version.changed_keys ?? []).map(key => fieldLabel(key, __)),
                note: version.text || null,
                isCreation: 0 === index,
                // The newest published row is the live baseline — no rollback.
                rollbackTarget: version.id === latestVersion?.id ? null : Number(version.version)
            });
        }

        return rows;
    });

    let rollbackVersion = $state<number | null>(null);
    let rollingBack = $state(false);

    /**
     * Whether a rollback here runs as a submission (public assistant or a
     * review pending) instead of applying immediately — mirrors the backend's
     * own predicate, so dialog text and toast match what actually happens.
     */
    const rollbackSubmitsReview = $derived(
        target?.release_stage === 'organizational'
        || target?.release_stage === 'federated'
        || target?.assistant_review?.status === 'pending'
    );

    async function doRollback(): Promise<void> {
        // Captured before the first await: the dialog's close handler resets
        // `rollbackVersion` while the request is in flight.
        const version = rollbackVersion;
        const submitsReview = rollbackSubmitsReview;
        if (version === null || !target) return;
        rollingBack = true;
        try {
            await rollbackAssistant(target.id, version);
            toast.success(__(submitsReview
                ? 'assistants.admin.detail.toast.rollback_submitted'
                : 'assistants.admin.detail.toast.rolled_back', {version: version.toFixed(1)}));
            rollbackVersion = null;
            await review.load(target.id);
        } catch (err) {
            toast.error(`${__('assistants.admin.detail.toast.failed')} ${ApiError.from(err).userMessage}`);
        } finally {
            rollingBack = false;
        }
    }
</script>

<div class="grid">
    <article class="card">
        {#if draft}
            {#if changedKeys.length}
                <div class="changed-block">
                    <p class="u-label">{__('assistants.admin.detail.changed_keys')}</p>
                    <div class="chips">
                        {#each changedKeys as key (key)}
                            {#if KEY_TO_STEP[key] !== undefined}
                                <button
                                    type="button"
                                    class="chip-button"
                                    title={__('assistants.admin.detail.chip_jump_hint')}
                                    onclick={() => jumpToKey(key)}
                                >
                                    <StatusPill tone="warning" label={fieldLabel(key, __)}/>
                                </button>
                            {:else}
                                <StatusPill tone="warning" label={fieldLabel(key, __)}/>
                            {/if}
                        {/each}
                    </div>
                </div>
            {/if}

            {#if target}
                <ReadonlySteps {target} bind:step changedKeys={changedKeys}/>
            {/if}
        {:else}
            <p class="meta">{__('assistants.admin.detail.no_draft')}</p>
        {/if}
    </article>

    <article class="card">
        <h2>{__('assistants.detail.history_title')}</h2>
        {#if timelineRows.length > 0}
            <VersionTimeline>
                {#each timelineRows as row (row.key)}
                    <VersionCard
                        version={row.version}
                        date={row.date ? formatDate(row.date) : '—'}
                        changedKeys={row.changedKeys}
                        note={row.note ?? undefined}
                        isCreation={row.isCreation}
                    >
                        {#snippet action()}
                            {#if row.rollbackTarget !== null}
                                <Button
                                    variant="stroke"
                                    size="sm"
                                    disabled={rollingBack}
                                    onclick={() => {
                                        rollbackVersion = row.rollbackTarget;
                                    }}
                                >
                                    {__('assistants.admin.detail.rollback')}
                                </Button>
                            {/if}
                        {/snippet}
                    </VersionCard>
                {/each}
            </VersionTimeline>
        {:else}
            <p class="meta">{__('assistants.admin.detail.no_versions')}</p>
        {/if}
    </article>
</div>

<ConfirmDialog
    open={rollbackVersion !== null}
    onOpenChange={open => {
        if (!open) rollbackVersion = null;
    }}
    title={__('assistants.admin.detail.rollback_confirm_title', {version: rollbackVersion === null ? '' : rollbackVersion.toFixed(1)})}
    description={__(
        rollbackSubmitsReview
            ? 'assistants.admin.detail.rollback_submit_description'
            : 'assistants.admin.detail.rollback_confirm_description',
        {version: rollbackVersion === null ? '' : rollbackVersion.toFixed(1)}
    )}
    okLabel={__('assistants.admin.detail.rollback')}
    busy={rollingBack}
    onConfirm={() => void doRollback()}
/>

<style>
    .grid {
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
        gap: var(--space-5);
    }

    @media (--bp-md-and-smaller) {
        .grid {
            grid-template-columns: 1fr;
        }
    }

    .card {
        border: var(--border);
        border-radius: var(--corner-lg);
        background: var(--color-surface-raised);
        padding: var(--space-4);
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }

    h2 {
        font-size: var(--font-size-md);
        margin: 0 0 var(--space-3);
    }

    .meta {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    .changed-block {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .chips {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
    }

    /* The chip visual is StatusPill's warning tone; the button wraps it for
       the open-and-scroll interaction (hover bumps the pill's recipe). */
    .chip-button {
        font: inherit;
        border: 0;
        padding: 0;
        background: none;
        cursor: pointer;
    }

    .chip-button:hover :global(.status-pill) {
        background: color-mix(in oklch, var(--color-warning) 24%, transparent);
    }

    .chip-button:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
        border-radius: var(--corner-full);
    }
</style>
