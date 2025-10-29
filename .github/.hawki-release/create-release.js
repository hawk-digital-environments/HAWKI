const fs = require('fs');
const path = require('path');
const core = require('@actions/core');
const github = require('@actions/github');

function readChangelogContent(version) {
    const changelogPath = path.join(process.cwd(), `../../_changelog/${version}.md`);
    let content;

    try {
        content = fs.readFileSync(changelogPath, 'utf8');
        core.info(`Successfully read changelog for version ${version}`);
    } catch (error) {
        core.warning(`Error reading changelog file for version ${version}: ${error.message}`);
        return `Release ${version}`;
    }

    const headingRegex = new RegExp(`^#\\s*v?${version}\\s*\\n?`);
    content = content.replace(headingRegex, '').trim();

    const upgradeGuidePath = path.join(process.cwd(), `../../_changelog/${version}-upgrade.md`);
    if (fs.existsSync(upgradeGuidePath)) {
        const upgradeGuideText = `\n\n## Upgrade Guide\n\nFor this update, manual changes are required. Please see the [upgrade guide](https://docs.hawki.info/changelog/${version}-upgrade) for more information.`;
        content += upgradeGuideText;
    }

    return content;
}

async function createRelease(version, body) {
    try {
        const token = core.getInput('github-token') || process.env.GITHUB_TOKEN;
        const octokit = github.getOctokit(token);

        const [owner, repo] = process.env.GITHUB_REPOSITORY.split('/');

        const release = await octokit.rest.repos.createRelease({
            owner,
            repo,
            tag_name: `${version}`,
            name: `v${version}`,
            body: body,
            draft: false,
            prerelease: false
        });

        core.info(`Successfully created release v${version}`);
        core.info(`Release URL: ${release.data.html_url}`);

    } catch (error) {
        core.setFailed(`Failed to create release: ${error.message}`);
    }
}

async function main() {
    const version = process.argv[2];
    if (!version) {
        core.setFailed('Version argument is required');
        return;
    }

    if (!process.env.GITHUB_TOKEN) {
        core.setFailed('GITHUB_TOKEN environment variable is required');
        return;
    }

    if (!process.env.GITHUB_REPOSITORY) {
        core.setFailed('GITHUB_REPOSITORY environment variable is required');
        return;
    }

    const changelogContent = readChangelogContent(version);
    await createRelease(version, changelogContent);
}

main().catch(error => {
    core.setFailed(`Unexpected error: ${error.message}`);
});
