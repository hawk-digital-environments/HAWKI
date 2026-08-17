<script lang="ts">
    import { type AssistantAvatar} from "$lib/types/assistant/AssistantAvatar";
    import { BACKGROUNDS } from "$lib/data/presets/backgrounds";
    import EmojiPicker from "$lib/components/generic/emojiPicker/EmojiPicker.svelte";
        const {__} = useTranslator();

    let {
        assistantAvatar = $bindable<AssistantAvatar>(),
        onchange
    } = $props<{
        assistantAvatar: AssistantAvatar
        onchange: (assistantAvatar: AssistantAvatar) => void;
    }>();

    function onEmojiSelect(emoji: string): void {
        onchange({ ...assistantAvatar, name: emoji });
    }

    function onBackgroundSelect(bgId: string): void {
        onchange({ ...assistantAvatar, iconCss: bgId });
    }

</script>

<div class="composer">
<div class="selector-row">
    <div class="symbol-group">
        <p class="label">{__('assistants.builder.general.avatar_symbol')}</p>
        <EmojiPicker
            onSelect={onEmojiSelect}
            ariaLabel={__('assistants.builder.general.avatar_symbol_change')}
            align="start"
            side="top"
        >
            {#snippet trigger()}
                <span class="symbol-emoji">{assistantAvatar.name}</span>
            {/snippet}
        </EmojiPicker>
    </div>

    <div class="background-group">
        <p class="label">{__('assistants.builder.general.avatar_background')}</p>
        <div class="background-list">
            {#each BACKGROUNDS as bg}
                <button class="gradient-select"
                        class:active={assistantAvatar.iconCss === bg.value}
                        style={bg.value}
                        onclick={()=>{onBackgroundSelect(bg.value)}}
                >
                    <span class="gradient-label"
                          style:color="oklch(100% 0 0)"
                    >{bg.label}</span>
                </button>
            {/each}
        </div>
    </div>
</div>
</div>


<style>
    /* Query this component's own width, so the layout adapts to the panel /
       sidebar it sits in rather than the viewport. */
    .composer{
        container-type: inline-size;
    }

    .selector-row{
        display: flex;
        flex-direction: row;
        /* Stretch so the symbol picker matches the background grid's height. */
        align-items: stretch;
        gap: 1rem;
        padding: 1rem 0 .5rem 0;
    }

    /* In a narrow column (e.g. the builder sidebar) stack the symbol picker
       above the background grid instead of squeezing them side by side. */
    @container (max-width: 30rem) {
        .selector-row{
            flex-direction: column;
            /* Let both pickers fill the full column width. */
            align-items: stretch;
        }
    }

    .symbol-group,
    .background-group{
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
    .symbol-group{
        flex: 0 0 auto;
    }
    .background-group{
        flex: 1 1 auto;
        min-width: 0;
    }

    /* Fixed width; grows vertically to fill the row alongside the background grid. */
    .symbol-group :global(.emoji-picker){
        display: flex;
        width: 6.5rem;
        flex: 1 1 auto;
        min-height: 6.5rem;
    }
    .symbol-group :global(.trigger){
        width: 100%;
        height: 100%;
        background: var(--color-surface-raised);
        border: var(--border);
        border-radius: var(--corner-sm);
        transition: background-color var(--transition-fast);
    }
    .symbol-group :global(.trigger:hover){
        background: var(--color-hover);
    }

    /* Stacked layout: let the symbol picker fill the full column width too.
       Placed after the base size rule so it wins at equal specificity. */
    @container (max-width: 30rem) {
        .symbol-group :global(.emoji-picker){
            width: 100%;
            flex: 0 0 auto;
            height: 6.5rem;
        }
    }

    .symbol-emoji{
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        line-height: 1;
    }

    .background-list{
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: .5rem;
    }
    @container (max-width: 34rem) {
        .background-list {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @container (max-width: 22rem) {
        .background-list {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    .gradient-select {
        position: relative;
        height: 3rem;
        width: 100%;
        min-width: 3rem;
        overflow: visible;
        border: var(--border-stroke-thin);
        border-radius: var(--border-radius-normal);
    }
    .gradient-select.active {
        border: var(--border-stroke-bold);
    }
    .gradient-label {
        position: absolute;
        bottom: 50%;
        left: 50%;
        transform: translateX(-50%) translateY(+50%);

        padding: 0.25rem 0.5rem;
        border-radius: var(--border-radius-small, 4px);
        font-size: 0.75rem;
        white-space: nowrap;

        opacity: 0;
        pointer-events: none;
        transition: opacity var(--transition-medium) ease;
        transition-delay: 0s;
    }

    .gradient-select:hover .gradient-label {
        opacity: 1;
        transition-delay: 1s;
    }

</style>
