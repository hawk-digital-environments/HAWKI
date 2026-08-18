<!--
@component Renders a single chat message body (markdown, code blocks, citations, ...) as a
`<svelte-snippet>` entry point registered by `core.plugin.ts`. It is a thin shell: initial content
comes in via props, but while streaming, the legacy chat JS pushes incremental updates onto the
mounted `<svelte-snippet>` DOM element via two `CustomEvent`s so the component doesn't need to be
remounted for every chunk:
- `messageUpdate` (`CustomEvent<string>`) — replaces the message text with the latest partial content.
- `doneStreaming` (`CustomEvent<{ text: string, citations?: UrlCitation[] }>`) — sets the final text
  and citations and switches the body out of streaming mode.

Also (ab)used directly as a generic markdown renderer outside of chat messages
(see `public/js/syntax_modifier.js`), since it already renders markdown correctly.

Usage (from Blade, non-streaming):
```blade
<x-svelte type="MessageBody" :props="['message' => $text, 'isStreaming' => false]" />
```

Usage (from legacy JS, streaming a live AI response — see `public/js/ai_chat_functions.js`):
```js
const snippet = document.createElement('svelte-snippet');
snippet.setAttribute('type', 'MessageBody');
snippet.setProps({message: '', isStreaming: true});
container.appendChild(snippet);
// ...as chunks arrive:
snippet.dispatchEvent(new CustomEvent('messageUpdate', {detail: partialText}));
// ...once the stream ends:
snippet.dispatchEvent(new CustomEvent('doneStreaming', {detail: {text: finalText, citations}}));
```
-->
<script lang="ts">

    import type {HTMLSvelteSnippetElement} from '$lib/legacy/svelteSnippetLoader.js';
    import {onMount} from 'svelte';
    import type {UrlCitation} from '@hawk-hhg/hawki-svelte-components';
    import MessageBody from '$plugins/core/modules/chat/components/message/MessageBody.svelte';

    interface Props {
        /** Initial message text (markdown). Only read once on mount; subsequent updates arrive via the `messageUpdate`/`doneStreaming` DOM events on `root`, not via prop changes. */
        message: string;
        /** Initial citations to render alongside the message, e.g. web-search results referenced in the text. */
        citations?: Array<UrlCitation>;
        /** Whether the message is still being streamed in (e.g. a live AI response). While true, expect further `messageUpdate` events until a `doneStreaming` event arrives. */
        isStreaming?: boolean;
        /** The `<svelte-snippet>` root element (always injected by the snippet loader); used here to listen for the legacy streaming DOM events. */
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
        root.addEventListener('messageUpdate', handleMessageUpdate as EventListener);
        root.addEventListener('doneStreaming', handleDoneStreaming as EventListener);

        return () => {
            root.removeEventListener('messageUpdate', handleMessageUpdate as EventListener);
            root.removeEventListener('doneStreaming', handleDoneStreaming as EventListener);
        };
    });
</script>

<MessageBody
    message={message}
    citations={citations}
    isStreaming={isStreaming}
/>
