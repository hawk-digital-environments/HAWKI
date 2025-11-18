import semver from 'semver';

export function changelogSorter(
    docs,
    githubOrganization,
    githubProject
) {
    const versionDocs = {};
    let indexDoc = null;
    const versionRegex = /^(\d+\.\d+\.\d+)(?:-upgrade)?$/;

    for (const doc of docs) {
        if (doc.id === 'index') {
            indexDoc = doc;
            continue;
        }

        const match = doc.id.match(versionRegex);
        if (match) {
            const versionNumber = match[1];
            const isUpgrade = doc.id.endsWith('-upgrade');

            if (!versionDocs[versionNumber]) {
                versionDocs[versionNumber] = {};
            }

            if (isUpgrade) {
                versionDocs[versionNumber].upgrade = doc;
            } else {
                versionDocs[versionNumber].main = doc;
            }
        }
    }

    // Filter out versions that don't have main docs and sort by semver (latest first)
    const validVersions = Object.keys(versionDocs)
        .filter(version => versionDocs[version].main)
        .sort(semver.rcompare);

    // Group by major version
    const majorVersionGroups = validVersions.reduce((acc, version) => {
        const major = semver.major(version);
        if (!acc[major]) {
            acc[major] = [];
        }
        acc[major].push(version);
        return acc;
    }, {});

    // Sort major versions descending (latest major first)
    const sortedMajorVersions = Object.keys(majorVersionGroups)
        .sort((a, b) => b - a);

    // Build the sidebar structure
    const sidebarItems = sortedMajorVersions.map(major => ({
        type: 'category',
        label: `${major}.x.x`,
        collapsible: true,
        collapsed: true,
        items: majorVersionGroups[major].map(version => {
            const versionData = versionDocs[version];
            const items = [
                {
                    type: 'doc',
                    id: versionData.main.id,
                    label: 'Release Notes'
                }
            ];

            if (versionData.upgrade) {
                items.push({
                    type: 'doc',
                    id: versionData.upgrade.id,
                    label: 'Upgrade Guide'
                });
            }

            items.push({
                type: 'link',
                label: 'GitHub Release',
                href: `https://github.com/${githubOrganization}/${githubProject}/releases/tag/${version}`
            });

            return {
                type: 'category',
                label: version,
                collapsible: true,
                collapsed: true,
                items: items
            };
        })
    }));

    // Ensure the first top-level item is expanded
    if (sidebarItems.length > 0) {
        sidebarItems[0].collapsed = false;
    }

    if (indexDoc) {
        sidebarItems.unshift({
            type: 'doc',
            id: indexDoc.id,
            label: 'Changelog'
        });
    }

    return sidebarItems;
}
