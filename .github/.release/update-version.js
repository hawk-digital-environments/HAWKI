const fs = require('fs');
const path = require('path');
const core = require('@actions/core');

function updateVersionFile(newVersion) {
    const versionFilePath = path.join(process.cwd(), '../../config/hawki_version.json');

    try {
        const versionData = {
            version: newVersion,
            released_at: new Date().toISOString() // Setting this helps us to avoid issues when re-releasing the same version, also it is a good indicator of when the version was released
        };

        fs.writeFileSync(versionFilePath, JSON.stringify(versionData, null, 2) + '\n');
        core.info(`Successfully updated hawki_version.json to version ${newVersion}`);
    } catch (error) {
        core.setFailed(`Error updating version file: ${error.message}`);
    }
}

const newVersion = process.argv[2];
if (!newVersion) {
    core.setFailed('Version argument is required');
    process.exit(1);
}

updateVersionFile(newVersion);
