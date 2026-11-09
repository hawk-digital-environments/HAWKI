<!--
  @component The review's read-only walk along the builder's steps: a top
  bar with prev/next buttons flanking the section's name and a position
  indicator (1/4), then — laid out like the builder's own section pages —
  a page header with the section's title/description and the fields on
  paper tiles (grid-2 pairs where the builder pairs them). Fields the
  submission changed carry an accent outline and elevation. Step swaps use
  the sidebar's drill transition, collapsing to an instant swap under
  reduced motion.
-->
<script lang="ts">
    import ReadonlyField from './ReadonlyField.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import AssistantAvatarIcon from '$plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte';
    import {drillTransition} from '$lib/utils/transitions/drillTransition';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {ReviewTargetWire} from '$plugins/assistants/admin/api/schemas.js';

    const {__} = useTranslator();

    /** The section names mirror the builder's sidebar. */
    const STEPS = ['general', 'model', 'behaviour', 'knowledge'] as const;

    interface Props {
        /** The review target whose submitted state is displayed. */
        target: ReviewTargetWire;
        /** The active step index; bindable so chips can jump to a step. */
        step?: number;
        /** The draft's changed keys — fields carrying any of them render as changed. */
        changedKeys?: string[];
    }

    let {target, step = $bindable(0), changedKeys = []}: Props = $props();

    /** The drill direction of the last navigation, for the in/out transitions. */
    let direction = $state<'deeper' | 'back'>('deeper');

    function goTo(next: number): void {
        if (next < 0 || next >= STEPS.length || next === step) return;
        direction = next > step ? 'deeper' : 'back';
        step = next;
    }

    const sectionLabel = $derived(__(`assistants.builder.sidebar.${STEPS[step]}`));

    /** Whether any of the field's payload keys is part of the submission's delta. */
    function changed(...keys: string[]): boolean {
        return keys.some(key => changedKeys.includes(key));
    }

    function join(texts: Array<string | null | undefined> | undefined | null, separator = ', '): string | null {
        const joined = (texts ?? []).filter((text): text is string => typeof text === 'string' && text !== '').join(separator);

        return '' === joined ? null : joined;
    }

    /** The setting values as "label: value" lines, formality/language/answer style. */
    const settingLines = $derived(
        join(target.assistant_setting_values?.map(
            value => `${__(`assistants.settings.${value.setting?.key ?? '?'}.label`)}: ${String(value.value ?? '—')}`
        ), '\n')
    );

    const number = (value: number | null | undefined): string | null =>
        null !== value && undefined !== value ? String(value) : null;
</script>

