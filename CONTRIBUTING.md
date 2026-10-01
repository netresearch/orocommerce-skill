<!-- SPDX-License-Identifier: CC-BY-SA-4.0 -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->

# Contributing

Contributions are welcome as pull requests against `main`. Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) and carry a `Signed-off-by` line (`git commit --signoff`); the DCO check on pull requests requires it. The rules for skill content and the pull request checklist are in [AGENTS.md](AGENTS.md).

## Local setup

Install the hooks once after cloning:

```bash
pre-commit install --install-hooks
```

The hooks in `.pre-commit-config.yaml` run the same checks as CI: the skill validator from netresearch/skill-repo-skill, version parity, markdownlint, yamllint, actionlint, ruff and ShellCheck. One difference: the hook lints every Markdown file, while CI's markdownlint step lints only the Markdown files in the repository root. `pre-commit run --all-files` runs them over the whole tree.

## Tests

The repository ships one executable that takes input, `scripts/check-datagrid-aliases.php`. Its behavioural tests are in `tests/check-datagrid-aliases-test.php`, with input files in `tests/fixtures/datagrid-aliases/`.

Run them locally with the PHP CLI (8.1 or later); nothing needs to be installed:

```bash
php tests/check-datagrid-aliases-test.php
```

CI runs the same file on every pull request to `main` and every push to `main`: `.github/workflows/tests.yml` calls the Skill Tests workflow of netresearch/skill-repo-skill, which runs it on PHP 8.3.

The tests start the checker as a separate process, the way a user runs it, and compare its exit code, stdout and stderr with the expected values. They cover every alias notation the checker reads (inline and block `from`, flow and block `join` items, quoted values, trailing comments), the report line for an undeclared alias in `filters` and `sorters`, per-grid scoping, the grids it skips (`extends`, `extended_from`, no recognised alias, plain field names), the directory search, `--help`, and the input errors that exit 2 (no argument, missing path, no `datagrids.yml`, unreadable file or directory).

Reading the result: each case prints `ok` or `FAIL` and its name; a failing case prints the expected and the actual exit code, stdout or stderr below it. The last line counts the cases and failures, and the exit code is 1 when any case failed. The unreadable-file cases print `skip` when run as root, which can read the files.

New or changed behaviour of a script needs a test case in the same pull request. Add a fixture under `tests/fixtures/datagrid-aliases/` and a `check()` call in the runner, and confirm that the case fails without the change.

`make verify-harness` checks that `AGENTS.md`, `docs/` and the CI workflows agree; CI runs the same checks in Harness Verification. `scripts/verify-harness.sh` is installed from netresearch/agent-harness-skill, where its tests live.

## Dependencies

- **Skills:** none. The skill content is Markdown; `scripts/check-datagrid-aliases.php` uses only the PHP standard library.
- **Composer distribution:** `composer.json` requires `netresearch/composer-agent-skill-plugin` (constraint `*`), which registers the skills in a consumer project. No `composer.lock` is committed (`.gitignore`); the consumer's own lock file pins the version.
- **Development tools:** the pre-commit hooks in `.pre-commit-config.yaml`, each pinned to a release tag with `rev:`. pre-commit downloads them from their GitHub repositories.
- **CI:** the workflows call reusable workflows of netresearch/skill-repo-skill and netresearch/.github at `@main`; those reusables pin the actions they use to commit SHAs.
- **Updates:** Renovate (`renovate.json`, organisation preset `netresearch/renovate-config`, with the pre-commit manager enabled) opens pull requests for new hook versions. They pass the same checks as any other pull request.
- **Selection:** a new dependency is added in a pull request and passes the checks listed below. Its licence must be OSI-approved and compatible with the project licence, as the organisation's [security policy](https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings) requires.

## Governance and policies

This repository follows the Netresearch organisation policies:

- [Governance](https://github.com/netresearch/.github/blob/main/GOVERNANCE.md): ownership, roles, how decisions are made and disputes resolved, and continuity.
- [Roadmap](https://github.com/netresearch/.github/blob/main/ROADMAP.md): planned and explicitly excluded work for the coming year.
- [Handling of dependency and code analysis findings](https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings): thresholds, deadlines and the exception process for dependency (SCA) and static analysis (SAST) findings.
- [Secret management](https://github.com/netresearch/.github/blob/main/SECURITY.md#secret-management): how CI and release credentials are stored, accessed and rotated.
- [Access roster](https://github.com/netresearch/.github/blob/main/docs/access-roster.md): who holds administrative access to this repository and the organisation.

The security assurance case for this repository (threat model, trust boundaries, countermeasures and limits) is in [docs/SECURITY-ASSURANCE.md](docs/SECURITY-ASSURANCE.md).

Checks that run on every pull request to `main`:

- Skill Validation (`validate.yml`): skill structure, plugin manifest sync, markdownlint, yamllint, actionlint, JSON syntax, plugin and SKILL.md version parity, ShellCheck at severity style, ruff and checkpoint schemas.
- Eval Validation (`eval-validate.yml`), Harness Verification (`harness-verify.yml`) and Skill Tests (`tests.yml`).
- CodeQL analysis of the workflow files (`Analyze (actions)`), configured in GitHub code scanning rather than in a workflow file here, SonarCloud Code Analysis (SonarCloud automatic analysis, also configured outside this repository), the DCO check and the CodeRabbit review status.
- Auto-merge dependency PRs (`auto-merge-deps.yml`), skipped unless Renovate or Dependabot opened the pull request.

The workflows in this repository run no dependency-vulnerability check, no Composer Audit, no other SAST tool and no secret scanner on pull requests.
