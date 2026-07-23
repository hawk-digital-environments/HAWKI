# Changelog

Welcome to the HAWKI changelog! 🚀

Whether you're checking out what's new in the latest release or catching up after some time away, you'll find all our updates, improvements, and bug fixes organized by version in the menu on the left.

## What you'll find here

- **Version history** grouped by major releases for easy browsing
- **Upgrade instructions** right alongside each release when setup changes are needed
- **Clear descriptions** of what's changed and why it matters

## Need help?

Got questions about a release or running into issues? Our
[Discord community](https://discord.gg/zzR54sRWDE) is full of helpful folks who'd love to help out.

Happy updating! ✨

---

## For contributors

The sections below explain how the changelog and release process work for developers contributing to HAWKI. The release pipeline is powered by [hawk-pipeline-actions](https://github.com/hawk-digital-environments/hawk-pipeline-actions).

### Tracking changes day-to-day

There are two working files in this directory: `next.md` and `next-upgrade.md`. These are **living documents** — treat them like a running notepad for the next release.

**Every pull request to `development` that introduces a change worth communicating should update one or both of these files as part of the PR itself.** Don't save it for later; changelog entries written close to the actual change are far more accurate and useful.

> **Both files are automatically reset to a clean template after every release.** You will never need to manually clear or recreate them — just start filling in `next.md` for the next release cycle.

#### Which parts are written?

Lines written as `[//]: # (text)` are template hints — they are visible in your editor but render as nothing in markdown. When the pipeline processes a release, these lines are stripped first; any section that contains only those hints is then dropped entirely. Write your real content as normal (non-commented) lines anywhere under the appropriate heading.

#### `next.md` — the next release notes

This is the primary changelog file. Add bullet points under the appropriate section when your PR introduces something new, fixes a bug, or deprecates something.

#### `next-upgrade.md` — the next upgrade guide

This file is **optional**. Only fill it in if your change requires administrators to take manual action when upgrading (e.g. running a migration, changing a config value, updating an environment variable). If your change needs no upgrade steps, leave the file alone.

---

### Which version number to use?

Always follow [Semantic Versioning](https://semver.org/). In short:

**Patch — `x.y.Z`**
Increment for backward-compatible bug fixes only. A bug fix corrects incorrect behaviour without changing any public interface.

**Minor — `x.Y.0`**
Increment when new, backward-compatible functionality is introduced. Also increment when public API functionality is marked as deprecated, or when substantial internal improvements are made. Reset the patch version to `0`.

**Major — `X.0.0`**
Increment when backward-incompatible changes are introduced. Reset both minor and patch versions to `0`.

When in doubt, err on the side of a minor bump rather than a patch. A version number that's slightly higher than necessary causes no harm; a patch version that hides a breaking change causes real problems for people upgrading.

---

### Releasing a new version

Releases are triggered manually via two GitHub Actions workflows, run in order.
You will find both under **Actions** in the repository.

#### Step 1 — Create the release branch

1. Go to **Actions** → **[MANUAL] - 1. Create Release Branch**
2. Click **Run workflow**
3. Select the source branch from the **"Use workflow from"** dropdown:
    - `development` for a normal release
    - `main` for a hotfix (patch release on top of what's already in production)
4. Enter the version number (e.g. `2.1.0`) and run

The pipeline validates `next.md`, renames it to `2.1.0.md`, resets both working files to their templates, updates `config/hawki_version.json`, and pushes the result as a new `release/2.1.0` branch. You can review the branch, make last-minute corrections, or simply proceed.

#### Step 2 — Trigger the release

1. Go to **Actions** → **[MANUAL] - 2. Trigger Release**
2. Click **Run workflow**
3. Select **`release/v2.1.0`** from the **"Use workflow from"** dropdown — this is your release selector, there is no separate input field for the branch
4. Run the workflow

The pipeline validates the branch name, runs a Docker build test, then squash-merges the release branch into `main` and back into `development`, pushes the version tag, and deletes the release branch. This tag push automatically triggers the automated release pipeline, which builds the Docker image, creates the GitHub Release, and announces the update on Discord.

> **Hotfix?** The process is identical — the only difference is that you selected `main` as the source in Step 1 instead of `development`. The rest of the workflow is the same.

---

### Versioning convention

Git **tags** and working **branch names** do not carry a `v` prefix. The version `2.1.0` is tagged as `2.1.0` and the release branch is named `release/2.1.0`.

The only place the `v` prefix appears is in the **GitHub Release display name** (`v2.1.0`), which is cosmetic and matches GitHub's conventions for release pages. This is intentional and consistent with HAWKI's historical versioning schema.

| Thing               | Format            | Example                           |
|---------------------|-------------------|-----------------------------------|
| Git tag             | no prefix         | `2.1.0`                           |
| Release branch      | `release/` prefix | `release/2.1.0`                   |
| GitHub Release name | `v` prefix        | `v2.1.0`                          |
| Docker image tag    | no prefix         | `digitalenvironments/hawki:2.1.0` |
