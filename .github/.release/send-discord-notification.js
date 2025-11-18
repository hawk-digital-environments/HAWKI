const core = require('@actions/core');
const fetch = require('node-fetch').default;

/*
 * A lot of code in this file has been adapted from https://github.com/SethCohen/github-releases-to-discord
 * Licensed under the MIT License.
 * I sadly could not use the action directly, as it always expected to be called in a workflow triggered by a GitHub Release event.
 */

/**
 * Removes carriage return characters.
 * @param {string} text The input text.
 * @returns {string} The text without carriage return characters.
 */
const removeCarriageReturn = (text) => text.replace(/\r/g, '');

/**
 * Removes HTML comments.
 * @param {string} text The input text.
 * @returns {string} The text without HTML comments.
 */
const removeHTMLComments = (text) => text.replace(/<!--.*?-->/gs, '');

/**
 * Reduces redundant newlines and spaces.
 * Keeps a maximum of 2 newlines to provide spacing between paragraphs.
 * @param {string} text The input text.
 * @returns {string} The text with reduced newlines.
 */
const reduceNewlines = (text) => text.replace(/\n\s*\n/g, (ws) => {
    const nlCount = (ws.match(/\n/g) || []).length;
    return nlCount >= 2 ? '\n\n' : '\n';
});

/**
 * Converts @mentions to GitHub profile links for valid GitHub usernames.
 * @param {string} text The input text.
 * @returns {string} The text with valid @mentions converted to links.
 */
const convertMentionsToLinks = (text) => text.replace(
    /(?<![/@\w])@((?!-)(?!.*?--)[a-zA-Z0-9](?:-?[a-zA-Z0-9]){0,37})(?![.\w/-])(?!.*\])/g,
    (match, name) => `[@${name}](https://github.com/${name})`
);

/**
 * Removes any GitHub PR, commit, or issue links from the text, including markdown links.
 * @param {string} text The input text.
 * @returns {string} The text without GitHub PR and commit links.
 */
const removeGithubReferenceLinks = (text) => text
    // Remove markdown links to PRs, commits, and issues
    .replace(/\[[^\]]*\]\(https:\/\/github\.com\/[^(\s)]+\/pull\/\d+\)/g, '')
    .replace(/\[[^\]]*\]\(https:\/\/github\.com\/[^(\s)]+\/commit\/\w+\)/g, '')
    .replace(/\[[^\]]*\]\(https:\/\/github\.com\/[^(\s)]+\/issues\/\d+\)/g, '')
    // Remove bare PR, commit, and issue URLs
    .replace(/https:\/\/github\.com\/[^(\s)]+\/pull\/\d+/g, '')
    .replace(/https:\/\/github\.com\/[^(\s)]+\/commit\/\w+/g, '')
    .replace(/https:\/\/github\.com\/[^(\s)]+\/issues\/\d+/g, '')
    // Remove empty parentheses left behind
    .replace(/\(\s*\)/g, '');

/**
 * Reduces headings to a smaller format if 'reduce_headings' is enabled.
 * Converts H3 to bold+underline, H2 to bold.
 * @param {string} text The input text.
 * @returns {string} The text with reduced heading sizes.
 */
const reduceHeadings = (text) => text
    .replace(/^###\s+(.+)$/gm, '**__$1__**') // Convert H3 to bold + underline
    .replace(/^##\s+(.+)$/gm, '**$1**');     // Convert H2 to bold

/**
 * Converts PR, issue, and changelog links to markdown format, ignoring existing markdown links.
 * - PR links: `https://github.com/OWNER/REPO/pull/1` -> `[PR #1](https://github.com/OWNER/REPO/pull/1)`
 * - Issue links: `https://github.com/OWNER/REPO/issues/1` -> `[Issue #30](https://github.com/OWNER/REPO/issues/1)`
 * - Changelog links: `https://github.com/OWNER/REPO/compare/v1.0.0...v1.1.0` -> `[v1.0.0...v1.1.0](https://github.com/OWNER/REPO/compare/v1.0.0...v1.1.0)`
 * @param {string} text The input text.
 * @returns {string} The text with links converted to markdown format.
 */
const convertLinksToMarkdown = (text) => {
    // Extract existing markdown links and replace them with placeholders
    const markdownLinks = [];
    const textWithoutMarkdownLinks = text.replace(/\[.*?\]\(.*?\)/g, (link) => {
        markdownLinks.push(link);
        return `__MARKDOWN_LINK_PLACEHOLDER_${markdownLinks.length - 1}__`;
    });

    // Convert standalone PR, issue, and changelog URLs to markdown format
    let processedText = textWithoutMarkdownLinks
        .replace(/https:\/\/github\.com\/([\w-]+)\/([\w-]+)\/pull\/(\d+)/g, (match, owner, repo, prNumber) => `[PR #${prNumber}](${match})`)
        .replace(/https:\/\/github\.com\/([\w-]+)\/([\w-]+)\/issues\/(\d+)/g, (match, owner, repo, issueNumber) => `[Issue #${issueNumber}](${match})`)
        .replace(/https:\/\/github\.com\/([\w-]+)\/([\w-]+)\/compare\/([v\w.-]+)\.\.\.([v\w.-]+)/g, (match, owner, repo, fromVersion, toVersion) => `[${fromVersion}...${toVersion}](${match})`);

    // Reinsert the original markdown links
    return processedText.replace(/__MARKDOWN_LINK_PLACEHOLDER_(\d+)__/g, (match, index) => markdownLinks[parseInt(index, 10)]);
};

const limitString = (str, maxLength) => {
    if (str.length <= maxLength) return str;
    return str.substring(0, maxLength - 1) + '…';
};

const buildEmbedMessage = (name, html_url, description) => {
    return {
        title: limitString(name, 256),
        url: html_url,
        description: limitString(description, 4096),
        footer: {}
    };
};

const buildRequestBody = (embedMsg) => {
    return {
        embeds: [embedMsg],
        ...(core.getInput('content') && {content: core.getInput('content')})
    };
};

const sendWebhook = async (webhookUrl, requestBody) => {
    try {
        const response = await fetch(`${webhookUrl}?wait=true`, {
            method: 'POST',
            body: JSON.stringify(requestBody),
            headers: {'Content-Type': 'application/json'}
        });
        if (!response.ok) {
            const data = await response.json();
            core.setFailed(`Discord webhook error: ${JSON.stringify(data)}`);
        }
    } catch (err) {
        core.setFailed(err.message);
    }
};

/**
 * Stylizes a markdown body into an appropriate embed message style.
 * @param {string} description The description to format.
 * @returns {string} The formatted description.
 */
const formatDescription = (description) => {
    let edit = removeCarriageReturn(description);
    edit = removeHTMLComments(edit);
    edit = reduceNewlines(edit);
    edit = removeGithubReferenceLinks(edit);
    edit = convertMentionsToLinks(edit);
    edit = convertLinksToMarkdown(edit);
    edit = edit.trim();
    edit = reduceHeadings(edit);

    return edit;
};

async function run() {
    try {
        // Read data from environment variables populated by workflow
        const webhookUrl = core.getInput('webhook_url', {required: true});
        const name = core.getInput('release_name', {required: true});
        const body = core.getInput('release_body', {required: true});
        const url = core.getInput('release_url', {required: true});
        const description = formatDescription(body);
        const embedMsg = buildEmbedMessage(name, url, description);
        const requestBody = buildRequestBody(embedMsg);

        await sendWebhook(webhookUrl, requestBody);

    } catch (error) {
        core.setFailed(error.message);
    }
}

run();
