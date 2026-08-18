<script lang="ts">

    import Chatbox from "$lib/components/chat/Chatbox";
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';
    import type {Snippet} from "svelte";
    import {setContext} from "svelte";
    import {HELP_ACCORDION_KEY, type HelpAccordionContext} from "./helpAccordion";

    let {
        title,
        icon: Icon,
        description,
        children,
        isActive = false,
        onClose,
    } = $props<{
        title: string;
        icon: IconComponent;
        description: string;
        children: Snippet;
        isActive: boolean;
        onClose?: () => void;
    }>();

    // Single-open accordion: the panel owns the active id, the elements
    // (provided as children) coordinate through this context.
    let activeId = $state<string | null>(null);

    setContext<HelpAccordionContext>(HELP_ACCORDION_KEY, {
        isActive: (id) => activeId === id,
        toggle: (id) => { activeId = activeId === id ? null : id; },
        requestInitial: (id) => { if (activeId === null) activeId = id; },
    });


</script>


<div class="help-panel"
     class:isActive = {isActive}
>
    <div class="panel-inner">
        <div class="header">
            <div class="title-wrapper">
                <span class="icon-wrapper">
                    <span class="icon"><Icon size="1em" /></span>
                </span>
                <span class="title">{title}</span>
                <button class="close-btn"
                    onclick={()=> onClose?.()}
                >
                    <span class="icon"><Cancel01Icon size="1em" /></span>
                </button>
            </div>
            <div class="description">
                {description}
            </div>
        </div>
        <div class="content">
            {@render children()}
        </div>
        <div class="chatbox-wrapper">
            <Chatbox/>
        </div>
    </div>
</div>


<style>
    .help-panel {
        /* Outer clip box: animates its width; the fixed-width inner is pinned
           to the right (justify-content: flex-end) so it clips from the left
           and the panel collapses toward the right edge. Mirrors the app
           sidebar: translucent panel surface, divider hairline, spring easing. */
        display: flex;
        justify-content: flex-end;
        width: 0;
        height: 100%;
        justify-self: end;
        min-height: 0;
        overflow: hidden;
        background: var(--panel-bg);
        transition: width 280ms var(--easing-spring);
    }
    .help-panel.isActive{
        width: 20rem;
    }
    .panel-inner {
        /* Fixed width so the content never reflows while the outer box shrinks. */
        display: flex;
        flex-direction: column;
        row-gap: var(--space-4);
        flex: 0 0 auto;
        width: 20rem;
        height: 100%;
        min-height: 0;
        padding: var(--space-3);
        border-left: var(--divider);
        box-sizing: border-box;
    }
    .header {
        flex: 0 0 auto;
        padding: 0 var(--space-2);
    }
    .header .title-wrapper{
        display: grid;
        grid-template-columns: auto 1fr auto;
        flex-grow: 0;
        align-items: center;
        height: 2.5rem;
        gap: var(--space-2);
    }
    .header .title-wrapper .title{
        font-size: var(--font-size-nav);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .header .icon-wrapper{
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        width: 1.125rem;
        color: var(--color-text-muted);
    }
    .header .icon{
        font-size: var(--font-size-base);
    }
    .close-btn{
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: var(--corner-sm);
        color: var(--color-text-muted);
        cursor: pointer;
        transition:
            background-color var(--duration-fast),
            color var(--duration-fast);
    }
    .close-btn:hover{
        background-color: var(--color-hover);
        color: var(--color-text);
    }
    .header .description{
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        line-height: var(--line-height-normal);
    }
    .content{
        display: flex;
        flex-direction: column;
        /* The scroll region: takes its natural height, but `min-height: 0`
           lets it shrink below content size so `overflow-y` can kick in once
           the chatbox has claimed its minimum height. */
        flex: 0 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        row-gap: var(--space-1);
    }
    .chatbox-wrapper{
        /* Fills leftover space when the content is short, and shrinks as the
           content grows — but no further than the chatbox's own min-height
           (its automatic flex minimum), at which point the content scrolls. */
        flex: 1 1 auto;
    }
</style>
