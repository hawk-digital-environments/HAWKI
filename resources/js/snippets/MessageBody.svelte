<script lang="ts">

    import type {HTMLSvelteSnippetElement} from '$lib/svelteSnippetLoader.js';
    import {onMount} from 'svelte';
    import type {UrlCitation} from '$lib/components/ui/citations/types.js';
    import MessageBody from '$lib/components/chat/message/MessageBody.svelte';

    interface Props {
        message: string;
        citations?: Array<UrlCitation>;
        isStreaming?: boolean;
        root: HTMLSvelteSnippetElement;
    }

    let {
        message: initialMessage,
        isStreaming: initialIsStreaming,
        citations: initialCitations,
        root
    }: Props = $props();

    // svelte-ignore state_referenced_locally
    let isStreaming = $state(initialIsStreaming || false);
    // svelte-ignore state_referenced_locally
    let message = $state(initialMessage);
    // svelte-ignore state_referenced_locally
    let citations = $state(initialCitations ?? []);

    function handleMessageUpdate(event: CustomEvent<string>) {
        message = event.detail;
    }

    function handleDoneStreaming(event: CustomEvent<{ text: string, citations?: Array<UrlCitation> }>) {
        message = event.detail.text;
        citations = event.detail.citations ?? [];
        isStreaming = false;
    }

    onMount(() => {
        root.addEventListener('messageUpdate', handleMessageUpdate);
        root.addEventListener('doneStreaming', handleDoneStreaming);

        return () => {
            root.removeEventListener('messageUpdate', handleMessageUpdate);
            root.removeEventListener('doneStreaming', handleDoneStreaming);
        };
    });
</script>

<MessageBody
    message={message}
    citations={citations}
    isStreaming={isStreaming}
/>
