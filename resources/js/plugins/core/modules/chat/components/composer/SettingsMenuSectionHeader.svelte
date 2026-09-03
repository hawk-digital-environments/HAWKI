<!--
  @component Heading row for a section of the `SettingsMenu` panel: a small
  title on the left and an optional "reset to default" icon button on the
  right. The reset button is only rendered when `onReset` is given; it is
  disabled (and removed from the tab order) while `resetDisabled` is true,
  i.e. while the section is already at its default.

  ```svelte
  <SettingsMenuSectionHeader
      title={__('chat.composer.settings.settingsHeading')}
      resetTooltip={__('chat.composer.settings.resetModelSettings')}
      resetDisabled={!modelParameters.isModified}
      onReset={() => modelParameters.reset()}
  />
  ```
-->
<script lang="ts">
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import UndoIcon from '$lib/components/ui/icons/iconset/UndoIcon.svelte';

    interface Props {
        /** Section title. */
        title: string;
        /** Tooltip of the reset button. Required when `onReset` is set. */
        resetTooltip?: string;
        /** Disables the reset button, e.g. when the section is unchanged. */
        resetDisabled?: boolean;
        /** Renders the reset button and handles its click. */
        onReset?: () => void;
    }

    const {title, resetTooltip, resetDisabled = false, onReset}: Props = $props();
</script>

<div class="section-header">
    <h4 class="section-heading">{title}</h4>
    {#if onReset}
        <ButtonWithTooltip
            tooltip={resetTooltip ?? ''}
            iconLeft={UndoIcon}
            onclick={onReset}
            variant="iconGhost"
            disabled={resetDisabled}
            tabindex={resetDisabled ? -1 : 0}
            class="section-reset-button"
        ></ButtonWithTooltip>
    {/if}
</div>

<style>
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2, calc(0.25rem * 2));
    }

    .section-heading {
        margin: 0;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    :global(.section-reset-button) {
        width: auto;
        height: auto;
    }
</style>
