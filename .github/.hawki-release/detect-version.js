const fs = require('fs');
const path = require('path');
const semver = require('semver');
const core = require('@actions/core');

function detectLatestVersion() {
    const changelogDir = path.join(process.cwd(), '../../_changelog');

    try {
        const files = fs.readdirSync(changelogDir);
        core.info(`Found ${files.length} files in _changelog directory`);

        // Filter files that match semver pattern (x.y.z.md)
        const versionFiles = files.filter(file => {
            if (!file.endsWith('.md')) return false;
            if (file.includes('-upgrade')) return false;

            const versionString = file.replace('.md', '');
            const isValid = semver.valid(versionString) !== null;

            if (isValid) {
                core.info(`Valid version file found: ${file}`);
            }

            return isValid;
        });

        if (versionFiles.length === 0) {
            core.info('No valid version files found');
            return '';
        }

        // Extract versions and sort them
        const versions = versionFiles.map(file => file.replace('.md', ''));
        const sortedVersions = versions.sort(semver.rcompare);

        core.info(`Latest version detected: ${sortedVersions[0]}`);
        return sortedVersions[0];
    } catch (error) {
        core.setFailed(`Error reading changelog directory: ${error.message}`);
        return '';
    }
}

const latestVersion = detectLatestVersion();
core.setOutput('version', latestVersion);
