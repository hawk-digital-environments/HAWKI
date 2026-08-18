<script lang="ts">
import AvatarBuilderPreview from "./AvatarBuilderPreview.svelte";
import AvatarBuilderSymbolComposer from "./AvatarBuilderSymbolComposer.svelte";
import {type AssistantAvatar} from "$lib/plugins/assistants/types/assistant/AssistantAvatar";
import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
const {__} = useTranslator();
const builder = useBuilderContext();

let assistantAvatar = $derived(
    builder.draft.avatar
);


function handleAvatarChange(avatar: AssistantAvatar) {
    assistantAvatar = avatar;
    builder.set('avatar', $state.snapshot(avatar));
}

</script>

<div class="input-container" class:renderBlock={true}>
    <p class="label">{__('assistants.builder.general.avatar_title')}</p>
    <p class="description">{__('assistants.builder.general.avatar_description')}</p>

    <AvatarBuilderPreview
        assistantAvatar={assistantAvatar}
    />
    <AvatarBuilderSymbolComposer
            bind:assistantAvatar
            onchange={handleAvatarChange}

    />
</div>
