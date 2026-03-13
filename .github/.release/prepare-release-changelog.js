const fs = require('fs');
const path = require('path');
const {execSync} = require('child_process');
const core = require('@actions/core');
const semver = require('semver');

const TEMPLATE_FIRST_LINE = '# vX.Y.Z';

const NEXT_TEMPLATE = `# vX.Y.Z

### What's New

- The main new features and changes in this version.

### Quality of Life

- Improvements and enhancements that improve the user experience.

### Bugfix

- List of bugs that have been fixed in this version.

### Deprecation

- List of features or functionalities that have been deprecated in this version.
`;

function run() {
    const version = process.argv[2];

    if (!version) {
        core.setFailed('No version argument provided. Usage: node prepare-release-changelog.js <semver>');
        process.exit(1);
    }

    if (!semver.valid(version)) {
        core.setFailed(
            `"${version}" is not a valid semver version. ` +
            'Please provide a version in the format X.Y.Z (e.g. 2.1.0).'
        );
        process.exit(1);
    }

    // Working directory is .github/.release, so repo root is two levels up
    const repoRoot = path.resolve(process.cwd(), '../..');
    const changelogDir = path.join(repoRoot, '_changelog');
    const nextMdPath = path.join(changelogDir, 'next.md');
    const nextUpgradeMdPath = path.join(changelogDir, 'next-upgrade.md');
    const versionMdPath = path.join(changelogDir, `${version}.md`);
    const versionUpgradeMdPath = path.join(changelogDir, `${version}-upgrade.md`);

    // 1. next.md must exist
    if (!fs.existsSync(nextMdPath)) {
        core.setFailed(
            'No next.md file found in _changelog/. ' +
            'Please write a changelog entry in next.md before starting a release.'
        );
        process.exit(1);
    }

    const nextMdContent = fs.readFileSync(nextMdPath, 'utf8');

    // 2. next.md must not be the unmodified template
    if (nextMdContent.trim() === NEXT_TEMPLATE.trim()) {
        core.setFailed(
            'next.md still contains only the unmodified template content. ' +
            'Please fill in the changelog entry for this release before proceeding.'
        );
        process.exit(1);
    }

    // 3. The target version file must not already exist
    if (fs.existsSync(versionMdPath)) {
        core.setFailed(
            `_changelog/${version}.md already exists. ` +
            'Did you already prepare this release? If not, remove the file and try again.'
        );
        process.exit(1);
    }

    // 4. Replace the template header if it was left unchanged
    const lines = nextMdContent.split('\n');
    if (lines[0].trim() === TEMPLATE_FIRST_LINE) {
        core.info(`Template header detected on first line. Replacing with "# v${version}".`);
        lines[0] = `# v${version}`;
    }
    const versionMdContent = lines.join('\n');

    // 5. Write the content to $VERSION.md
    fs.writeFileSync(versionMdPath, versionMdContent);
    core.info(`Created _changelog/${version}.md.`);

    // 6 & 7. Handle next-upgrade.md if it exists; silently skip if it doesn't
    if (fs.existsSync(nextUpgradeMdPath)) {
        core.info('Found next-upgrade.md — processing.');
        const upgradeLines = fs.readFileSync(nextUpgradeMdPath, 'utf8').split('\n');

        const firstH1Index = upgradeLines.findIndex(line => /^#\s/.test(line));
        if (firstH1Index !== -1) {
            upgradeLines[firstH1Index] = `# Upgrading to v${version}`;
        } else {
            // No existing h1 — prepend one
            upgradeLines.unshift(`# Upgrading to v${version}`, '');
        }

        fs.writeFileSync(versionUpgradeMdPath, upgradeLines.join('\n'));
        fs.unlinkSync(nextUpgradeMdPath);
        core.info(`Created _changelog/${version}-upgrade.md.`);
    } else {
        core.info('No next-upgrade.md found — skipping.');
    }

    // Remove next.md before writing the fresh template
    fs.unlinkSync(nextMdPath);

    // 8. Write a fresh next.md from the template
    fs.writeFileSync(nextMdPath, NEXT_TEMPLATE);
    core.info('Created fresh next.md from template.');

    // 9. Commit and push directly to development
    const execOpts = {cwd: repoRoot, stdio: 'inherit'};
    execSync('git config --local user.email "action@github.com"', execOpts);
    execSync('git config --local user.name "GitHub Action"', execOpts);
    execSync('git add _changelog/', execOpts);
    execSync(`git commit -m "chore: Prepare changelog for release v${version}"`, execOpts);
    execSync('git push origin development', execOpts);
    core.info('Changelog changes committed and pushed to development.');
}

run();
