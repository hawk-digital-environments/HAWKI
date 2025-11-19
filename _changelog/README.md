# How to release a new version of HAWKI

The main work of release ing a new version of HAWKI is done using a GitHub action workflow. This workflow is triggered by pushing something into the "main" branch. If your commit contains a version file in the `_changelog` folder, that is not yet represented by a git tag, the workflow will start the release process. If you push to `main` without a new version file, nothing will happen.

> 🚨 SUPER-IMPORTANT: DO NOT squash the PR into `main` when merging!
> Always create a merge commit, otherwise everything gets messed up.

## Writing a changelog entry

To create a new changelog entry, create a new markdown file in the `_changelog` folder. The file name must be the `semver` version number you want to release, e.g. `2.1.0.md`. The content of the file should contain the changelog entry in markdown format.

### Which version number to use?

Always adhere to [Semantic Versioning](https://semver.org/) when choosing a version number. In short:

**Patch version Z (x.y.Z | x > 0)** MUST be incremented if only backward compatible bug fixes are introduced. A bug fix is defined as an internal change that fixes incorrect behavior.

**Minor version Y (x.Y.z | x > 0)** MUST be incremented if new, backward compatible functionality is introduced to the public API. It MUST be incremented if any public API functionality is marked as deprecated. It MAY be incremented if substantial new functionality or improvements are introduced within the private code. It MAY include patch level changes. Patch version MUST be reset to 0 when minor version is incremented.

**Major version X (X.y.z | X > 0)** MUST be incremented if any backward incompatible changes are introduced to the public API. It MAY also include minor and patch level changes. Patch and minor versions MUST be reset to 0 when major version is incremented.

### How to track changes?

There SHOULD always be a `next.md` file in the `_changelog` folder. This file is used to track changes that are planned for the next release. Before creating a new pull request for the `development` branch, simply add your changes to the `next.md` file. When you are ready to release a new version, copy the content of the `next.md` file into a new version file with the appropriate version number, and then clear the content of the `next.md` file.

### Changelog template

Here is a simple template you can use for your changelog entry:

```markdown
# vX.Y.Z

### What's New

- The main new features and changes in this version.

### Quality of Life

- Improvements and enhancements that improve the user experience.

### Bugfix

- List of bugs that have been fixed in this version.

### Deprecation

- List of features or functionalities that have been deprecated in this version.
```

## Providing upgrade instructions

If your new version requires special upgrade instructions, you can provide them in a separate markdown file in the `_changelog` folder. The file name must be the same as the version file, but with the suffix `-upgrade`, e.g. `2.1.0-upgrade.md`. The content of the file should contain the upgrade instructions in markdown format.

## What the release workflow does

When you push a new version file to the `main` branch, the release workflow will:

1. Read the list of files in the `_changelog` folder and determine the latest version to release.
2. Check if there is a git tag for that version. If there is, the workflow will exit without doing anything.
3. If there is no git tag for that version, the workflow will continue...
4. Update the `config/hawki_version.json` file with the new version number.
5. It will push the updated `hawki_version.json` file back to the `main` branch.
6. Create a new git tag for the new version.
7. Create a new release in GitHub with the content of the changelog file as the release notes.
8. Build and push new docker images to Docker Hub with the new version tag.
9. Yell out the release in the #update channel on our HAWKI Community Discord server.
