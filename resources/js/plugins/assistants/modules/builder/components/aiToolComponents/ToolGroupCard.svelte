<!--
  @component Collapsible card grouping a set of tools on the builder's model
  page — the shared chrome the MCP server cards, the capabilities card, and
  the standalone-tools card are built from.

  Header button: icon on a status swatch, label, optional description, an
  optional ":selected of :count selected" badge (visible while collapsed —
  the point of the badge), and a rotating chevron. Content sits in an
  animated grid-rows expander.

  Initial open state: the card auto-opens exactly once, when a selection
  arrives while the user has never toggled the card themselves — i.e. a
  preselected group starts expanded. Selections made *inside* the card
  require it to be open already, so they never trigger the auto-open, and
  once the user has toggled the card their choice always wins.

  ## Usage
  ```svelte
  <ToolGroupCard
      label={__('chat.composer.toolMenu.capabilitiesLabel')}
      icon={AiMagicIcon}
      selectedCount={selectedCount}
      totalCount={capabilities.length}
  >
      <CapabilitiesList ... />
  </ToolGroupCard>
  ```
-->
<script lang="ts">
    import type {IconComponent} from '$lib/components/ui/icons';
    import {StatusIcon} from '$lib/components/ui/icons';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    let {
        label,
        description = undefined,
        icon,
        selectedCount = undefined,
        totalCount = undefined,
        children,
    } = $props<{
        /** Group title shown in the header. */
        label: string;
        /** Optional muted description line under the label. */
        description?: string;
        /** Icon rendered on the status swatch (e.g. `ServerStack01Icon`). */
        icon: IconComponent;
        /** Selected entries in the group; rendered with `totalCount` as the header badge. Omit to hide the badge. */
        selectedCount?: number | undefined;
        /** Total selectable entries in the group. */
        totalCount?: number | undefined;
        /** The card's (collapsed) content. */
        children: import('svelte').Snippet;
    }>();

    let open = $state(false);

    // Auto-open once when a preselected group reports a non-zero count,
    // before the user has ever toggled the card (after that, their choice
    // wins — see the component doc). Counts can arrive late: the builder
    // draft and the tool stores hydrate asynchronously.
    let userToggled = false;
    let autoOpened = false;

    $effect(() => {
        if (userToggled || autoOpened || !(selectedCount !== undefined && selectedCount > 0)) return;
        open = true;
        autoOpened = true;
    });

    function toggle(): void {
        userToggled = true;
        open = !open;
    }

    const showsCount = $derived(selectedCount !== undefined && totalCount !== undefined);
</script>

<div class="tool-group-card">
    <button class="header" type="button" onclick={toggle} aria-expanded={open}>
        <StatusIcon {icon}/>
        <span class="text-wrapper">
            <span class="label-row">
                <span class="u-label">{label}</span>
            </span>
            {#if description}
                <p class="description">{description}</p>
            {/if}
        </span>
        {#if showsCount}
            <span class="selected-count">
                {__('assistants.builder.tools.selectedCount', {selected: String(selectedCount), count: String(totalCount)})}
            </span>
        {/if}
        <span class="chevron" class:open={open}>
            <ArrowRight01Icon size="1em"/>
        </span>
    </button>

    <div class="details-wrapper" class:active={open}>
        <div class="inner-wrapper">
            <div class="content">
                {@render children()}
            </div>
        </div>
    </div>
</div>

<style>
    .tool-group-card{
        position: relative;
        min-height: 3rem;
        border: var(--border);
        border-radius: var(--corner-md);
        padding: .75rem;
        margin-top: .5rem;
        background: var(--color-surface-raised);
    }

    .header{
        display: flex;
        flex-direction: row;
        gap: 1rem;
        align-items: center;
        width: 100%;
        cursor: pointer;
    }

    .label-row{
        display: flex;
        flex-direction: row;
        gap: .5rem;
        align-items: center;
    }

    .text-wrapper{
        flex: 1;
        min-width: 0;
        text-align: left;
    }

    .text-wrapper .u-label{
        margin-bottom: 0;
        color: var(--color-accent-text);
        text-align: center;
    }

    .text-wrapper .description{
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .selected-count{
        flex-shrink: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        white-space: nowrap;
    }

    .chevron{
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        transition: transform var(--duration-medium);
    }

    .chevron.open{
        transform: rotate(90deg);
    }

    .details-wrapper{
        display: grid;
        grid-template-rows: 0fr;
        overflow: hidden;
        width: 100%;
        margin-top: 0;
        transition: all var(--duration-medium);
    }

    .details-wrapper.active{
        grid-template-rows: 1fr;
        margin-top: .5rem;
    }

    .inner-wrapper{
        overflow: hidden;
    }
</style>
