<!--
  @component One setting inside a `SettingsGroup`: a label with an optional
  description on the left and its control on the right. `tone="danger"` tints
  the label for a destructive action.

  The `control` snippet receives the ids of the label and the description so
  the control can point `aria-labelledby` / `aria-describedby` at them.

  Passing `onclick` turns the whole row into a single button — for toggles,
  where the entire row is the hit target and the control inside is only an
  indicator (e.g. `<Switch presentational/>`). Extra attributes such as `role`
  and `aria-checked` land on that button.

      <SettingsGroup>
          <SettingsRow label="Language" description="…">
              {#snippet control({labelId})}
                  <Button aria-labelledby={labelId}>English</Button>
              {/snippet}
          </SettingsRow>
      </SettingsGroup>
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface ControlIds {
        labelId: string;
        descriptionId: string | undefined;
    }

    interface Props extends Omit<HTMLAttributes<HTMLElement>, 'onclick' | 'children'> {
        /** Row label. */
        label: string;
        /** Supporting text under the label. */
        description?: string;
        /** `danger` tints the label, marking a destructive action. */
        tone?: 'default' | 'danger';
        /** The row's control; receives the label/description ids for aria wiring. */
        control?: Snippet<[ControlIds]>;
        /** Turns the whole row into a single button, e.g. for a toggle. */
        onclick?: (event: MouseEvent) => void;
    }

    const {
        label,
        description,
        tone = 'default',
        control,
        onclick,
        class: className,
        ...rest
    }: Props = $props();

    const uid = $props.id();
    const labelId = `${uid}-label`;
    const descriptionId = $derived(description ? `${uid}-description` : undefined);
    // A button row may only contain phrasing content.
    const wrapper = $derived(onclick ? 'span' : 'div');
</script>

{#snippet body()}
    <svelte:element this={wrapper} class="text">
        <span class="label" id={labelId}>{label}</span>
        {#if description}
            <span class="description" id={descriptionId}>{description}</span>
        {/if}
    </svelte:element>
    {#if control}
        <svelte:element this={wrapper} class="control">
            {@render control({labelId, descriptionId})}
        </svelte:element>
    {/if}
{/snippet}

{#if onclick}
    <button
        {...mergeProps(rest, {class: ['settings-row', 'interactive', className]})}
        type="button"
        class:danger={tone === 'danger'}
        aria-labelledby={labelId}
        aria-describedby={descriptionId}
        {onclick}
    >
        {@render body()}
    </button>
{:else}
    <div {...mergeProps(rest, {class: ['settings-row', className]})} class:danger={tone === 'danger'}>
        {@render body()}
    </div>
{/if}

<style>
    .settings-row {
        display: flex;
        align-items: center;
        gap: var(--space-3) var(--space-4);
        width: 100%;
        padding: var(--space-3) 0;
    }

    /* Rows in a group are split by a hairline. (A `+` sibling selector would
       be pruned: each row is its own component instance, so the compiler
       can't see two rows side by side.) */
    .settings-row:not(:first-child) {
        border-top: var(--divider);
    }

    .text {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: var(--space-0_5);
        min-width: 0;
    }

    .label {
        color: var(--color-text);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-normal);
    }

    .danger .label {
        color: var(--color-error);
    }

    .description {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }

    .control {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: flex-end;
    }

    .interactive {
        border: none;
        background: transparent;
        color: inherit;
        font: inherit;
        text-align: left;
        cursor: pointer;
    }
</style>
