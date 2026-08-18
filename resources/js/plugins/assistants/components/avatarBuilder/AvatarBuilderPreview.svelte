<script lang="ts">

    import { useBuilderContext } from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import type {AssistantAvatar} from "$lib/plugins/assistants/types/assistant/AssistantAvatar";
    import AssistantAvatarIcon from "./AssistantAvatarIcon.svelte";
    import AssistantBanner from "./AssistantBanner.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    const {__} = useTranslator();
    const builder = useBuilderContext();

    let {
        assistantAvatar,
    }= $props <{
        assistantAvatar: AssistantAvatar;
    }>();

    const name = $derived(
        builder.draft.name !== ''
            ? builder.draft.name
            : __('assistants.builder.general.avatar_preview_name')
    );
    const handle = $derived(builder.draft.handle);
</script>

<!-- App-store-style cover card: hero banner up top, the avatar icon overlapping
     its lower edge, and the name/handle on the neutral footer — mirroring the
     store listing and detail header. -->
<div class="preview-card">
    <div class="cover">
        <AssistantBanner assistantAvatar={assistantAvatar} />
    </div>

    <div class="avatar-wrap">
        <AssistantAvatarIcon size="small" assistantAvatar={assistantAvatar} />
    </div>

    <div class="info-container">
        <p class="name">{name}</p>
        <p class="handle">{handle ? `@${handle}` : __('assistants.builder.general.avatar_preview_handle')}</p>
    </div>
</div>


<style>
    .preview-card {
        position: relative;
        overflow: hidden;
        background-color: var(--color-surface-raised);
        border: var(--border);
        border-radius: var(--corner-lg);
    }

    /* Full-bleed hero cover. */
    .cover {
        display: block;
    }

    /* Avatar straddles the cover / footer boundary, lifted by a surface ring. */
    .avatar-wrap {
        position: absolute;
        top: calc(6rem - 1.75rem);
        left: var(--space-4);
        padding: 3px;
        background: var(--color-surface-raised);
        border-radius: calc(var(--corner-md) + 3px);
        box-shadow: var(--elevation-1, 0 1px 3px rgba(0, 0, 0, 0.12));
    }

    /* Footer leaves room for the overlapping avatar on the left. */
    .info-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 2px;
        min-height: 2rem;
        padding: var(--space-2) var(--space-4) var(--space-4);
        padding-left: calc(var(--space-4) + 3rem + var(--space-3));
    }

    .name {
        font-size: var(--font-size-base);
        font-weight: bold;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .handle {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>
