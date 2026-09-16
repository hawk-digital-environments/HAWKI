<!--
  @component Shell of an Admin Workspace: heading, description, feedback,
  the page menu, the create button and the editor and confirmation dialogs of
  its Record Sets. Pages compose AdminSearch, AdminTable or their own
  markup as children.
-->
<script module lang="ts">
    import type { EditorSection } from '../forms/schemas.js';
    import type { AdminRecordSet } from '../recordSet.svelte.js';

    export interface RelatedRecordSet {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        recordSet: AdminRecordSet<any, any, any>;
        editor: EditorSection;
        /** Editor dialog title prefix, e.g. "Tools". */
        title: string;
    }
</script>

<script
    lang="ts"
    generics="Row extends AdminRow, ColumnId extends string, Results extends Record<string, unknown>"
>
    import { onMount, tick, untrack, type Snippet } from 'svelte';
    import Page from '$lib/components/ui/page/Page.svelte';
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useBreakpoint } from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import AdminActionMenu, { type AdminMenuItem } from './AdminActionMenu.svelte';
    import AdminEditor from './AdminEditor.svelte';
    import { adminActionIcons } from '../actionIcons.js';
    import type { WorkspaceId } from '../workspaces.js';
    import type { AdminRow } from '../schemas/admin-content.js';
    import type { AdminAction } from '../recordSet.svelte.js';

    let {
        recordSet,
        workspace,
        pageActions = [],
        menuItems = [],
        hint,
        related = [],
        children
    }: {
        recordSet: AdminRecordSet<Row, ColumnId, Results>;
        /** Page identity for labels, access checks and editor controls. */
        workspace: WorkspaceId | (string & {});
        /** Workspace actions offered in the page menu after "reload"; run through `recordSet.action(item, trigger)`. */
        pageActions?: AdminAction[];
        /** Extra page menu entries after the page actions (pages compute them reactively themselves). */
        menuItems?: AdminMenuItem[];
        /** Explanatory text under the Workspace description. */
        hint?: string;
        related?: RelatedRecordSet[];
        children?: Snippet;
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const breakpoint = useBreakpoint();
    // The Workspace identity of a page never changes, so the registry lookup happens once.
    // svelte-ignore state_referenced_locally
    const entry = app.admin.workspace(workspace);
    // svelte-ignore state_referenced_locally
    if (!entry) throw new Error(`Unknown admin workspace "${workspace}"`);
    const title = $derived(__(entry.title));
    const all = $derived([recordSet, ...related.map((item) => item.recordSet)]);
    /** Checked reactively so revoked permissions hide the Workspace content. */
    const allowed = $derived(app.can('admin.access') && app.can(entry.permission));
    let toolbar = $state<HTMLDivElement>();
    let forbidden = $state<HTMLParagraphElement>();
    const permissionSignature = () => JSON.stringify(
        app.connection.type === 'internal_authenticated' ? [...app.connection.userinfo.permissions].sort() : []
    );
    let previousPermissions = untrack(permissionSignature);
    function invalidate() {
        const hadDialog = all.some((item) => item.dialogOpen);
        for (const item of all) item.invalidate();
        if (hadDialog || !allowed) void tick().then(() => (allowed ? recordSet.restoreFocus() : forbidden)?.focus());
    }
    $effect(() => {
        if (!allowed) untrack(invalidate);
    });
    onMount(() => {
        const disposers = [
            app.events.async.on('connectionRefreshed', async () => {
                const current = permissionSignature();
                const authorizationChanged = !allowed || current !== previousPermissions;
                if (authorizationChanged) {
                    invalidate();
                    if (allowed) for (const item of all) item.notice = __('admin.authorization_changed');
                }
                previousPermissions = current;
                const closed =
                    allowed &&
                    (await Promise.all(
                        all.map((item) => (authorizationChanged ? item.resume() : item.revalidate()))
                    )).some(Boolean);
                if (closed) {
                    await tick();
                    if (!all.some((item) => item.dialogOpen)) recordSet.restoreFocus()?.focus();
                }
            }),
            app.events.async.on('connectionRefreshFailed', () => invalidate()),
            app.events.sync.on('sessionLost', () => invalidate())
        ];
        return () => disposers.forEach((dispose) => dispose());
    });
    const toolbarItems = $derived.by<AdminMenuItem[]>(() => [
        {
            label: __('admin.reload'),
            icon: adminActionIcons.reload,
            disabled: all.some((item) => item.loading),
            run: () => Promise.all(all.map((item) => item.load()))
        },
        ...pageActions.map((item) => ({
            label: __('admin.actions.' + item.id),
            icon: adminActionIcons[item.id],
            destructive: item.destructive,
            run: (trigger: HTMLButtonElement | null) => recordSet.action(item, trigger)
        })),
        ...menuItems
    ]);

    $effect(() => {
        for (const item of all)
            item.focusFallback = () =>
                toolbar?.querySelector<HTMLElement>('button:not(:disabled)') ?? toolbar ?? forbidden ?? null;
    });
</script>

{#if allowed}
    <Page style="grid-template-columns: minmax(0, 1fr); min-width: 0">
        {#snippet header()}
            <PageHeaderBar heading={title}>
                <div
                    class="header-actions"
                    tabindex="-1"
                    bind:this={toolbar}
                >
                    <AdminActionMenu
                        label={__('admin.page_actions', { name: title })}
                        items={toolbarItems}
                        disabled={all.some((item) => item.busy)}
                        compact={breakpoint.is('bpSmAndSmaller')}
                        dialogOpen={all.some((item) => item.dialogOpen)}
                    />
                    {#if recordSet.canCreate}
                        <Button
                            type="button"
                            variant="fill"
                            disabled={recordSet.busy}
                            onclick={(event) => recordSet.edit(null, event.currentTarget)}
                        >
                            {__('admin.create')}
                        </Button>
                    {/if}
                </div>
            </PageHeaderBar>
        {/snippet}
        <div class="workspace">
            <p class="description">{__(entry.description)}</p>
            {#if hint}<p class="hint">{hint}</p>{/if}
            <p
                role="status"
                class="feedback"
            >
                {recordSet.notice}
            </p>
            {#if recordSet.error || recordSet.authorizationDenied}<p
                    role="alert"
                    class="error"
                >
                    {recordSet.authorizationDenied ? __('admin.forbidden') : recordSet.error}
                </p>{/if}
            {#each related as item}
                <p
                    role="status"
                    class="feedback"
                >
                    {item.recordSet.notice}
                </p>
                {#if item.recordSet.error || item.recordSet.authorizationDenied}<p
                        role="alert"
                        class="error"
                    >
                        {item.recordSet.authorizationDenied ? __('admin.forbidden') : item.recordSet.error}
                    </p>{/if}
            {/each}
            {@render children?.()}
        </div>
    </Page>

    {#if recordSet.editor}
        <AdminEditor
            section={workspace}
            fields={recordSet.editor.fields}
            content={recordSet.content}
            row={recordSet.editor.row}
            title={title + ' · ' + __(recordSet.editor.row ? 'admin.edit' : 'admin.create')}
            onSave={(values) => recordSet.save(values)}
            onClose={() => (recordSet.editor = null)}
            restoreFocus={() => recordSet.restoreFocus()}
        />
    {/if}
    <ConfirmDialog
        open={!!recordSet.confirmation}
        title={recordSet.confirmation?.title}
        description={recordSet.confirmation?.description}
        onOpenChange={(open) => {
            if (!open) recordSet.confirmation = null;
        }}
        restoreFocusTo={() => recordSet.restoreFocus()}
        onConfirm={() => recordSet.confirm()}
    />
    {#each related as item}
        {#if item.recordSet.editor}
            <AdminEditor
                section={item.editor}
                fields={item.recordSet.editor.fields}
                content={item.recordSet.content}
                row={item.recordSet.editor.row}
                title={item.title + ' · ' + __(item.recordSet.editor.row ? 'admin.edit' : 'admin.create')}
                onSave={(values) => item.recordSet.save(values)}
                onClose={() => (item.recordSet.editor = null)}
                restoreFocus={() => item.recordSet.restoreFocus()}
            />
        {/if}
        <ConfirmDialog
            open={!!item.recordSet.confirmation}
            title={item.recordSet.confirmation?.title}
            description={item.recordSet.confirmation?.description}
            onOpenChange={(open) => {
                if (!open) item.recordSet.confirmation = null;
            }}
            restoreFocusTo={() => item.recordSet.restoreFocus()}
            onConfirm={() => item.recordSet.confirm()}
        />
    {/each}
{:else}
    <Page title={__('admin.forbidden_title')}><p bind:this={forbidden} tabindex="-1" role="alert">{__('admin.forbidden')}</p></Page>
{/if}

<style>
    .workspace {
        padding: var(--space-6);
        max-width: 100rem;
        margin-inline: auto;
    }
    .description {
        margin-bottom: var(--space-5);
        max-width: 65rem;
        color: var(--color-text-muted);
    }
    .header-actions {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: var(--space-2);
    }
    .hint,
    .feedback {
        font-size: var(--font-size-sm);
        margin-block: var(--space-3);
    }
    .feedback:empty {
        display: none;
    }
    .error {
        border: var(--border);
        padding: var(--space-3);
        border-radius: var(--corner-md);
        margin-block: var(--space-3);
    }
    @media (--bp-md-and-smaller) {
        .workspace {
            padding: var(--space-4);
        }
    }
</style>
