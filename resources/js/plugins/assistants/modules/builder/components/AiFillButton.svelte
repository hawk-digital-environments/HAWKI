<!--
  @component The composer's star, next to a builder field's label: asks the AI
  guide to fill that field, or, when it's already filled, what to change
  (`builder.requestGuideFill`; the test panel opens the guide and sends the
  request). Renders nothing for fields the guide can't fill.

  @example
  ```svelte
  <label for="name">Name</label>
  <AiFillButton field="name"/>
  ```
-->
<script lang="ts">
    import type {Assistant} from '$plugins/assistants/types/assistant/Assistant';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import GoogleGeminiIcon from '$lib/components/ui/icons/iconset/GoogleGeminiIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import {isFieldEmpty, isGuideFillable} from '$plugins/assistants/modules/builder/contexts/builderGuideChat.svelte.js';

    interface Props {
        field: keyof Assistant;
        /** The field's label, for the button's accessible name. */
        label: string;
    }

    const {field, label}: Props = $props();
    const {__} = useTranslator();
    const builder = useBuilderContext();
    const tooltip = $derived(__(isFieldEmpty(builder.draft[field])
        ? 'assistants.builder.guide.fill_field'
        : 'assistants.builder.guide.revise_field'));
</script>

{#if isGuideFillable(field)}
    <span class="ai-fill-button">
        <ButtonWithTooltip
            {tooltip}
            aria-label={`${tooltip}: ${label}`}
            size="xs"
            variant="ghost"
            iconRight={GoogleGeminiIcon}
            disabled={builder.draft.id === null || builder.guideFillRequest === field}
            onclick={() => builder.requestGuideFill(field)}
        />
    </span>
{/if}

<style>
    /* Right-aligned in the field header; the negative block margin keeps
       the header as tall as its label, with or without the button. */
    .ai-fill-button {
        display: flex;
        margin-block: calc(-1 * var(--space-1_5));
        margin-inline-start: auto;
    }
</style>
