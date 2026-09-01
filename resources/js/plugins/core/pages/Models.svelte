<!--
  @component Page component for the `/models` route (route name `models.index`)
  — the model showcase, analogous to the announcements ("Aktuelles") page.
  Renders every available AI model as a bordered `ModelCard`, grouped by
  provider. Reached via the profile dropdown (`ProfileButton.svelte`) and the
  "all models" footer link in `ModelPickerV2`.
-->
<script lang="ts">
    import ModelCard from '$plugins/core/components/ModelCard.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {}: RouteProps = $props();

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

<div class="models-page">
    <h1>{__('ai.model.page.title')}</h1>

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

<style>
    .models-page {
        width: min(70rem, 100%);
        margin-inline: auto;
        padding: var(--space-8) var(--space-4);

        h1 {
            margin-bottom: var(--space-6);
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
        }
    }

    .models-empty {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }

    .models-group {
        margin-bottom: var(--space-8);

        h2 {
            margin-bottom: var(--space-4);
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold);
        }
    }

    .models-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
        gap: var(--space-4);
        align-items: start;
    }
</style>
