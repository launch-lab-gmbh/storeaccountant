# Contributing Guide

Thank you for contributing to StoreAccountant.

This document describes the development workflow, branch strategy, commit conventions, release process, and quality requirements used by this project.

---

# Development Workflow

This project follows:

- GitFlow
- Conventional Commits
- Semantic Versioning (SemVer)

---

# Branch Strategy

## Main Branches

### main

The production branch.

Only stable releases are merged into `main`.

### develop

The integration branch.

All completed features and bug fixes are merged into `develop` first.

---

## Feature Branches

Used for new functionality.

Example:

```bash
git flow feature start export-password-protection
```

Resulting branch:

```text
feature/export-password-protection
```

Finish:

```bash
git flow feature finish export-password-protection
```

---

## Bugfix Branches

Used for bug fixes.

Example:

```bash
git flow bugfix start invalid-export-filename
```

Resulting branch:

```text
bugfix/invalid-export-filename
```

Finish:

```bash
git flow bugfix finish invalid-export-filename
```

---

## Release Branches

Used to prepare a release.

Example:

```bash
git flow release start 1.2.0
```

Resulting branch:

```text
release/1.2.0
```

Typical tasks:

- Update changelog
- Update version numbers
- Final testing
- Release validation

Finish:

```bash
git flow release finish 1.2.0
git push origin main develop --tags
```

---

# Semantic Versioning

This project uses Semantic Versioning.

Format:

```text
MAJOR.MINOR.PATCH
```

Examples:

```text
1.0.0
1.0.1
1.1.0
2.0.0
```

Rules:

### PATCH

Bug fixes only.

Example:

```text
1.0.0 -> 1.0.1
```

### MINOR

Backward-compatible features.

Example:

```text
1.0.0 -> 1.1.0
```

### MAJOR

Breaking changes.

Example:

```text
1.0.0 -> 2.0.0
```

---

# Commit Message Convention

All commits must follow Conventional Commits.

Format:

```text
<type>: <description>
```

Example:

```text
feat: add CSV export configuration
```

---

# Commit Types

| Type | Description |
|--------|-------------|
| feat | New feature |
| fix | Bug fix |
| refactor | Internal code changes without functional changes |
| test | Adding or updating tests |
| docs | Documentation changes |
| style | Formatting and coding style changes |
| chore | Maintenance tasks |
| ci | CI/CD and GitHub Actions |
| build | Build and deployment related changes |
| perf | Performance improvements |

---

# Examples

Good:

```text
feat: add customer export support
fix: handle invalid file names correctly
refactor: simplify storage adapter creation
test: add ZIP archive storage tests
docs: update installation guide
ci: add reusable unit test workflow
build: add GitHub release packaging
```

Bad:

```text
update
fixes
changes
misc
new stuff
```

---

# Breaking Changes

Breaking changes must use `!`.

Examples:

```text
feat!: drop support for PHP 8.1
refactor!: redesign export configuration API
```

---

# Pull Requests

Requirements before opening a pull request:

- Code compiles successfully
- Linting passes
- Unit tests pass
- Documentation is updated if necessary
- Commit messages follow Conventional Commits

Pull requests should target:

```text
develop
```

unless specifically instructed otherwise.

---

# CI/CD

The CI pipeline performs:

- Composer validation
- Linting
- Unit tests
- Additional checks added in future versions

Every pull request must pass CI.

---

# Release Process

The release workflow is triggered automatically when a semantic version tag is pushed.

Example:

```bash
git tag 1.2.0
git push origin 1.2.0
```

The release workflow:

1. Runs all quality checks
2. Builds a production package
3. Creates a GitHub Release
4. Uploads the installable ZIP package
5. Optionally deploys to WordPress.org SVN

---

# WordPress.org Deployment

WordPress.org deployment is performed from the GitHub release workflow.

The deployed package contains only the files required for production use.

WordPress.org assets are stored in:

```text
.wordpress-org/
```

Examples:

```text
.wordpress-org/banner-772x250.png
.wordpress-org/banner-1544x500.png
.wordpress-org/icon-128x128.png
.wordpress-org/icon-256x256.png
.wordpress-org/screenshot-1.png
```

---

# Quality Standards

All code should:

- Follow the project's coding standards
- Pass PHPCS checks
- Include tests where appropriate
- Be documented when introducing new public functionality

---

Thank you for contributing.
