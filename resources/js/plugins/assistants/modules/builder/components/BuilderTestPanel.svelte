<!--
  @component The builder's test chat, in the shared floating dock (see
  `ChatDock.svelte`). Stays mounted across every builder step (it lives in
  the builder layout), so a running conversation survives step changes and
  always reflects the live `draft`.

  A switch in the header flips between two conversations, both owned here so
  neither is lost when switching: the test chat with the assistant itself,
  and the guide chat that walks the creator through the setup and fills the
  builder fields (see `builderGuideChat.svelte.ts`).
-->
<script lang="ts">
    import Chatbox from '$plugins/assistants/components/testChat';
    import ChatDock from '$plugins/assistants/components/testChat/ChatDock.svelte';
    import Tabs from '$lib/components/ui/tabs/Tabs.svelte';
    import {createChatStore} from '$plugins/assistants/components/testChat/stream/chatStore.svelte.js';
    import {createChatConfig, type ChatVariant} from '$plugins/assistants/components/testChat/stream/chatConfig.svelte.js';
    import {createBuilderGuideChat} from '$plugins/assistants/modules/builder/contexts/builderGuideChat.svelte.js';
    import {KnowledgeUploader} from '$plugins/assistants/modules/builder/components/fileUpload/knowledgeUploader.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';

    interface Props {
        open: boolean;
    }

    let {open = $bindable()}: Props = $props();

    const {__} = useTranslator();
    const builder = useBuilderContext();
    const uid = $props.id();

    const testChat = createChatStore(createChatConfig(() => builder.draft));
    const guideChat = createBuilderGuideChat(builder, new KnowledgeUploader(), __, useRouter());

    let mode = $state<ChatVariant>('guide');
    const chat = $derived(mode === 'guide' ? guideChat : testChat);

    const modes = $derived([
        {key: 'guide', label: __('assistants.builder.test.mode_guide'), id: `${uid}-tab-guide`, panelId: `${uid}-panel`},
        {key: 'test', label: __('assistants.builder.test.mode_test'), id: `${uid}-tab-test`, panelId: `${uid}-panel`},
    ]);

    /** Files dragged onto the launcher open the guide, which takes them. */
    const openForFiles = (e: DragEvent): void => {
        if (!Array.from(e.dataTransfer?.types ?? []).includes('Files')) return;
        mode = 'guide';
        open = true;
    };
</script>

<ChatDock bind:open {chat}
          title={__('assistants.builder.test.title')}
          description={__('assistants.builder.test.description')}
          onLauncherDragEnter={openForFiles}>
    {#snippet header()}
        <div class="mode-switch">
            <Tabs items={modes} value={mode} onChange={(key) => mode = key as ChatVariant}
                  aria-label={__('assistants.builder.test.mode')}/>
        </div>
    {/snippet}
    <div class="mode-panel" id="{uid}-panel" role="tabpanel"
         aria-labelledby={mode === 'guide' ? `${uid}-tab-guide` : `${uid}-tab-test`}>
        <!-- Keyed so each conversation mounts its own chatbox; the chats
             themselves live above, so switching loses nothing. -->
        {#key mode}
            <Chatbox assistant={builder.draft} variant={mode} chat={chat}/>
        {/key}
    </div>
</ChatDock>

<style>
    .mode-switch {
        width: 14rem;
        max-width: 100%;
    }

    .mode-panel {
        height: 100%;
    }
</style>
