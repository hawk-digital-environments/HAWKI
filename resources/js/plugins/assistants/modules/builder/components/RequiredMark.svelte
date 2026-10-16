<script lang="ts">
    import type {Assistant} from '$plugins/assistants/types/assistant/Assistant';
    import {COMPLETENESS_RULES} from '$plugins/assistants/modules/builder/contexts/builderValidationRules';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';

    // Asterisk behind the label of a mandatory field. Whether a field is
    // mandatory comes from the completeness rules, so the marker cannot drift
    // from what the step footer actually enforces.
    let {field}: { field: keyof Assistant } = $props();

    const {__} = useTranslator();
    const required = $derived(COMPLETENESS_RULES.some(rule => rule.keys.includes(field)));
</script>

{#if required}
    <span class="required-mark" title={__('assistants.builder.steps.required')} aria-hidden="true">*</span>
{/if}

<style>
    .required-mark {
        margin-inline-start: var(--space-1);
        opacity: .5;
    }
</style>
