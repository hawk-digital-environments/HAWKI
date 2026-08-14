<script lang="ts">

    import ArrowDown01Icon from '$lib/components/ui/icons/iconset/ArrowDown01Icon.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';
    import {getContext, untrack} from "svelte";
    import {idMaker} from "$lib/utils/id_generator";
    import {HELP_ACCORDION_KEY, type HelpAccordionContext} from "./helpAccordion";

    let {
        label,
        icon: Icon,
        description,
        isActive=false
    } = $props <{
        label: string;
        icon: IconComponent;
        description: string;
        isActive?: boolean;
    }>();

    const accordion = getContext<HelpAccordionContext | undefined>(HELP_ACCORDION_KEY);
    const id = idMaker();

    // Standalone open-state, used only when there is no enclosing HelpPanel.
    let localOpen = $state(false);

    // `isActive` is an init-only seed for the initially-open element (first
    // one wins); untrack keeps it from being read as live reactive state.
    untrack(() => {
        if (!isActive) return;
        localOpen = true;
        accordion?.requestInitial(id);
    });

    // Inside a HelpPanel the open state is owned by the accordion, so only one
    // element is open at a time; standalone, it falls back to local state.
    let active = $derived(accordion ? accordion.isActive(id) : localOpen);

    function toggle() {
        if (accordion) accordion.toggle(id);
        else localOpen = !localOpen;
    }
</script>

<div class="help-element-wrapper"
    class:isActive={active}
>
    <button class="help-element"
            onclick={toggle}
    >
        <span class="icon-wrapper">
            <span class="icon"><Icon size="1em" /></span>
        </span>
        <span class="label">
            {label}
        </span>
        <span class="arrow-wrapper">
            <span class="arrow-icon"><ArrowDown01Icon size="1em" /></span>
        </span>
    </button>
    <div class="help-description">
        { description }
    </div>
</div>

<style>

    /* Borderless rows in the sidebar's language: rounded hover/active washes
       instead of boxed borders, nav-scale type, muted icons. */
    .help-element-wrapper {
        display: flex;
        width: 100%;
        /* Size to content and keep it: as a child of the scrollable list it
           must not stretch to the container height nor be shrunk away. */
        flex: 0 0 auto;
        flex-direction: row;
        align-items: center;
        border-radius: var(--corner-sm);
    }
    .help-element {
        display: grid;
        grid-template-columns: auto 1fr auto;
        flex-direction: row;
        width: 100%;
        min-height: 2.125rem;
        align-items: center;
        gap: var(--space-2_5);
        padding: 0 var(--space-2_5);
        /* Self-contained reset: this row is a <button>, and outside the builder
           shell there is no ambient `button { border: none }` to lean on. */
        border: none;
        background: transparent;
        color: inherit;
        font: inherit;
        border-radius: var(--corner-sm);
        cursor: pointer;
        opacity: 0.85;
        transition:
            opacity var(--transition-fast),
            background-color var(--transition-fast);
    }
    .help-element:hover{
        opacity: 1;
        background-color: var(--color-hover);
    }
    .label{
        font-size: var(--font-size-nav);
        color: var(--color-text);
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        text-wrap: nowrap;
        white-space: nowrap;
    }
    .icon-wrapper,
    .arrow-wrapper{
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-text-muted);
    }
    .icon{
        font-size: var(--font-size-base);
    }
    .arrow-icon{
        font-size: var(--font-size-base);
        transition: transform 120ms var(--easing-spring);
    }
    .help-description {
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
        display: none;
        padding: var(--space-1) var(--space-2_5) var(--space-2_5);
    }


    .help-element-wrapper.isActive{
        display: block;
        background-color: var(--color-accent-100);
    }
    .help-element-wrapper.isActive .help-element{
        opacity: 1;
        background-color: transparent;
    }
    .help-element-wrapper.isActive .label,
    .help-element-wrapper.isActive .icon{
        color: var(--color-accent-text);
    }
    .help-element-wrapper.isActive .help-description{
        display: block;
        color: var(--color-text);
    }
    .help-element-wrapper.isActive .arrow-icon{
        transform: rotate(180deg);
        color: var(--color-accent-text);
    }
</style>
