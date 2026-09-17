<!--
  @component Publishing Center: full detail/review page for one assistant.
  Shows everything submitted in the builder, grouped into cards mirroring the
  builder's own categories, plus the review tools: flagging a passage of a
  long-text field, reviewing knowledge files, a collapsed version history, the
  administrative log, and the Approve / Ask for edit / Discard actions.
-->
<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useStore } from '$lib/app/hooks/useStore.svelte.js';
    import { useRouter, type RouteParams } from '$lib/components/ui/routing/index.js';
    import { useToastContext } from '$lib/components/ui/toast/ToastContext.svelte.js';
    import Page from '$lib/components/ui/page/Page.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import AssistantAvatarIcon from '$plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte';
    import StatusPill from '$plugins/assistants/components/status/StatusPill.svelte';
    import AdminDetailCard from '$plugins/assistants/admin/components/AdminDetailCard.svelte';
    import FlaggableField from '$plugins/assistants/admin/components/FlaggableField.svelte';
    import AttachmentReviewRow from '$plugins/assistants/admin/components/AttachmentReviewRow.svelte';
    import FilePreviewPanel from '$plugins/assistants/admin/components/FilePreviewPanel.svelte';
    import ExitReviewDialog from '$plugins/assistants/admin/components/ExitReviewDialog.svelte';
    import { ApiError } from '$plugins/assistants/api/errors';
    import { getAssistant, ASSISTANT_EDIT_INCLUDES } from '$plugins/assistants/api/resources/assistantsClient';
    import {
        deleteAssistantFieldFlag,
        getAssistantFieldFlags,
        getAssistantReviewLogs,
        submitAssistantReview
    } from '$plugins/assistants/admin/api/assistantReviewClient';
    import type { Assistant } from '$plugins/assistants/types/assistant/Assistant';
    import type { AssistantFieldFlag } from '$plugins/assistants/api/schemas/resources/assistant-field-flag.schema';
    import type { AssistantReviewAction, AssistantReviewLog } from '$plugins/assistants/api/schemas/resources/assistant-review-log.schema';
    import type { UploadFile, AttachmentReviewStatus } from '$plugins/assistants/types/UploadFile';

    interface Props {
        params?: RouteParams;
    }
    const { params = {} }: Props = $props();

    const app = useApp();
    const { __ } = useTranslator();
    const router = useRouter();
    const toast = useToastContext();
    const modelStore = useStore('ai-models');
    modelStore.loadData(app);

    const ASSISTANT_ADMIN_INCLUDES = [...ASSISTANT_EDIT_INCLUDES, 'assistant_review', 'assistant_feedback'] as const;

    let assistantId = $derived.by(() => {
        const raw = params?.id;
        return Array.isArray(raw) ? raw[0] : raw;
    });

    let assistant = $state<Assistant | null>(null);
    let flags = $state<AssistantFieldFlag[]>([]);
    let logs = $state<AssistantReviewLog[]>([]);
    let loading = $state(true);
    let error = $state('');

    let previewFile = $state<UploadFile | null>(null);
    let previewOpen = $state(false);

    let actionPending = $state<AssistantReviewAction | null>(null);
    let actionReason = $state('');
    let actionSubmitting = $state(false);

    async function load(id: string): Promise<void> {
        loading = true;
        error = '';
        try {
            const [loadedAssistant, loadedFlags, loadedLogs] = await Promise.all([
                getAssistant(id, { include: [...ASSISTANT_ADMIN_INCLUDES] }),
                getAssistantFieldFlags(id),
                getAssistantReviewLogs(id)
            ]);
            assistant = loadedAssistant;
            flags = loadedFlags;
            logs = loadedLogs;
        } catch (err) {
            error = err instanceof Error ? err.message : String(err);
        } finally {
            loading = false;
        }
    }

    $effect(() => {
        const id = assistantId;
        if (id) void load(id);
    });

    const unresolvedFlags = $derived(flags.filter((f) => !f.resolved));
    const flagsByField = $derived.by(() => {
        const map: Record<string, AssistantFieldFlag[]> = {};
        for (const flag of flags) {
            (map[flag.field] ??= []).push(flag);
        }
        return map;
    });

    const modelLabel = $derived(
        modelStore.models.find((m) => m.model_id === assistant?.model)?.label ?? assistant?.model ?? ''
    );

    function refreshFlags(): void {
        if (assistantId) void getAssistantFieldFlags(assistantId).then((f) => (flags = f));
    }

    function onFileReviewed(uuid: string, status: AttachmentReviewStatus): void {
        if (!assistant) return;
        assistant = {
            ...assistant,
            files: assistant.files?.map((f) => (f.uuid === uuid ? { ...f, reviewStatus: status } : f))
        };
    }

    function openPreview(file: UploadFile): void {
        previewFile = file;
        previewOpen = true;
    }

    function startAction(action: AssistantReviewAction): void {
        actionReason = '';
        actionPending = action;
    }

    async function confirmAction(): Promise<void> {
        if (!actionPending || !assistant?.review) return;
        actionSubmitting = true;
        try {
            await submitAssistantReview(assistant.review.id, actionPending, actionReason.trim() || undefined);
            actionPending = null;
            if (assistantId) await load(assistantId);
        } finally {
            actionSubmitting = false;
        }
    }

    // Exit guard: leaving the page with unresolved flags that were never
    // sent, discarded, or acted on asks what to do with them first — mirrors
    // the assistant builder's own exit guard (see
    // modules/builder/components/ConfirmBuilderExit.svelte).
    type ExitDecision = 'sent' | 'discarded' | 'draft' | 'stay';

    let exitDialogOpen = $state(false);
    let exitBusy = $state(false);
    /** The pending decision's resolver — one shared answer for every navigation that arrives while the dialog is already open. */
    let exitDecide: ((decision: ExitDecision) => void) | null = null;
    let pendingExitDecision: Promise<ExitDecision> | null = null;

    function askExitUser(): Promise<ExitDecision> {
        if (!pendingExitDecision) {
            exitBusy = false;
            exitDialogOpen = true;
            pendingExitDecision = new Promise<ExitDecision>((resolve) => {
                exitDecide = resolve;
            });
        }
        return pendingExitDecision;
    }

    function settleExit(decision: ExitDecision): void {
        exitDialogOpen = false;
        exitBusy = false;
        pendingExitDecision = null;
        const decide = exitDecide;
        exitDecide = null;
        decide?.(decision);
    }

    /** Send: deny the pending review with the flagged feedback as the reason — same effect as the "Ask for edit" action. */
    async function sendFlagsToCreator(): Promise<ExitDecision> {
        if (!assistant?.review) return 'stay';
        exitBusy = true;
        try {
            const reason = unresolvedFlags.map((flag) => `${flag.field}: ${flag.comment}`).join('\n');
            await submitAssistantReview(assistant.review.id, 'denied', reason);
            return 'sent';
        } catch (err) {
            toast.error(__('admin.detail.exit_dialog_send_failed') + ' ' + ApiError.from(err).userMessage);
            return 'stay';
        } finally {
            exitBusy = false;
        }
    }

    /** Discard: permanently remove every unresolved flag; the review itself is left untouched. */
    async function discardUnresolvedFlags(): Promise<ExitDecision> {
        exitBusy = true;
        try {
            await Promise.all(unresolvedFlags.map((flag) => deleteAssistantFieldFlag(flag.id)));
            return 'discarded';
        } catch (err) {
            toast.error(__('admin.detail.exit_dialog_discard_failed') + ' ' + ApiError.from(err).userMessage);
            return 'stay';
        } finally {
            exitBusy = false;
        }
    }

    async function chooseSend(): Promise<void> {
        settleExit(await sendFlagsToCreator());
    }

    async function chooseDiscard(): Promise<void> {
        settleExit(await discardUnresolvedFlags());
    }

    /** Draft: leave everything exactly as it is — flags stay unresolved, the review stays pending, and the Publishing Center table picks up the "draft in progress" marker from that same state. */
    function chooseDraft(): void {
        settleExit('draft');
    }

    // Registered for this component's lifetime; the effect's cleanup
    // unregisters the guard when the page unmounts.
    $effect(() => {
        return router.registerNavigationGuard(async ({ to, from }) => {
            const id = assistantId;
            const detailPath = id ? router.getPath('admin.assistants.detail', { id }) : null;
            // Only real exits of this assistant's detail page need the
            // decision: not entering it, and not navigating within it.
            if (!detailPath || from !== detailPath || to === detailPath) {
                return true;
            }
            if (assistant?.review?.status !== 'pending' || unresolvedFlags.length === 0) {
                return true;
            }
            const decision = await askExitUser();
            return decision !== 'stay';
        });
    });
