# Contributing

Thank you for your interest in contributing to `hyde-llms-txt`.

## Getting Started

After cloning the repository, follow these steps to set up the project:

```bash
git clone git@github.com:raphaelstolt/hyde-llms-txt.git && cd hyde-llms-txt
composer install
```

Verify the setup by running the checks:

```bash
composer check
```

## Code Quality

This project uses Composer scripts to manage common development commands. See all available scripts with:

```bash
composer run
```

### Checks

Run linting, static analysis, and tests together:

```bash
composer check
```

### Formatting

Format the code with Mago:

```bash
composer fmt
```

### Linting

Check for code issues with Mago:

```bash
composer lint
```

### Static Analysis

Run Mago's static analysis:

```bash
composer analyze
```

### Testing

Run the full test suite:

```bash
composer test
```

## Pull Requests

Please open pull requests against the `main` branch from a dedicated feature branch. Use descriptive branch names that
reflect the change, such as `feat/add-option`, `fix/link-formatting`, or `docs/update-readme`.

Before opening a PR, ensure:

1. `composer check` passes locally.
2. `CHANGELOG.md` is updated under the `[Unreleased]` section with a summary of notable changes.
3. Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/).

## Commit Messages

Please follow [Conventional Commits](https://www.conventionalcommits.org/) for all commit messages. Common types include:

- `feat:` — new features or behavior changes
- `fix:` — bug fixes
- `docs:` — documentation updates
- `refactor:` — code changes that neither fix bugs nor add features
- `test:` — adding or updating tests
- `chore:` — maintenance tasks (dependencies, config, etc.)