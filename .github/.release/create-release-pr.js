const fs = require('fs');
const path = require('path');
const core = require('@actions/core');
const github = require('@actions/github');

async function run() {
    const version = process.argv[2];

    if (!version) {
        core.setFailed('No version argument provided.');
        process.exit(1);
    }

    const token = process.env.GH_TOKEN;
    if (!token) {
        core.setFailed('GH_TOKEN environment variable is required.');
        process.exit(1);
    }

    const repositoryEnv = process.env.GITHUB_REPOSITORY;
    if (!repositoryEnv) {
        core.setFailed('GITHUB_REPOSITORY environment variable is required.');
        process.exit(1);
    }

    const [owner, repo] = repositoryEnv.split('/');
    const octokit = github.getOctokit(token);

    // Use the freshly created version changelog file as the PR body
    const repoRoot = path.resolve(process.cwd(), '../..');
    const versionMdPath = path.join(repoRoot, '_changelog', `${version}.md`);
    const prBody = fs.readFileSync(versionMdPath, 'utf8');

    // 10. Create the PR from development into main
    core.info(`Creating PR "Release v${version}" (development → main) ...`);
    const {data: pr} = await octokit.rest.pulls.create({
        owner,
        repo,
        title: `Release v${version}`,
        body: prBody,
        head: 'development',
        base: 'main'
    });
    core.info(`Created PR #${pr.number}: ${pr.html_url}`);

    // 11. Merge without squashing — a plain merge commit keeps the full history
    core.info(`Merging PR #${pr.number} using a merge commit ...`);
    await octokit.rest.pulls.merge({
        owner,
        repo,
        pull_number: pr.number,
        merge_method: 'merge',
        commit_title: `Release v${version}`,
        commit_message: `Merge development into main for release v${version}`
    });
    core.info(`PR #${pr.number} merged. The release pipeline will now detect v${version} and proceed.`);
}

run().catch(error => {
    core.setFailed(error.message);
    process.exit(1);
});
