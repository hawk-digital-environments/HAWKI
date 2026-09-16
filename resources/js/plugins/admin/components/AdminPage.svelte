<!--
  @component Shell of an admin section page: heading, description, feedback,
  the page menu, the create button and the editor and confirmation dialogs of
  the page's workspace. Pages compose AdminSearch, AdminTable or their own
  markup as children.
-->
<script module lang="ts">
    import type { EditorSection } from '../forms/schemas.js';
    import type { AdminWorkspace } from '../workspace.svelte.js';

    export interface RelatedWorkspace {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        workspace: AdminWorkspace<any, any, any>;
        section: EditorSection;
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
    import { sections, type SectionId } from '../sections.js';
    import type { AdminRow } from '../schemas/admin-content.js';
    import type { AdminAction } from '../workspace.svelte.js';

    let {
        workspace,
        section,
        pageActions = [],
        menuItems = [],
        hint,
        related = [],
        children
    }: {
        workspace: AdminWorkspace<Row, ColumnId, Results>;
        /** Page identity for labels, access checks and editor controls. */
        section: SectionId;
        /** Section actions offered in the page menu after "reload"; run through `workspace.action(item, trigger)`. */
        pageActions?: AdminAction[];
        /** Extra page menu entries after the page actions (pages compute them reactively themselves). */
        menuItems?: AdminMenuItem[];
        /** Explanatory text under the section description. */
        hint?: string;
        related?: RelatedWorkspace[];
        children?: Snippet;
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const breakpoint = useBreakpoint();
    const title = $derived(__('admin.sections.' + section));
    const all = $derived([workspace, ...related.map((item) => item.workspace)]);
    /** The user may see this section; checked reactively so revoked permissions hide the content. */
    const allowed = $derived(
        app.can('admin.access') && app.can(sections.find((item) => item.id === section)!.permission)
    );
    let toolbar = $state<HTMLDivElement>();
    let forbidden = $state<HTMLParagraphElement>();
    const permissionSignature = () => JSON.stringify(
        app.connection.type === 'internal_authenticated' ? [...app.connection.userinfo.permissions].sort() : []
    );
    let previousPermissions = untrack(permissionSignature);
    function invalidate() {
        const hadDialog = all.some((item) => item.dialogOpen);
        for (const item of all) item.invalidate();
        if (hadDialog || !allowed) void tick().then(() => (allowed ? workspace.restoreFocus() : forbidden)?.focus());
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
                    if (!all.some((item) => item.dialogOpen)) workspace.restoreFocus()?.focus();
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
            run: (trigger: HTMLButtonElement | null) => workspace.action(item, trigger)
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
                    {#if workspace.canCreate}
                        <Button
                            type="button"
                            variant="fill"
                            disabled={workspace.busy}
                            onclick={(event) => workspace.edit(null, event.currentTarget)}
                        >
                            {__('admin.create')}
                        </Button>
                    {/if}
                </div>
            </PageHeaderBar>
        {/snippet}
        <div class="workspace">
            <p class="description">{__('admin.descriptions.' + section)}</p>
            {#if hint}<p class="hint">{hint}</p>{/if}
            <p
                role="status"
                class="feedback"
            >
                {workspace.notice}
            </p>
            {#if workspace.error || workspace.authorizationDenied}<p
                    role="alert"
                    class="error"
                >
                    {workspace.authorizationDenied ? __('admin.forbidden') : workspace.error}
                </p>{/if}
            {#each related as item}
                <p
                    role="status"
                    class="feedback"
                >
                    {item.workspace.notice}
                </p>
                {#if item.workspace.error || item.workspace.authorizationDenied}<p
                        role="alert"
                        class="error"
                    >
                        {item.workspace.authorizationDenied ? __('admin.forbidden') : item.workspace.error}
                    </p>{/if}
            {/each}
            {@render children?.()}
        </div>
    </Page>

    {#if workspace.editor}
        <AdminEditor
            {section}
            fields={workspace.editor.fields}
            content={workspace.content}
            row={workspace.editor.row}
            title={title + ' · ' + __(workspace.editor.row ? 'admin.edit' : 'admin.create')}
            onSave={(values) => workspace.save(values)}
            onClose={() => (workspace.editor = null)}
            restoreFocus={() => workspace.restoreFocus()}
        />
    {/if}
    <ConfirmDialog
        open={!!workspace.confirmation}
        title={workspace.confirmation?.title}
        description={workspace.confirmation?.description}
        onOpenChange={(open) => {
            if (!open) workspace.confirmation = null;
        }}
        restoreFocusTo={() => workspace.restoreFocus()}
        onConfirm={() => workspace.confirm()}
    />
    {#each related as item}
        {#if item.workspace.editor}
            <AdminEditor
                section={item.section}
                fields={item.workspace.editor.fields}
                content={item.workspace.content}
                row={item.workspace.editor.row}
                title={item.title + ' · ' + __(item.workspace.editor.row ? 'admin.edit' : 'admin.create')}
                onSave={(values) => item.workspace.save(values)}
                onClose={() => (item.workspace.editor = null)}
                restoreFocus={() => item.workspace.restoreFocus()}
            />
        {/if}
        <ConfirmDialog
            open={!!item.workspace.confirmation}
            title={item.workspace.confirmation?.title}
            description={item.workspace.confirmation?.description}
            onOpenChange={(open) => {
                if (!open) item.workspace.confirmation = null;
            }}
            restoreFocusTo={() => item.workspace.restoreFocus()}
            onConfirm={() => item.workspace.confirm()}
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
