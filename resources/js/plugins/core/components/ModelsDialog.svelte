<!--
  @component Model showcase as a modal. Renders every available AI model as a
  bordered `ModelCard`, grouped by provider. Mounted by `AppSidebar`, opened
  via `modelsRequested` from the profile dropdown and the "all models" footer
  link in `ModelPickerV2`.
-->
<script lang="ts">
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import ModelCard from '$plugins/core/components/ModelCard.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** Whether the dialog is open. Supports bind:open. */
        open?: boolean;
    }

    let {open = $bindable(false)}: Props = $props();

    const aiModelStore = useStore('ai-models');
    const {__} = useTranslator();

    const OTHER_GROUP = '__other__';

    // Models grouped by provider, in API order; providerless models share one
    // trailing "other" group.
    const groups = $derived.by(() => {
        const map = new Map<string, {label: string; models: typeof aiModelStore.models}>();
        for (const model of aiModelStore.models) {
            const id = model.provider?.provider_id ?? OTHER_GROUP;
            if (!map.has(id)) {
                map.set(id, {label: model.provider?.name ?? __('chat.composer.modelPicker.otherProvider'), models: []});
            }
            map.get(id)!.models.push(model);
        }
        return [...map.entries()];
    });
</script>

<Dialog
    {open}
    onOpenChange={isOpen => open = isOpen}
    title={__('ai.model.page.title')}
    contentProps={{class: 'models-dialog-content'}}
    headerProps={{class: 'models-dialog-header'}}
>
    <div class="models-body">
        {#if groups.length === 0}
            <p class="models-empty">{__('ai.model.page.empty')}</p>
        {:else}
            {#each groups as [providerId, group] (providerId)}
                <section class="models-group">
                    <h2>{group.label}</h2>
                    <div class="models-grid">
                        {#each group.models as model (model.model_id)}
                            <ModelCard {model} bordered/>
                        {/each}
                    </div>
                </section>
            {/each}
        {/if}
    </div>
</Dialog>

<style>
    :global(.models-dialog-content.models-dialog-content) {
        width: min(70rem, calc(100vw - 2 * var(--space-4)));
        max-width: 70rem;
        max-height: calc(100dvh - 2 * var(--space-4));
        grid-template-rows: auto minmax(0, 1fr);
        overflow: hidden;
        padding: 0;
        gap: 0;
    }

    :global(.models-dialog-header.models-dialog-header) {
        padding: var(--space-5) var(--space-6) var(--space-4);
        border-bottom: var(--divider);
    }

    .models-body {
        min-height: 0;
        overflow-y: auto;
        padding: var(--space-6);
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }
    }

    /* Tighter insets on phones so the cards get the width; the header follows
       so its title stays aligned with the provider headings. */
    @media (--bp-xs) {
        .models-body {
            padding: var(--space-4) var(--space-3);
        }

        :global(.models-dialog-header.models-dialog-header) {
            padding-inline: var(--space-3);
        }
    }

    .models-empty {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }

    .models-group {
        margin-bottom: var(--space-8);

        &:last-child {
            margin-bottom: 0;
        }

        h2 {
            margin-bottom: var(--space-4);
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-medium);
        }
    }

    .models-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(30rem, 100%), 1fr));
        gap: var(--space-4);
        align-items: start;
    }
</style>
