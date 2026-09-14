<!--
  @component Quick toggles for a model's capabilities inside the models list.
  Each round button switches one capability (file upload, vision, tool calling,
  native web search, native code execution, image generation) and hands the
  changed field values to `onChange`, e.g.
  `onChange={(changes) => mutations.update(row, changes)}`.
-->
<script lang="ts">
    import type { Component } from 'svelte';
    import type { HugeiconsProps } from '@hugeicons/svelte';
    import { mergeProps } from 'bits-ui';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import Attachment01Icon from '$lib/components/ui/icons/iconset/Attachment01Icon.svelte';
    import EyeIcon from '$lib/components/ui/icons/iconset/EyeIcon.svelte';
    import ToolboxIcon from '$lib/components/ui/icons/iconset/ToolboxIcon.svelte';
    import GlobalSearchIcon from '$lib/components/ui/icons/iconset/GlobalSearchIcon.svelte';
    import SourceCodeIcon from '$lib/components/ui/icons/iconset/SourceCodeIcon.svelte';
    import AiImageIcon from '$lib/components/ui/icons/iconset/AiImageIcon.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AdminRow } from '../schemas/admin-content.js';
    import {
        hasModelCapability,
        modelCapabilities,
        toggleModelCapability,
        type ModelCapabilityId
    } from '../capabilities.js';

    const {
        row,
        disabled = false,
        onChange
    }: {
        row: AdminRow;
        disabled?: boolean;
        /** Saves the field values a toggle produced; the toggle shows as busy until the promise settles. */
        onChange: (changes: Record<string, unknown>) => Promise<void> | void;
    } = $props();
    const { __ } = useTranslator();
    const icons: Record<ModelCapabilityId, Component<HugeiconsProps>> = {
        file_upload: Attachment01Icon,
        vision: EyeIcon,
        tool_calling: ToolboxIcon,
        web_search: GlobalSearchIcon,
        code_execution: SourceCodeIcon,
        image_generation: AiImageIcon
    };
    let pending = $state<ModelCapabilityId | null>(null);

    async function toggle(id: ModelCapabilityId) {
        if (disabled || pending) return;
        pending = id;
        try {
            await onChange(toggleModelCapability(row, id, !hasModelCapability(row, id)));
        } finally {
            pending = null;
        }
    }
</script>

<div
    class="capabilities"
    role="group"
    aria-label={__('admin.fields.capabilities')}
>
    {#each modelCapabilities as id (id)}
        {@const Icon = icons[id]}
        {@const enabled = hasModelCapability(row, id)}
        {@const label = __('admin.capabilities.' + id)}
        <Tooltip
            tooltip={label}
            delayDuration={300}
        >
            {#snippet children(trigger)}
                <button
                    {...mergeProps(trigger.props, { onclick: () => toggle(id) })}
                    type="button"
                    role="switch"
                    class="toggle"
                    data-capability={id}
                    aria-checked={enabled}
                    aria-label={label}
                    aria-busy={pending === id || undefined}
                    {disabled}
                >
                    <Icon
                        size={16}
                        aria-hidden="true"
                    />
                </button>
            {/snippet}
        </Tooltip>
    {/each}
</div>

<style>
    .capabilities {
        display: inline-flex;
        gap: var(--space-1_5);
    }
    .toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        padding: 0;
        border: 1px dashed var(--color-text-muted);
        border-radius: var(--corner-full);
        cursor: pointer;
        background-color: color-mix(in oklch, var(--color-text-muted) 18%, var(--color-bg));
        color: var(--color-text-muted);
        transition:
            background-color var(--duration-fast),
            color var(--duration-fast);
    }
    .toggle:hover:not(:disabled) {
        background-color: color-mix(in oklch, var(--color-text-muted) 30%, var(--color-bg));
        color: var(--color-text-muted);
    }
    .toggle:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }
    .toggle:disabled {
        cursor: not-allowed;
    }
    .toggle[aria-busy='true'] {
        opacity: 0.5;
    }
    .toggle[aria-checked='true'] {
        border: 1px solid var(--capability-fill);
        background-color: var(--capability-fill);
        color: var(--capability-on-fill);
    }
    .toggle[aria-checked='true']:hover:not(:disabled) {
        background-color: color-mix(in oklab, var(--capability-fill) 85%, white);
        color: var(--capability-on-fill);
    }
</style>
