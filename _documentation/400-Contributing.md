# Contributing to HAWKI

Thank you for contributing to HAWKI. This guide covers the contribution workflow, standards checklist, and review process. All participants are expected to treat others with respect and courtesy.

---

## Table of Contents

1. [How to Contribute](#how-to-contribute)
2. [Development Workflow](#development-workflow)
3. [Architecture & Code Standards](#architecture--code-standards)
4. [Testing](#testing)
5. [Frontend Code](#frontend-code)
6. [Pull Request Process](#pull-request-process)
7. [Code Review](#code-review)
8. [AI Agents](#ai-agents)
9. [Getting Help](#getting-help)

---

## How to Contribute

- **Bug reports:** Search existing issues first. Include reproduction steps, environment details, and error messages.
- **Feature suggestions:** Check existing issues. Describe the use case and why it benefits users.
- **Bug fixes:** Reference the issue in your commit.
- **New features:** Discuss in an issue before implementing. Keep scope focused.
- **Documentation:** Fix typos, clarify explanations, keep docs in sync with code.

---

## Development Workflow

### Branching Strategy

| Branch             | Purpose                                                                                                         |
|--------------------|-----------------------------------------------------------------------------------------------------------------|
| **`development`**  | **Default branch** — bleeding edge. All feature and bugfix PRs target here.                                     |
| **`main`**         | Stable release. Only updated by the release pipeline, never directly.                                           |
| **`feature/*`**    | New functionality (e.g., `feature/user-notifications`)                                                          |
| **`bugfix/*`**     | Issue fixes (e.g., `bugfix/login-validation`)                                                                   |
| **`hotfix/*`**     | Urgent fixes branched from `main` and merged back into both `main` and `development`                            |
| **`hawk/testing`** | Deployment branch for the HAWK testing environment — pushing here triggers an automated Docker build and deploy |
| **`hawk/prod`**    | Deployment branch for the HAWK production environment — same pipeline, production infrastructure                |

`development` is the default branch because it represents the current state of active work. `main` reflects what has been released. Contributors always branch from `development` and open PRs against `development`. The release process, versioning strategy, and pipeline details are described in [`_changelog/README.md`](https://github.com/hawk-digital-environments/HAWKI/blob/development/_changelog/README.md).

```bash
git checkout development && git pull upstream development
git checkout -b feature/your-feature-name
```

### Commit Messages

We follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

```
type(scope): short summary of what changed

Optional body explaining what and why (not how). Wrap at 72 characters.

Refs #123
```

**Types:** `feat` · `fix` · `docs` · `style` · `refactor` · `test` · `chore`

**Rules:** Subject line under 50 characters · reference related issues.

### Keeping Your Branch Updated

```bash
git fetch upstream
git rebase upstream/development
git push origin feature/your-feature-name --force-with-lease
```

---

## Architecture & Code Standards

HAWKI follows a lightweight Domain-Driven Design approach on top of Laravel 13 / PHP 8.3. The backend is in transition from server-rendered MVC to a pure API server for the Svelte frontend.

The full architecture documentation lives in the **[Backend section](./_documentation/Backend/)**:

| I want to understand…                                            | Read                                                                                        |
|------------------------------------------------------------------|---------------------------------------------------------------------------------------------|
| Domain structure, layer responsibilities, naming conventions, DI | [Architecture Overview](500-Backend/100-Architecture/index.md)                              |
| `@api` stability contract, `DecoratorTrait`, extension points    | [API Stability & Extension Points](500-Backend/100-Architecture/100-API-Stability.md)       |
| ServiceLocatorTrait, contextual scopes, custom translator        | [Custom Infrastructure Patterns](500-Backend/100-Architecture/250-Custom-Infrastructure.md) |
| Known coding-standard violations in the codebase                 | [Technical Debt Register](500-Backend/100-Architecture/300-Technical-Debt.md)               |
| How a request flows through every layer                          | [Life of a Request](500-Backend/150-Life-of-a-Request.md)                                   |

> The old `3-architecture/` directory contains pre-refactor documentation and does not reflect current patterns. Do not use it as a reference.

### Code Style

HAWKI follows [PSR-12](https://www.php-fig.org/psr/psr-12/) for PHP and [Prettier](https://prettier.io/) for frontend files. Run the formatters before every commit:

```bash
# Format all PHP files
bin/env style php

# Format all JS/frontend files
bin/env style js
```

> **Automated enforcement coming:** Starting with HAWKI 3.0.0, CI will reject PRs with formatting issues.

### Code Quality Checklist

Before submitting your PR:

- [ ] `declare(strict_types=1)` in every PHP file
- [ ] All parameters and return types declared
- [ ] Dependencies injected via constructor or `#[Config]` / `#[Cache]` attributes
- [ ] No facades in services, `Repository` classes, or value objects
- [ ] `ServiceLocatorTrait` used only sparingly or when it makes sense (never in services, models, or repositories)
- [ ] No `env()` calls outside config files
- [ ] All database access goes through a `Repository` class — no static Eloquent calls in services
- [ ] Models contain no business logic, no facades, no query scopes, no service locator
- [ ] Value objects are `readonly` with `from...` / `tryFrom...` factory methods
- [ ] Enums used for all constrained string or int values
- [ ] DocBlocks only where needed (complex types, non-obvious intent)
- [ ] No `now()`, `new \DateTime()`, `Carbon::now()`, or similar — use injected `CarbonClockInterface` (`Psr\Clock\ClockInterface` if the class must stay PSR-only)
- [ ] No debug statements (`dd()`, `dump()`, `var_dump()`)
- [ ] No hardcoded values (use config or constants)
- [ ] You provided good test coverage for new features and bug fixes
- [ ] You executed the code formatters

---

## Testing

### Running Tests

| What                      | In Docker                  | Locally (Composer)              |
|---------------------------|----------------------------|---------------------------------|
| Unit tests only           | `bin/env test php unit`    | `composer run test:unit`        |
| Feature tests only        | `bin/env test php feature` | `composer run test:feature`     |
| Static analysis (PHPStan) | `bin/env test php stan`    | `composer run test:stan`        |
| All of the above          | `bin/env test php all`     | *(run each command separately)* |

### PHPUnit Conventions

HAWKI has strict naming and structure rules for tests. The key rules are:

- **Namespace:** Unit tests mirror `app/` under `tests/Unit/`. Feature tests go under `tests/Feature/`.
- **Method names:** Every test method starts with `testIt...` (e.g. `testItConstructs`, `testItCanRetrieveValueXy`) and returns `void`.
- **SUT variable:** The class under test is always named `$sut`.
- **Assertions:** Always call assertions as `static::assertSame()`, not `$this->assertSame()`.
- **Coverage attributes:** Annotate test classes with `#[CoversClass(MyClass::class)]` or `#[CoversTrait(MyTrait::class)]` from `PHPUnit\Framework\Attributes`.
- **Data providers:** Named `provideTestIt{MethodName}Data`, return `iterable` (generator with `yield 'label' => [values]`).
- **Fixtures:** One fixture class per file, placed in a `{TestClassName}Fixtures/` sub-namespace next to the test class.
- **Exception messages:** When expecting an exception, always assert the message as well.
- **Section dividers:** Use `// =========================================================================` between logical sections in a test file.

> **Anti-patterns to avoid:** Testing private methods via reflection · over-mocking · hardcoding absolute file paths · test methods longer than ~20 lines.

### PHPStan

Fix all PHPStan errors rather than suppressing them. Suppressions are a last resort for genuine false positives in third-party code.

### Frontend Tests

There is currently no automated frontend test suite. Frontend testing will be introduced in **HAWKI 3.0.0**.

---

## Frontend Code

> **Planned Svelte rewrite:** The HAWKI frontend is being progressively migrated to a Svelte 5 SPA. **Do not add new code to the legacy vanilla-JS layer** (`public/js/`). All new frontend work must follow the patterns in the [Frontend documentation](600-Frontend/).

| Topic                                       | Document                                                          |
|---------------------------------------------|-------------------------------------------------------------------|
| Tech stack, directory structure             | [Svelte Frontend](600-Frontend/100-Svelte-Frontend.md)            |
| Component authoring conventions             | [Writing Svelte Components](600-Frontend/400-Components/index.md) |
| CSS tokens, cascade layers, dark mode       | [Styling](600-Frontend/200-Styling.md)                            |
| Config, API fetch helpers, resource schemas | [Data Layer](600-Frontend/300-Data/index.md)                      |
| `__()` translation function                 | [Translations](600-Frontend/500-Utilities/100-Translations.md)    |
| Available UI primitive components           | [UI Primitives](600-Frontend/400-Components/100-UI-Primitives.md) |

---

## Pull Request Process

### Before Creating a PR

1. Ensure your branch is up to date with `development`
2. Review your own changes
3. Run the test suite locally (automated test coverage is actively being built out — check for new tests before assuming there are none)

The release pipeline and automated checks are described in [`_changelog/README.md`](https://github.com/hawk-digital-environments/HAWKI/blob/development/_changelog/README.md).

### PR Scope & Size

One PR = one responsibility. Keep PRs small and focused:

- One feature, bugfix, or refactor (or a tightly related set)
- Do not mix refactors, formatting, and feature changes
- If a change touches many files, explain why in the description

### PR Title & Description

Use the same format as commit messages:

```
feat(ai): add model status caching
fix(auth): resolve LDAP reconnect loop
```

A good PR description answers:

- **What** was changed?
- **Why** was this approach chosen?
- What issue does it close? (`Closes #123`)

### Draft PRs

For early feedback or architectural guidance, open a Draft PR and request specific feedback in the description. Mark as "Ready for review" when complete.

---

## Code Review

### For Contributors

- All PRs require at least one approval before merge
- Address all feedback; resolve conversations when done
- If feedback is unclear, ask for clarification

### For Reviewers

- Critique code, not people
- Explain *why*, not just *what*
- Suggest alternatives where relevant
- Label comments:
    - **Blocking:** "This will cause a bug because..."
    - **Non-blocking:** "Consider X for better readability"
    - **Question:** "Why did you choose this approach?"

---

## AI Agents

You are welcome to use AI tools when contributing to HAWKI. AI assistants can help you write and review code, generate tests, format code, and understand the codebase.

To help you succeed, we provide curated skills:

[//]: # (- **[HAWKI backend Skill]&#40;pathname://attachments/skills/hawki-backend/SKILL.md&#41;** — The skill for working at our Laravel backend layer, including our architecture, coding standards, and best practices)

[//]: # (- **[HAWKI frontend Skill]&#40;pathname://attachments/skills/hawki-frontend/SKILL.md&#41;** — The skill for working at our Svelte frontend layer, including our hybrid architecture, coding standards, and best practices)

[//]: # (- **[PHPUnit Testing Skill]&#40;pathname://attachments/skills/phpunit/SKILL.md&#41;** — Comprehensive guidance for writing effective unit and feature tests, including assertions, data providers, mocking, and test structure)

Share these skills with your AI tool to give it context on HAWKI's expectations.

---

## Getting Help

- **[GitHub Issues](https://github.com/hawk-digital-environments/HAWKI/issues)** — Bugs and feature requests
- **[Discord](https://discord.gg/zzR54sRWDE)** — Real-time support in **#sos-support**
- **[Documentation](https://docs.hawki.info)** — Guides and FAQs

**Good First Issues:** Look for `good first issue`, `help wanted`, or `documentation` labels.

When in doubt about architecture, open a Draft PR early rather than building something that might need a major rewrite. Architectural discussions are cheaper than large rewrites.

---

## Philosophy

- **Clarity over cleverness** — Simple, readable code wins
- **Explicit dependencies** — Make dependencies visible and testable
- **Domains, not layers** — Organize by business concept, not technical concern
- **Consistency** — Follow established patterns in the codebase
- **Incremental improvement** — Small, focused changes compound over time

---

> **A note of honest self-deprecation:** We are aware that the current codebase does not fully reflect the goals described in this guide — some naming conventions are mid-migration and a few pre-refactor rough edges remain. They are catalogued in the [Technical Debt Register](500-Backend/100-Architecture/300-Technical-Debt.md). Please do as we say, not as we did. :)

Thank you for contributing to HAWKI! 🧡
