<!--
  @component Shows the typed response of a dialog action (`{dialog: true}`) kept in
  `recordSet.results`. The action id determines the snippet response type.

  Usage:
    <AdminResultDialog {recordSet} action="discover">
        {#snippet children(response, id)}...{/snippet}
    </AdminResultDialog>
-->
<script
    lang="ts"
    generics="Row extends AdminRow, ColumnId extends string, Results extends Record<string, unknown>, Id extends keyof Results & string"
>
    import type { Snippet } from 'svelte';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AdminRow } from '../schemas/admin-content.js';
    import type { AdminRecordSet } from '../recordSet.svelte.js';

    const { __ } = useTranslator();
    let {
        recordSet,
        action,
        title = __('admin.action_result'),
        children
    }: {
        recordSet: AdminRecordSet<Row, ColumnId, Results>;
        action: Id;
        /** Defaults to `__('admin.action_result')`. */
        title?: string;
        children: Snippet<[NoInfer<Results[Id]>, string | null]>;
    } = $props();
    const result = $derived(recordSet.results[action]);
    let trigger: HTMLElement | null = null;
    $effect(() => {
        if (result) trigger = result.trigger;
    });
</script>

<Dialog
    open={!!result}
    {title}
    onOpenChange={(open) => {
        if (!open) recordSet.closeResult(action);
    }}
    contentProps={{
        onCloseAutoFocus: (event) => {
            const target = recordSet.restoreFocus(trigger);
            if (target) {
                event.preventDefault();
                target.focus({ preventScroll: true });
            }
        }
    }}
>
    {#if result}{@render children(result.response, result.id)}{/if}
</Dialog>
