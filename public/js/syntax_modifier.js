function escapeHTML(text) {
    return text.replace(/[<>&"']/g, function (match) {
        return {
            '&': '&amp;',
            '"': '&quot;',
            '\'': '&#039;',
            '<': '&lt;',
            '>': '&gt;'
        }[match];
    });
}

function getMarkdownRendererHtml(text) {
    // I abuse the MessageBody snippet to render markdown, because it is already set up to do so.
    // This whole construct is temporary anyway and in the svelte app we can use the underlying markdown renderer directly.

    const props = {
        message: text,
        isStreaming: false
    };
    const propsAttributeString = JSON.stringify(props).replace(/"/g, '&quot;');

    return `<svelte-snippet type="MessageBody" props="${propsAttributeString}"></svelte-snippet>`;
}