</script>

<Page title={assistant?.name ?? __('admin.sections.assistants')}>
    {#if loading}
        <p>{__('ui.loading')}</p>
    {:else if error}
        <p role="alert" class="error">{error}</p>
    {:else if assistant}
        <div class="detail-page">
            <header class="detail-header">
                <AssistantAvatarIcon assistantAvatar={assistant.avatar} size="large" />
                <div class="heading">
                    <h1>{assistant.name}</h1>
                    <p class="handle">{assistant.handle ?? '—'}</p>
                    <div class="badges">
                        <StatusPill label={__('admin.values.' + assistant.releaseStage)} tone="info" />
                        {#if assistant.review}
                            <StatusPill
                                label={__('admin.values.' + assistant.review.status)}
                                tone={assistant.review.status === 'approved'
                                    ? 'safe'
                                    : assistant.review.status === 'pending'
                                        ? 'warning'
                                        : 'error'}
                            />
                        {/if}
                    </div>
                    <p class="meta">
                        {__('admin.fields.creator')}: {assistant.creator.displayName}
                        {#if assistant.remixCreator}
                            · {__('admin.fields.based_on')}: {assistant.remixedAssistant?.name ?? ''} ({assistant.remixCreator.displayName})
                        {/if}
                    </p>
                </div>
            </header>

            <AdminDetailCard title={__('admin.detail.general')}>
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="name"
                    label={__('admin.fields.name')}
                    value={assistant.name}
                    selectable={false}
                    flags={flagsByField.name ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="handle"
                    label={__('admin.fields.handle')}
                    value={assistant.handle ?? '—'}
                    selectable={false}
                    flags={flagsByField.handle ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="description"
                    label={__('admin.detail.description')}
                    value={assistant.description || '—'}
                    flags={flagsByField.description ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="detail_description"
                    label={__('admin.detail.detail_description')}
                    value={assistant.detailDescription || '—'}
                    flags={flagsByField.detail_description ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="category"
                    label={__('admin.detail.category')}
                    value={assistant.category?.text ?? '—'}
                    selectable={false}
                    flags={flagsByField.category ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="language"
                    label={__('admin.detail.language')}
                    value={assistant.language ?? '—'}
                    selectable={false}
                    flags={flagsByField.language ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="tags"
                    label={__('admin.detail.tags')}
                    value={assistant.tags.length ? assistant.tags.map((t) => t.text).join(', ') : '—'}
                    selectable={false}
                    flags={flagsByField.tags ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="allow_remix"
                    label={__('admin.detail.allow_remix')}
                    value={__(assistant.allowRemix ? 'admin.yes' : 'admin.no')}
                    selectable={false}
                    flags={flagsByField.allow_remix ?? []}
                    onFlagsChanged={refreshFlags}
                />
            </AdminDetailCard>

            <AdminDetailCard title={__('admin.detail.behaviour')}>
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="system_prompt"
                    label={__('admin.detail.system_prompt')}
                    value={assistant.systemPrompt}
                    flags={flagsByField.system_prompt ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="greeting"
                    label={__('admin.detail.greeting')}
                    value={assistant.greeting}
                    flags={flagsByField.greeting ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="starter_prompts"
                    label={__('admin.detail.starter_prompts')}
                    value={assistant.starterPrompts.length ? assistant.starterPrompts.join(' · ') : '—'}
                    selectable={false}
                    flags={flagsByField.starter_prompts ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="formality"
                    label={__('admin.detail.formality')}
                    value={assistant.formality ?? '—'}
                    selectable={false}
                    flags={flagsByField.formality ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="answer_style"
                    label={__('admin.detail.answer_style')}
                    value={assistant.answerStyle ?? '—'}
                    selectable={false}
                    flags={flagsByField.answer_style ?? []}
                    onFlagsChanged={refreshFlags}
                />
            </AdminDetailCard>

            <AdminDetailCard title={__('admin.detail.knowledge')}>
                {#if assistant.files?.length}
                    {#each assistant.files as file (file.uuid ?? file.name)}
                        <AttachmentReviewRow assistantId={assistant.id ?? ''} {file} onView={openPreview} onReviewed={onFileReviewed} />
                    {/each}
                {:else}
                    <p class="empty">{__('admin.detail.no_files')}</p>
                {/if}
            </AdminDetailCard>

            <AdminDetailCard title={__('admin.detail.model')}>
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="model"
                    label={__('admin.detail.model')}
                    value={modelLabel || '—'}
                    selectable={false}
                    flags={flagsByField.model ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="temp"
                    label={__('admin.detail.temperature')}
                    value={String(assistant.temp)}
                    selectable={false}
                    flags={flagsByField.temp ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="top_p"
                    label={__('admin.detail.top_p')}
                    value={String(assistant.topP)}
                    selectable={false}
                    flags={flagsByField.top_p ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="max_tokens"
                    label={__('admin.detail.max_tokens')}
                    value={String(assistant.maxTokens)}
                    selectable={false}
                    flags={flagsByField.max_tokens ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="tools"
                    label={__('admin.fields.tools')}
                    value={assistant.aiTools?.length ? assistant.aiTools.map((t) => t.name).join(', ') : '—'}
                    selectable={false}
                    flags={flagsByField.tools ?? []}
                    onFlagsChanged={refreshFlags}
                />
                <FlaggableField
                    assistantId={assistant.id ?? ''}
                    field="capabilities"
                    label={__('admin.detail.capabilities')}
                    value={assistant.capabilities?.length ? assistant.capabilities.join(', ') : '—'}
                    selectable={false}
                    flags={flagsByField.capabilities ?? []}
                    onFlagsChanged={refreshFlags}
                />
            </AdminDetailCard>

            <details class="versions">
                <summary>{__('admin.detail.versions')} ({assistant.versions.length})</summary>
                {#if assistant.versions.length}
                    <ul>
                        {#each assistant.versions as version (version.id)}
                            <li>
                                <strong>v{version.version}</strong>
                                <span class="meta">{new Date(version.createdAt).toLocaleString()}</span>
                                <p>{version.text}</p>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="empty">{__('admin.detail.no_versions')}</p>
                {/if}
            </details>

            <AdminDetailCard title={__('admin.detail.admin_log')}>
                {#if logs.length}
                    <ul class="log-list">
                        {#each logs as log (log.id)}
                            <li>
                                <StatusPill
                                    label={__('admin.values.' + log.action)}
                                    tone={log.action === 'approved' ? 'safe' : 'error'}
                                />
                                <span class="who">{log.adminName}</span>
                                <span class="meta">{new Date(log.createdAt).toLocaleString()}</span>
                                {#if log.reason}<p class="reason">{log.reason}</p>{/if}
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="empty">{__('admin.detail.no_log')}</p>
                {/if}
            </AdminDetailCard>

            {#if assistant.review}
                <div class="action-bar">
                    {#if unresolvedFlags.length}
                        <p class="flag-warning">{__('admin.detail.unresolved_flags_warning', { count: String(unresolvedFlags.length) })}</p>
                    {/if}
                    <div class="actions">
                        <Button variant="stroke" onclick={() => startAction('denied')}>{__('admin.detail.ask_for_edit')}</Button>
                        <Button variant="delete" onclick={() => startAction('blocked')}>{__('admin.detail.discard')}</Button>
                        <Button variant="fill" disabled={unresolvedFlags.length > 0} onclick={() => startAction('approved')}>
                            {__('admin.detail.approve')}
                        </Button>
                    </div>
                </div>
            {/if}
        </div>
    {/if}
</Page>

<FilePreviewPanel file={previewFile} open={previewOpen} onOpenChange={(open) => (previewOpen = open)} />

<Dialog
    open={!!actionPending}
    onOpenChange={(open) => { if (!open) actionPending = null; }}
    title={actionPending ? __('admin.detail.' + (actionPending === 'approved' ? 'approve' : actionPending === 'denied' ? 'ask_for_edit' : 'discard')) : ''}
>
    {#snippet children()}
        <Textarea
            bind:value={actionReason}
            ariaLabel={__('admin.detail.reason_label')}
            placeholder={__('admin.detail.reason_placeholder')}
        />
    {/snippet}
    {#snippet footer()}
        <Button variant="ghost" onclick={() => (actionPending = null)}>{__('admin.cancel')}</Button>
        <Button variant="fill" disabled={actionSubmitting} onclick={confirmAction}>{__('admin.detail.confirm')}</Button>
    {/snippet}
</Dialog>

<ExitReviewDialog
    bind:open={exitDialogOpen}
    busy={exitBusy}
    flagCount={unresolvedFlags.length}
    onSend={chooseSend}
    onDiscard={chooseDiscard}
    onDraft={chooseDraft}
    onDismiss={() => settleExit('stay')}
/>

<style>
    .detail-page {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        max-width: 60rem;
        margin-inline: auto;
        padding: var(--space-6) var(--space-4);
    }
    .detail-header {
        display: flex;
        align-items: flex-start;
        gap: var(--space-4);
    }
    .heading h1 {
        margin: 0;
        font-size: var(--font-size-lg);
    }
    .handle {
        margin: 0;
        color: var(--color-text-muted);
        font-family: monospace;
        font-size: var(--font-size-xs);
    }
    .badges {
        display: flex;
        gap: var(--space-2);
        margin-block: var(--space-2);
    }
    .meta {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        margin: 0;
    }
    .empty {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }
    .versions {
        border: var(--border);
        border-radius: var(--corner-lg);
        padding: var(--space-4) var(--space-5);
        background: var(--color-surface-raised);
    }
    .versions summary {
        cursor: pointer;
        font-weight: var(--font-weight-medium, 500);
    }
    .versions ul {
        list-style: none;
        margin: var(--space-3) 0 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }
    .versions li p {
        margin: var(--space-1) 0 0;
        font-size: var(--font-size-sm);
    }
    .log-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }
    .log-list li {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-2);
        border-bottom: var(--border);
        padding-bottom: var(--space-2);
    }
    .log-list .reason {
        flex-basis: 100%;
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
    .who {
        font-size: var(--font-size-sm);
    }
    .action-bar {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        align-items: flex-end;
        border-top: var(--border);
        padding-top: var(--space-4);
    }
    .flag-warning {
        color: var(--color-error);
        font-size: var(--font-size-sm);
        margin: 0;
    }
    .actions {
        display: flex;
        gap: var(--space-2);
    }
    .error {
        color: var(--color-error);
    }
</style>