<div class="readonly-steps">
    <div class="topbar">
        <Button
            class="nav"
            variant="stroke"
            iconLeft={ArrowLeft01Icon}
            aria-label={__('assistants.admin.detail.previous_step')}
            title={__('assistants.admin.detail.previous_step')}
            disabled={step === 0}
            onclick={() => goTo(step - 1)}
        />
        <div class="position">
            <span class="section">{sectionLabel}</span>
            <span class="indicator">{step + 1}/{STEPS.length}</span>
        </div>
        <Button
            class="nav"
            variant="stroke"
            iconLeft={ArrowRight01Icon}
            aria-label={__('assistants.admin.detail.next_step')}
            title={__('assistants.admin.detail.next_step')}
            disabled={step === STEPS.length - 1}
            onclick={() => goTo(step + 1)}
        />
    </div>

    <!-- The drill cell: both step levels share one grid cell during the
         swap (the nav-stack pattern from drillTransition), so the outgoing
         page's unmount never collapses the card mid-slide. -->
    <div class="stack" aria-live="polite">
        {#key step}
            <div
                class="step-page"
                in:drillTransition={{direction, distance: 120, duration: 200}}
                out:drillTransition={{direction: direction === 'deeper' ? 'back' : 'deeper', distance: 120, duration: 200}}
            >
                <header class="page-header">
                    <h3 class="page-title">{__(`assistants.builder.${STEPS[step]}.title`)}</h3>
                    <p class="page-description">{__(`assistants.builder.${STEPS[step]}.description`)}</p>
                </header>
    
                {#if step === 0}
                    <div class="fields">
                        <div class="identity" class:changed={changed('name', 'handle', 'avatar_id')}>
                            {#if target.assistant_avatar?.icon_css}
                                <div class="avatar-tile" class:changed={changed('avatar_id')}>
                                    <AssistantAvatarIcon
                                        size="large"
                                        assistantAvatar={{
                                            iconCss: target.assistant_avatar.icon_css,
                                            name: target.assistant_avatar.name ?? ''
                                        }}
                                    />
                                </div>
                            {/if}
                            <div class="identity-fields">
                                <ReadonlyField
                                    label={__('assistants.builder.general.input_name')}
                                    value={target.name}
                                    changed={changed('name')}
                                />
                                <ReadonlyField
                                    label={__('assistants.builder.general.input_handle')}
                                    value={target.handle}
                                    changed={changed('handle')}
                                />
                            </div>
                        </div>
                        <ReadonlyField
                            label={__('assistants.builder.general.input_description')}
                            value={target.description}
                            changed={changed('description')}
                        />
                        <ReadonlyField
                            label={__('assistants.builder.general.input_short_description')}
                            value={target.detail_description}
                            changed={changed('detail_description')}
                        />
                        <div class="grid-2">
                            <ReadonlyField
                                label={__('assistants.builder.general.input_category')}
                                value={target.assistant_category?.name ?? null}
                                changed={changed('category_id')}
                            />
                            <ReadonlyField
                                label={__('assistants.builder.general.input_tags')}
                                value={join(target.assistant_tags?.map(tag => tag.text))}
                                changed={changed('tag_ids', 'assistant_tags')}
                            />
                        </div>
                    </div>
                {:else if step === 1}
                    <div class="fields">
                        <ReadonlyField
                            label={__('assistants.admin.detail.fields.model')}
                            value={target.model}
                            changed={changed('model')}
                        />
                        <div class="grid-2">
                            <ReadonlyField
                                label={__('assistants.builder.model.input_max_tokens')}
                                value={number(target.max_tokens)}
                                changed={changed('max_tokens')}
                            />
                            <ReadonlyField
                                label={__('assistants.builder.model.input_allow_model_select')}
                                value={null !== target.allow_model_select && undefined !== target.allow_model_select
                                    ? __(target.allow_model_select ? 'assistants.admin.detail.value_yes' : 'assistants.admin.detail.value_no')
                                    : null}
                                changed={changed('allow_model_select')}
                            />
                        </div>
                        <div class="grid-2">
                            <ReadonlyField
                                label={__('assistants.builder.model.input_temperature')}
                                value={number(target.temp)}
                                changed={changed('temp')}
                            />
                            <ReadonlyField
                                label={__('assistants.builder.model.input_top_p')}
                                value={number(target.top_p)}
                                changed={changed('top_p')}
                            />
                        </div>
                    </div>
                {:else if step === 2}
                    <div class="fields">
                        <ReadonlyField
                            label={__('assistants.builder.behaviour.input_system_prompt')}
                            value={target.system_prompt}
                            prose
                            changed={changed('system_prompt')}
                        />
                        <ReadonlyField
                            label={__('assistants.builder.behaviour.input_greeting')}
                            value={target.greeting}
                            prose
                            changed={changed('greeting')}
                        />
                        <ReadonlyField
                            label={__('assistants.builder.behaviour.input_starter_prompts')}
                            value={join(target.assistant_user_prompts?.map(prompt => prompt.text), '\n')}
                            prose
                            changed={changed('user_prompts', 'assistant_user_prompts')}
                        />
                        <ReadonlyField
                            label={__('assistants.admin.detail.fields.settings')}
                            value={settingLines}
                            prose
                            changed={changed('setting_values')}
                        />
                    </div>
                {:else}
                    <div class="fields">
                        <ReadonlyField
                            label={__('assistants.admin.detail.fields.attachments')}
                            value={join(target.assistant_attachments?.map(attachment => attachment.name))}
                            changed={changed('attachment_ids')}
                        />
                        <ReadonlyField
                            label={__('assistants.admin.detail.fields.tools')}
                            value={join(target.ai_tools?.map(tool => tool.name))}
                            changed={changed('tool_ids', 'ai_tools')}
                        />
                    </div>
                {/if}
            </div>
        {/key}
    </div>
</div>

<style>
    .readonly-steps {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
    }

    .position {
        display: flex;
        align-items: baseline;
        gap: var(--space-2);
        min-width: 0;
    }

    .section {
        font-size: var(--font-size-md);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .indicator {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        font-variant-numeric: tabular-nums;
    }

    .stack {
        display: grid;
        min-width: 0;
    }

    .step-page {
        grid-area: 1 / 1;
        min-width: 0;
    }

    /* The builder section page's header metrics (see the builder layout's
       :global(.page-header) rules). */
    .page-header {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        margin-bottom: var(--space-4);
    }

    .page-title {
        margin: 0;
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }

    .page-description {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }

    .fields {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    /* The builder's two-column row for short fields. */
    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4) var(--space-3);
    }

    @media (--bp-md-and-smaller) {
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }

    /* Avatar beside the identity fields on the general step; outlines like
       the paper tiles when its field changed. */
    .identity {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .avatar-tile {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--color-surface-raised);
        box-shadow: none;
        opacity: 0.72;
        padding: var(--space-2);
        outline: 1px solid transparent;
        outline-offset: -1px;
        transition:
            box-shadow var(--duration-fast) var(--easing-out),
            opacity var(--duration-fast) var(--easing-out),
            outline-color var(--duration-fast) var(--easing-out);
    }

    .avatar-tile.changed {
        opacity: 1;
        box-shadow: var(--elevation-1), var(--elevation-2);
        outline-color: color-mix(in oklab, var(--color-accent-500) 45%, transparent);
    }
</style>
