<!--
  @component One variant option in the builder's capability variant picker:
  the option label, a status dot reflecting availability for the selected
  model, an optional info popover, and — via `RadioCard`'s `disabledTooltip`
  — an explanatory tooltip when the variant is unavailable for the selected
  model. Composes `RadioCard` + `ToolStatusDot` + `InfoPopover` so the
  variant blocks in `CapabilitiesList` stay declarative one-liners.

  `disabled`, `statusSupported` and the tooltip are typically driven by the
  same predicate, so the card's disabled state, its dot and its tooltip can
  never disagree.

  ## Usage
  ```svelte
  <VariantCard
      value="native"
      disabled={!hasNative}
      disabledTooltip={__('assistants.builder.tools.capabilities.variantUnavailable')}
      model={currentModel}
      statusTool={capability}
      statusSupported={hasNative}>
      {__('assistants.builder.tools.capabilities.nativeLabel')}
  </VariantCard>
  ```
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import RadioCard from '$lib/components/ui/radio-card/RadioCard.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import ToolStatusDot from '$plugins/assistants/modules/builder/components/aiToolComponents/ToolStatusDot.svelte';
    import type {AiToolOrCapability, ExtendedAiTool} from '$plugins/core/stores/aiToolStoreData.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

    let {
        value,
        disabled,
        disabledTooltip,
        model = undefined,
        statusTool,
        statusSupported,
        info = undefined,
        infoLabel = undefined,
        children,
    } = $props<{
        /** The variant's value within the group (e.g. 'auto', 'native', a tool name). */
        value: string;
        /** Disables the variant — typically "the selected model can't fulfil it". */
        disabled: boolean;
        /** Shown as the card's tooltip while disabled. */
        disabledTooltip: string;
        /** The assistant's currently selected model; keeps the status dot's not-supported label speaking. */
        model?: AiModel | null | undefined;
        /** The tool (or capability wrapper) whose status the dot reflects. */
        statusTool: AiToolOrCapability | ExtendedAiTool;
        /** Whether `statusTool` is available for the selected model. */
        statusSupported: boolean;
        /** Optional info-popover text describing the variant. */
        info?: string | undefined;
        /**
         * Name of the variant for the info popover's accessible trigger
         * button; falls back to `value` so the button is always named.
         */
        infoLabel?: string | undefined;
        /** The variant's label. */
        children: Snippet;
    }>();
</script>

<RadioCard {value} class="capability-variant" {disabled} {disabledTooltip}>
    {@render children()}
    <span class="variant-meta">
        <ToolStatusDot tool={statusTool} supported={statusSupported} {model} focusable={false}/>
        {#if info}
            <InfoPopover label={infoLabel ?? value} info={info}/>
        {/if}
    </span>
</RadioCard>

<style>
    .variant-meta {
        display: flex;
        align-items: center;
        gap: var(--space-2, 0.5rem);
    }
</style>
