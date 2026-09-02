<!--
  @component Presentational row for one AI tool or capability: its icon on a swatch,
  display name, and a one-line description. Tools that are already active darken their
  swatch and carry the check over the icon. The swatch is the one `AssistantRow` seats its emoji on, so a tool
  row and an assistant row read as the same kind of thing.

  Deliberately renders no interactive element of its own, so it can sit inside whatever
  wrapper the surrounding list needs — currently the option rows of `ToolMentionPopup`.

  ## Usage
  ```svelte
  <ToolRow tool={entry.tool} checked={entry.active}/>
  ```
-->
<script lang="ts">
    import type {AiToolOrCapability} from '$plugins/core/stores/aiToolStoreData.js';
    import ToolIcon from '$plugins/core/modules/chat/components/composer/utils/ToolIcon.svelte';

    interface Props {
        /** The tool or capability to render. */
        tool: AiToolOrCapability;
        /** When true, the swatch darkens and a check is laid over the icon (tool enabled). */
        checked?: boolean;
    }

    const {tool, checked = false}: Props = $props();
</script>

<span class="tool-row">
    <ToolIcon tool={tool} swatch checked={checked}/>
    <span class="tool-row-text">
        <span class="tool-row-label">{tool.displayName}</span>
        <span class="tool-row-description">{tool.description}</span>
    </span>
</span>

<style>
    .tool-row {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        flex: 1;
    }

    .tool-row-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
        /* Matches `AssistantRow`: name and description read as one block, tighter than
           the menu's default leading. */
        line-height: var(--line-height-tight);
    }

    /* Set on the lines themselves, not just the column: the surrounding menu row sets its
       own leading, and an inherited value loses to it. */
    .tool-row-label,
    .tool-row-description {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: var(--line-height-tight);
    }

    .tool-row-description {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

</style>
