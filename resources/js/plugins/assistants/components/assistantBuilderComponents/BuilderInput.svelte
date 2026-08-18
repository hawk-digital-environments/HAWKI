<script lang="ts">
    import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import { assistantOptionsStore } from '$lib/plugins/assistants/stores/AssistantOptionsStore.svelte.js';
    import type { Assistant } from '$lib/plugins/assistants/types/assistant/Assistant';
    import type { AssistantTag } from '$lib/plugins/assistants/types/assistant/AssistantTag';
    import Input from '$lib/plugins/assistants/components/textInputs/Input.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import InputError from '$lib/plugins/assistants/components/inputError/InputError.svelte';
    import Select from '$lib/plugins/assistants/components/select/Select.svelte';
    import AddableItemList from "$lib/plugins/assistants/components/itemList/AddableItemList.svelte";
    import FullWidthToggle from "$lib/plugins/assistants/components/toggle/FullWidthToggle.svelte";
    import Slider from "$lib/components/ui/slider/Slider.svelte";
    import Tooltip from "$lib/components/ui/tooltip/Tooltip.svelte";
    import AlertCircleIcon from "$lib/components/ui/icons/iconset/AlertCircleIcon.svelte";
    import type {IconComponent} from '$lib/components/ui/icons';
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";


    const {__} = useTranslator();
    const builder = useBuilderContext();


    interface Props {
        type: 'input' | 'textarea' | 'select' | 'fullWidthToggle' | 'tag' | 'itemList' | "slider"
        label: string;
        name?: string;
        icon?: IconComponent;
        placeholder?: string;
        hint?: string;
        disabled?: boolean;
        options?: string[];
        description?: string;
        min?: number;
        max?: number;
        isInteger?: boolean;
        assistantValueKey: keyof Assistant;
        // style vars
        render?: 'block' | 'inline';
    }
    const {
        type,
        label,
        name,
        icon,
        placeholder = '',
        hint,
        disabled = false,
        options = [],
        assistantValueKey,
        description = '',
        min,
        max,
        isInteger = false,
        render = 'block'

    }: Props = $props();


    // Most fields map straight onto the primitive their input expects. The
    // three relationship fields are the exception: they hold value objects
    // ({ id, text }), so we adapt them to/from the plain strings the generic
    // inputs work with, and source their options from the server (see
    // `assistantOptions`).
    const isCategory = $derived(assistantValueKey === 'category');
    const isLanguage = $derived(assistantValueKey === 'language');
    const isFormality = $derived(assistantValueKey === 'formality');
    const isAnswerLength = $derived(assistantValueKey === 'answerLength');

    // Inline server validation error for this field, if any.
    let error = $derived(builder.validator.errorFor(assistantValueKey));

    let currentValue = $derived(
        isCategory ? builder.draft?.category?.id :
            isFormality ? builder.draft.formality :
                isLanguage ? builder.draft.language:
                    isAnswerLength ? builder.draft.answerLength:
                        builder.draft[assistantValueKey] as any
    );
    let booleanValue = $derived(Boolean(currentValue));



    // itemList values are already string[].
    let stringArrayValue = $derived(
        Array.isArray(currentValue) ? (currentValue as string[]) : []
    );

    // Slider works with a plain number; fall back to the lower bound.
    let numberValue = $derived(typeof currentValue === 'number' ? currentValue : (min ?? 0));

    // Category holds a value object ({ id, text }); the three setting fields hold
    // primitive values whose human label comes from the matching server setting.
    let selectedCategory = $derived(
        isCategory ? assistantOptionsStore.categories.find(i => i.id === currentValue) : undefined
    );
    let settingKey = $derived(
        isFormality ? 'formality' :
            isLanguage ? 'language' :
                isAnswerLength ? 'answer_length' : null
    );
    let activeSetting = $derived(
        settingKey ? assistantOptionsStore.settings.find(s => s.key === settingKey) : undefined
    );
    let activeOptionLabel = $derived(
        activeSetting?.options?.find(o => o.value === currentValue)?.label
    );

    let selectValue = $derived(
        isCategory ? (selectedCategory ? __(selectedCategory.text) : undefined) :
            activeSetting ? (activeOptionLabel ? __(activeOptionLabel) : undefined) :
                currentValue
    );

    // Relationship fields override any options passed in with the server lists.
    let effectiveOptions = $derived(
        isCategory ? assistantOptionsStore.categories.map(c => __(c.text)) :
            activeSetting ? (activeSetting.options?.map(o => __(o.label ?? '')) ?? []) :
                options
    );

    // Coerce to the plain string[] the Select primitive expects.
    let selectOptions = $derived((effectiveOptions ?? []).filter((o): o is string => o != null));

    // write to store, translating back into the field's stored shape
    function update(value: any) {
        if (isCategory) {
            builder.set('category', assistantOptionsStore.categories.find(c => __(c.text) === value) ?? null);
        }
        else if (activeSetting) {
            const option = activeSetting.options?.find(o => __(o.label ?? '') === value);
            builder.set(assistantValueKey, option?.value ?? undefined);
        }
        else {
            builder.set(assistantValueKey, value);
        }
    }
