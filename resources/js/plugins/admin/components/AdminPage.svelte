<!--
  @component Shell of an admin section page: heading, description, feedback,
  the page menu, the create button and the editor and confirmation dialogs of
  the page's workspace. Pages compose AdminSearch, AdminTable or their own
  markup as children.
-->
<script
    lang="ts"
    generics="Results extends Record<string, unknown>"
>
    import type { Snippet } from 'svelte';
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
    import type { AdminAction, AdminWorkspace } from '../workspace.svelte.js';

    let {
        workspace,
        section,
        pageActions = [],
        menuItems = [],
        hint,
        children
    }: {
        workspace: AdminWorkspace<Results>;
        /** Page identity for labels, access checks and editor controls. */
        section: SectionId;
        /** Section actions offered in the page menu after "reload"; run through `workspace.action(item, trigger)`. */
        pageActions?: AdminAction[];
        /** Extra page menu entries after the page actions (pages compute them reactively themselves). */
        menuItems?: AdminMenuItem[];
        /** Explanatory text under the section description. */
        hint?: string;
        children?: Snippet;
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const breakpoint = useBreakpoint();
    const title = $derived(__('admin.sections.' + section));
    /** The user may see this section; checked reactively so revoked permissions hide the content. */
    const allowed = $derived(
        app.can('admin.access') && app.can(sections.find((item) => item.id === section)!.permission)
    );
    let toolbar = $state<HTMLDivElement>();
    const toolbarItems = $derived.by<AdminMenuItem[]>(() => [
        {
            label: __('admin.reload'),
            icon: adminActionIcons.reload,
            disabled: workspace.loading,
            run: () => workspace.load()
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
        workspace.focusFallback = () => toolbar?.querySelector<HTMLElement>('button') ?? null;
    });
</script>

{#if allowed}
    <Page style="grid-template-columns: minmax(0, 1fr); min-width: 0">
        {#snippet header()}
            <PageHeaderBar heading={title}>
                <div
                    class="header-actions"
                    bind:this={toolbar}
                >
                    <AdminActionMenu
                        label={__('admin.page_actions', { name: title })}
                        items={toolbarItems}
                        disabled={workspace.busy}
                        compact={breakpoint.is('bpSmAndSmaller')}
                        dialogOpen={workspace.dialogOpen}
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
            {#if workspace.error}<p
                    role="alert"
                    class="error"
                >
                    {workspace.error}
                </p>{/if}
            {@render children?.()}
        </div>
    </Page>

    {#if workspace.editor}
        <AdminEditor
            {section}
            fields={workspace.editor.fields}
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
{:else}
    <Page title={__('admin.forbidden_title')}><p>{__('admin.forbidden')}</p></Page>
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
