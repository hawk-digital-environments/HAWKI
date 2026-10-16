<script lang="ts">
    import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import { assistantOptionsStore } from '$plugins/assistants/stores/AssistantOptionsStore.svelte.js';
    import type { Assistant } from '$plugins/assistants/types/assistant/Assistant';
    import type { AssistantTag } from '$plugins/assistants/types/assistant/AssistantTag';
    import Input from '$plugins/assistants/components/textInputs/Input.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import InputError from '$plugins/assistants/components/inputError/InputError.svelte';
    import Select from '$plugins/assistants/components/select/Select.svelte';
    import AddableItemList from "$plugins/assistants/components/itemList/AddableItemList.svelte";
    import FullWidthToggle from "$plugins/assistants/components/toggle/FullWidthToggle.svelte";
    import Slider from "$lib/components/ui/slider/Slider.svelte";
    import InfoPopover from "$lib/components/ui/popover/InfoPopover.svelte";
    import type {IconComponent} from '$lib/components/ui/icons';
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import AiFillReveal from "$plugins/assistants/modules/builder/components/AiFillReveal.svelte";
    import CheckmarkCircle02Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte';
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import Loading03Icon from '$lib/components/ui/icons/iconset/Loading03Icon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import RequiredMark from "$plugins/assistants/modules/builder/components/RequiredMark.svelte";
    import {getScrollableParent} from "$plugins/assistants/components/testChat/textarea-resizer";


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
        /** Slider step; defaults to 1 for integer sliders. */
        step?: number;
        assistantValueKey: keyof Assistant;
        // style vars
        render?: 'block' | 'inline';
        /** Label for the itemList's collapsed "add" button; defaults to the tag wording. */
        addItemLabel?: string;
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
        step,
        render = 'block',
        addItemLabel

    }: Props = $props();


    // Most fields map straight onto the primitive their input expects. The
    // three relationship fields are the exception: they hold value objects
    // ({ id, text }), so we adapt them to/from the plain strings the generic
    // inputs work with, and source their options from the server (see
    // `assistantOptions`).
    const isCategory = $derived(assistantValueKey === 'category');
    const isLanguage = $derived(assistantValueKey === 'language');
    const isFormality = $derived(assistantValueKey === 'formality');
    const isAnswerStyle = $derived(assistantValueKey === 'answerStyle');

    // Inline server validation error for this field, if any.
    let error = $derived(builder.validator.errorFor(assistantValueKey));

    // A handle derived from the name is held back until the server confirmed
    // it (or picked a free suffix for it), then shown in one go.
    let currentValue = $derived(
        assistantValueKey === 'handle' && builder.handleChecking ? '' :
        isCategory ? builder.draft?.category?.id :
            isFormality ? builder.draft.formality :
                isLanguage ? builder.draft.language:
                    isAnswerStyle ? builder.draft.answerStyle:
                        builder.draft[assistantValueKey] as any
    );
    let booleanValue = $derived(Boolean(currentValue));

    // The handle field shows a spinner while the server checks a changed
    // handle, and confirms one it accepted as free.
    let handlePending = $derived(assistantValueKey === 'handle' && builder.handlePending);
    let handleAvailable = $derived(assistantValueKey === 'handle' && builder.handleAvailable);
    // ... and flags one it rejected, e.g. because it is taken.
    let handleRejected = $derived(
        assistantValueKey === 'handle' && !!error && !!builder.draft.handle && !builder.handlePending
    );



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
                isAnswerStyle ? 'answer_style' : null
    );
    let activeSetting = $derived(
        settingKey ? assistantOptionsStore.settings.find(s => s.key === settingKey) : undefined
    );
    let activeOptionLabel = $derived(
        activeSetting?.options?.find(o => o.value === currentValue)?.label
    );

    let selectValue = $derived(
        isCategory ? (selectedCategory ? __(selectedCategory.text) : '') :
            activeSetting ? (activeOptionLabel ? __(activeOptionLabel) : undefined) :
                currentValue
    );

    // Relationship fields override any options passed in with the server lists.
    // The category list is headed by an explicit "not set" entry (value ''),
    // TODO: Unify not set behaviour <pb: 23.09.26>
    let effectiveOptions = $derived(
        isCategory ? [
                {value: '', label: __('assistants.builder.general.input_category_not_set')},
                ...assistantOptionsStore.categories.map(c => __(c.text)),
            ] :
            activeSetting ? (activeSetting.options?.map(o => __(o.label ?? '')) ?? []) :
                options
    );

    // Coerce to the entry list the Select primitive expects.
    let selectOptions = $derived((effectiveOptions ?? []).filter(o => o != null));

    // Textareas grow with their content up to the CSS max-height, after which
    // they scroll. Once the user drags the resize handle, auto-grow stops so
    // the manually chosen height sticks.
    let textareaRef = $state<HTMLTextAreaElement | null>(null);
    let manuallyResized = false;

    function autoGrow(el: HTMLTextAreaElement) {
        if (manuallyResized || !el.offsetParent) return;
        // Collapsing to 'auto' can clamp the surrounding scroll position; restore it.
        const scrollParent = getScrollableParent(el);
        const savedScrollTop = scrollParent?.scrollTop ?? window.scrollY;
        const border = el.offsetHeight - el.clientHeight;
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + border + 'px';
        if (scrollParent) {
            scrollParent.scrollTop = savedScrollTop;
        } else {
            window.scrollTo({top: savedScrollTop, behavior: 'instant'});
        }
    }

    $effect(() => {
        if (type !== 'textarea' || !textareaRef) return;
        void currentValue; // re-run on typing and on externally loaded drafts
        autoGrow(textareaRef);
    });

    // Height and inline height at pointerdown on the textarea; a different
    // height at pointerup means the user dragged the resize handle.
    let resizeStart: {height: number; styleHeight: string} | null = null;

    // Lift the max-height for the duration of a possible drag so the handle can
    // pull the field past it. The current height is pinned first, otherwise a
    // field whose content exceeds the cap would jump to its full height.
    function handleResizePointerDown() {
        const el = textareaRef;
        if (!el || manuallyResized) return;
        resizeStart = {height: el.offsetHeight, styleHeight: el.style.height};
        el.style.height = el.offsetHeight + 'px';
        el.style.maxHeight = 'none';
    }

    function handleResizePointerUp() {
        const el = textareaRef;
        if (!resizeStart || !el) return;
        if (el.offsetHeight !== resizeStart.height) {
            manuallyResized = true;
        } else {
            // Plain click, not a resize: restore the cap and auto-grow height.
            el.style.maxHeight = '';
            el.style.height = resizeStart.styleHeight;
        }
        resizeStart = null;
    }

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
    {#if label || hint || type === 'slider'}
        <div class="field-header">
            {#if label}
                <label for={name}>{label}<RequiredMark field={assistantValueKey}/></label>
            {/if}
            {#if hint}
                <InfoPopover {label} info={hint}/>
            {/if}
            {#if type === 'slider'}
                <span class="slider-value">{numberValue}</span>
            {/if}
        </div>
    {/if}
{/snippet}

<svelte:window onpointerup={handleResizePointerUp} onpointercancel={handleResizePointerUp}/>

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
         class:sliderDisabled={type === 'slider' && disabled}
    >
        {@render fieldHeader()}

        {#if type === 'slider'}
            {#if description}
                <p class="field-note">{description}</p>
            {/if}
            <Slider
                value={numberValue}
                min={min}
                max={max}
                step={step ?? (isInteger ? 1 : undefined)}
                {disabled}
                onValueChange={update}
            />
        {:else}
            <!-- Blue reveal when the AI guide fills this field. -->
            <AiFillReveal field={assistantValueKey}>
                {#if type === 'input'}
                    <div class="input-wrap" class:hasStatus={handlePending || handleAvailable || handleRejected}>
                        <Input
                            id={name}
                            {placeholder}
                            {disabled}
                            value={currentValue ?? ''}
                            oninput={(e) => update(e.currentTarget.value)}
                        />
                        {#if handlePending}
                            <span class="input-status input-status--pending" role="status"
                                  aria-label={__('assistants.builder.general.handle_checking')}>
                                <Loading03Icon size="1.125rem"/>
                            </span>
                        {:else if handleAvailable}
                            <Tooltip tooltip={__('assistants.builder.general.handle_available')}
                                     delayDuration={300} focusable={false}>
                                {#snippet children({props})}
                                    <span class="input-status" role="img"
                                          aria-label={__('assistants.builder.general.handle_available')}
                                          {...props}>
                                        <CheckmarkCircle02Icon size="1.125rem"/>
                                    </span>
                                {/snippet}
                            </Tooltip>
                        {:else if handleRejected}
                            <Tooltip tooltip={error} delayDuration={300} focusable={false}>
                                {#snippet children({props})}
                                    <span class="input-status input-status--error" role="img"
                                          aria-label={error}
                                          {...props}>
                                        <AlertCircleIcon size="1.125rem"/>
                                    </span>
                                {/snippet}
                            </Tooltip>
                        {/if}
                    </div>

                {:else if type === 'textarea'}
                    <Textarea
                        bind:ref={textareaRef}
                        onpointerdown={handleResizePointerDown}
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
                        defaultValue={stringArrayValue}
                        {addItemLabel}
                        {disabled}
                        onchange={update}
                    />

                {/if}
            </AiFillReveal>
        {/if}
        <InputError message={error} />
    </div>
{/if}

<style>
    .input-container :global(.textarea) {
        max-height: 15rem;
        overflow-y: auto;
    }
    .input-wrap {
        position: relative;
    }
    /* The check sits centred in a square as tall as the field, so its gap to
       the top, bottom and end edge is the same; the text keeps clear of it. */
    .input-wrap.hasStatus :global(.input) {
        padding-inline-end: var(--space-10);
    }
    .input-status {
        position: absolute;
        inset-block: 0;
        inset-inline-end: 0;
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-success);
    }
    .input-status--error {
        color: var(--color-error);
    }
    .input-status--pending {
        color: var(--color-text-disabled);
    }
    .input-status--pending :global(svg) {
        animation: input-status-spin 700ms linear infinite;
    }
    @keyframes input-status-spin {
        to {
            rotate: 1turn;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .input-status--pending :global(svg) {
            animation-duration: 1400ms;
        }
    }
    .slider-value {
        margin-inline-start: auto;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        font-variant-numeric: tabular-nums;
    }
    /* A disabled slider mutes its whole field, not just the track. */
    .sliderDisabled label,
    .sliderDisabled .slider-value,
    .sliderDisabled .field-note {
        color: var(--color-text-disabled);
    }
    .field-note {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