</script>

<!--
  The generic primitives are now bare form controls, so this component owns the
  shared field chrome (container + label/error header) once and swaps only the
  inner control per type. FullWidthToggle is self-contained (its label sits
  inline with the switch) and so renders on its own.
-->
{#snippet fieldHeader()}
    {#if label || error || hint || type === 'slider'}
        <div class="field-header">
            {#if label}
                <label for={name} class="label">{label}</label>
            {/if}
            {#if hint}
                <!-- Hint text is hidden until the trigger is hovered/focused. -->
                <Tooltip tooltip={hint} side="top" delayDuration={150}>
                    {#snippet children({props})}
                        <button type="button" class="hint-trigger" aria-label={hint} {...props}>
                            <AlertCircleIcon size="1em" />
                        </button>
                    {/snippet}
                </Tooltip>
            {/if}
            <InputError message={error} />
            {#if type === 'slider'}
                <span class="slider-value">{numberValue}</span>
            {/if}
        </div>
    {/if}
{/snippet}

{#if type === 'fullWidthToggle'}
    <FullWidthToggle
        label={label}
        icon={icon}
        description={description}
        defaultValue={booleanValue}
        {disabled}
        {error}
        onchange={update}
    />

{:else}
    <div class="input-container"
         class:renderBlock={render === 'block'}
         class:renderInline={render === 'inline'}
    >
        {@render fieldHeader()}

        {#if type === 'input'}
            <Input
                id={name}
                {placeholder}
                {disabled}
                value={currentValue ?? ''}
                oninput={(e) => update(e.currentTarget.value)}
            />

        {:else if type === 'textarea'}
            <Textarea
                id={name}
                {placeholder}
                {disabled}
                value={currentValue ?? ''}
                oninput={(e) => update(e.currentTarget.value)}
            />

        {:else if type === 'select'}
            <Select
                id={name}
                options={selectOptions}
                value={selectValue}
                {disabled}
                oninput={(e) => update(e.currentTarget.value)}
            />

        {:else if type === 'itemList'}
            <AddableItemList
                name={name}
                defaultValue={stringArrayValue}
                {disabled}
                onchange={update}
            />

        {:else if type === 'slider'}
            {#if description}
                <p class="field-note">{description}</p>
            {/if}
            <Slider
                value={numberValue}
                min={min}
                max={max}
                step={isInteger ? 1 : undefined}
                {disabled}
                onValueChange={update}
            />
        {/if}
    </div>
{/if}

<style>
    .hint-trigger {
        display: inline-flex;
        align-items: center;
        padding: 0;
        background: none;
        border: none;
        cursor: help;
        color: var(--color-text-muted);
        transition: color var(--duration-fast);
    }
    .hint-trigger:hover,
    .hint-trigger:focus-visible {
        color: var(--color-text);
    }
    .slider-value {
        margin-inline-start: auto;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .field-note {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
