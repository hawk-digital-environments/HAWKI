<script lang="ts">
import AvatarBuilderPreview from "./AvatarBuilderPreview.svelte";
import AvatarBuilderSymbolComposer from "./AvatarBuilderSymbolComposer.svelte";
import {type AssistantAvatar} from "$lib/plugins/assistants/types/assistant/AssistantAvatar";
import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
import InputError from "$lib/plugins/assistants/components/inputError/InputError.svelte";
const {__} = useTranslator();
const builder = useBuilderContext();

let assistantAvatar = $derived(
    builder.draft.avatar
);

// Inline server validation error for the avatar save (e.g. name too long, or
// the "an avatar already exists for this assistant" uniqueness conflict) —
// same pattern as BuilderInput's fieldHeader, just not routed through it
// since the avatar isn't a generic field.
let error = $derived(builder.validator.errorFor('avatar'));

function handleAvatarChange(avatar: AssistantAvatar) {
    assistantAvatar = avatar;
    builder.set('avatar', $state.snapshot(avatar));
}

</script>

<div class="input-container" class:renderBlock={true}>
    <div class="field-header">
        <p class="label">{__('assistants.builder.general.avatar_title')}</p>
        <InputError message={error} />
    </div>
    <p class="description">{__('assistants.builder.general.avatar_description')}</p>

    <AvatarBuilderPreview
        assistantAvatar={assistantAvatar}
    />
    <AvatarBuilderSymbolComposer
            bind:assistantAvatar
            onchange={handleAvatarChange}

    />
</div>

<style>
    .field-header {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
</style>
