<script lang="ts">
import AvatarBuilderPreview from "$lib/components/assistant/assistantBuilderComponents/avatarBuilder/AvatarBuilderPreview.svelte";
import AvatarBuilderSymbolComposer from "$lib/components/assistant/assistantBuilderComponents/avatarBuilder/AvatarBuilderSymbolComposer.svelte";
import {type AssistantAvatar} from "$lib/types/assistant/AssistantAvatar";
import {assistantBuilderStore} from "$lib/stores/assistants/AssistantBuilderStore.svelte.js";
    const {__} = useTranslator();


let assistantAvatar = $derived(
    assistantBuilderStore.draft.avatar
);


function handleAvatarChange(avatar: AssistantAvatar) {
    assistantAvatar = avatar;
    assistantBuilderStore.set('avatar', $state.snapshot(avatar));
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
