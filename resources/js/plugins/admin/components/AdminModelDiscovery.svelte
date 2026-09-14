<!--
  @component Model picker for a provider discovery result.
  Creates the selected models in the models section and removes each
  successful item from the list; progress and errors stay inside the picker.
-->
<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import { untrack } from 'svelte';

    let {
        models,
        providerId
    }: {
        /** Models the provider reported; already added ones are removed from the list as they are created. */
        models: { model_id: string; label: string }[];
        providerId: string;
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();
    let remaining = $state(untrack(() => models));
    let candidates = $state<string[]>([]);
    let discoverySearch = $state('');
    let busy = $state(false);
    let notice = $state('');
    let error = $state('');
    const discoveredModels = $derived.by(() => {
        const needle = discoverySearch.trim().toLowerCase();
        if (!needle) return remaining;
        return remaining.filter(
            (model) => model.label.toLowerCase().includes(needle) || model.model_id.toLowerCase().includes(needle)
        );
    });

    async function addModels() {
        if (busy || !providerId) return;
        busy = true;
        error = '';
        notice = '';
        try {
            for (const modelId of [...candidates]) {
                const model = remaining.find((item) => item.model_id === modelId);
                await app.restApi.createResource('admin-models', {
                    model_id: modelId,
                    label: model?.label ?? modelId,
                    provider_id: Number(providerId),
                    active: false,
                    model_type: 'chat',
                    tools: [],
                    usage_rules: ['main']
                });
                candidates = candidates.filter((id) => id !== modelId);
                remaining = remaining.filter((item) => item.model_id !== modelId);
            }
            notice = __('admin.models_added');
        } catch (failure) {
            error = failure instanceof Error ? failure.message : __('admin.errors.save');
        } finally {
            busy = false;
        }
    }
</script>

{#if error}<p role="alert">{error}</p>{/if}
<p role="status">{notice}</p>
<p>{__('admin.discovery_hint')}</p>
<Input
    aria-label={__('admin.discovery_search')}
    placeholder={__('admin.discovery_search')}
    type="search"
    bind:value={discoverySearch}
/>
<p
    class="discovery-count"
    aria-live="polite"
>
    {__('admin.discovery_count', {
        shown: String(discoveredModels.length),
        total: String(remaining.length)
    })}
</p>
{#if discoveredModels.length === 0}
    <p class="discovery-empty">{__('admin.discovery_no_matches')}</p>
{/if}
<ul class="discovered">
    {#each discoveredModels as model (model.model_id)}
        <li>
            <label
                ><input
                    type="checkbox"
                    checked={candidates.includes(model.model_id)}
                    disabled={busy || !app.can('models.manage')}
                    onchange={(event) =>
                        (candidates =
                            event.currentTarget.checked ?
                                [...candidates, model.model_id]
                            :   candidates.filter((id) => id !== model.model_id))}
                />{model.label}<small>{model.model_id}</small></label
            >
        </li>
    {/each}
</ul>
{#if app.can('models.manage')}
    <Button
        variant="fill"
        disabled={busy || candidates.length === 0}
        onclick={addModels}>{__('admin.add_models')}</Button
    >
{/if}

<style>
    .discovery-count,
    .discovery-empty {
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        margin-block: var(--space-2);
    }
    [role='status']:empty {
        display: none;
    }
    .discovered {
        list-style: none;
        padding: 0;
        overflow-y: auto;
        max-height: 24rem;
    }
    .discovered label {
        display: flex;
        gap: var(--space-2);
        flex-wrap: wrap;
        padding-block: var(--space-2);
    }
    .discovered small {
        color: var(--color-text-muted);
    }
</style>
