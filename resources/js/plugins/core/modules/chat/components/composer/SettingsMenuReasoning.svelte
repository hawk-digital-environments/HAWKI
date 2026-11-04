<!--
  @component Reasoning effort picker of the `SettingsMenu`: a `DropdownMenuSub`
  row (`label · current value ›`) whose submenu lists only the levels the
  current model advertises through its `feature-reasoning-*` flags
  (`modelParameters.supportedReasoningLevels`) as check-marked radio items.
  Hover/keyboard behaviour comes from the submenu primitive. Renders nothing
  when the model supports no adjustable level. Must live inside the
  `DropdownMenu` of `SettingsMenu`. Takes no props — reads/writes
  `ComposerContext` directly. A leading "Default" item clears the selection so
  the model's own default applies again.
-->
<script lang="ts">
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ReasoningLevel} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelParameterSlice.svelte.js';
    import DropdownMenuSub from '$lib/components/ui/dropdown-menu/DropdownMenuSub.svelte';
    import DropdownMenuRadioGroup from '$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
    import DropdownMenuRadioItem from '$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();

    const levelLabels: Record<ReasoningLevel, string> = {
        none: __('chat.composer.settings.reasoningLevelNone'),
        minimal: __('chat.composer.settings.reasoningLevelMinimal'),
        low: __('chat.composer.settings.reasoningLevelLow'),
        medium: __('chat.composer.settings.reasoningLevelMedium'),
        high: __('chat.composer.settings.reasoningLevelHigh'),
        xhigh: __('chat.composer.settings.reasoningLevelXhigh'),
        max: __('chat.composer.settings.reasoningLevelMax')
    };

    const levels = $derived.by(() => composerContext.modelParameters.supportedReasoningLevels);
    const effort = $derived.by(() => composerContext.modelParameters.reasoningEffort);

    function handleChange(value: string) {
        composerContext.modelParameters.set('reasoning_effort', value ? value as ReasoningLevel : null);
    }
</script>

{#if levels.length > 0}
    <DropdownMenuSub
        label={__('chat.composer.settings.reasoningHeading')}
        value={effort ? levelLabels[effort] : __('chat.composer.settings.reasoningDefault')}
        aria-label={__('chat.composer.settings.reasoningAriaLabel')}
    >
        <DropdownMenuRadioGroup value={effort ?? ''} onValueChange={handleChange}>
            <DropdownMenuRadioItem value="" indicator="check" closeOnSelect={false}>
                {__('chat.composer.settings.reasoningDefault')}
            </DropdownMenuRadioItem>
            {#each levels as level (level)}
                <DropdownMenuRadioItem value={level} indicator="check" closeOnSelect={false}>
                    {levelLabels[level]}
                </DropdownMenuRadioItem>
            {/each}
        </DropdownMenuRadioGroup>
    </DropdownMenuSub>
{/if}
