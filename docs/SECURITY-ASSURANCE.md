<!-- SPDX-License-Identifier: CC-BY-SA-4.0 -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->

# Security assurance case — orocommerce-skill

This document states what a user can expect from this repository in terms of security, and argues why that expectation holds. Every claim names the file that implements it. Reporting a vulnerability: see the [security policy](https://github.com/netresearch/.github/blob/main/SECURITY.md). Components: [ARCHITECTURE.md](ARCHITECTURE.md).

## What the repository ships

| Part | Files | Runs where |
| --- | --- | --- |
| Skill instructions for an AI agent | `skills/*/SKILL.md`, `skills/*/references/*.md` | Read by the agent as instructions; not executed. The agent may write the code and run the commands they describe (Oro console commands, Behat, PHPUnit, k6, Docker Compose) in the user's OroCommerce project. |
| Datagrid alias checker | `scripts/check-datagrid-aliases.php` | On the user's machine, run by the agent or the user as `oro-datagrid` suggests; shipped in the plugin archive. |
| Harness checker | `scripts/verify-harness.sh` (installed from netresearch/agent-harness-skill), `Makefile` | In this repository, by contributors (`make verify-harness`); shipped in the plugin archive. |
| Eval definitions | `evals/evals.json` | Data read by the eval validator; not executed. |
| Tests | `tests/check-datagrid-aliases-test.php`, `tests/fixtures/` | In this repository's CI (`.github/workflows/tests.yml`) and on contributors' machines. |

The repository ships no server component, no container image, no compiled code and no runtime library. It stores nothing and handles no user accounts or credentials of its own.

## Security requirements

1. The datagrid alias checker only reads: it opens the file or directory it is given, prints its findings, and changes, executes and sends nothing.
2. Malformed or unexpected input ends the checker with a defined exit code (0, 1 or 2) and a message, not with a PHP error.
3. The checker does not report a data_name as correct when its alias is undeclared in a grid it checks, and it skips a grid it cannot judge instead of guessing.
4. Skill content describes OroCommerce v6.1 practice accurately, so that code an agent writes from it does not open access-control or secret-handling mistakes the guidance warns against.
5. Nothing committed to this repository contains a secret, and a release can be verified against the build that produced it.

## Actors and trust boundaries

- **Skill user and agent.** The agent reads `SKILL.md` and the references and acts in the user's project with the user's privileges. What it runs is decided by the agent and the user, not by this repository. No `SKILL.md` declares `allowed-tools`.
- **The user's OroCommerce project.** The checker reads `datagrids.yml` files from the path it is given; their content is input from outside this repository. It parses them line by line with regular expressions (`checkFile()`), not with a YAML parser, so no YAML tag, anchor or object construction is evaluated. When given a directory it reads every file named `datagrids.yml` below it; it does not descend into symlinked directories, and it does read a `datagrids.yml` that is itself a symlink.
- **Command-line arguments.** The path given to the checker is only opened and printed. `verify-harness.sh` rejects unknown option names and unknown values of `--platform`, `--level` and `--check` with exit 1; an unknown `--format` value falls back to text output. No option value is executed.
- **The contributor's machine.** `verify-harness.sh` reads `AGENTS.md`, `Makefile`, `composer.json`, `package.json` and `.gitlab-ci.yml` of the current directory and runs `git`. On a GitHub repository without a local pull request template it calls `gh api repos/<org>/.github/contents/pull_request_template.md` with the contributor's `gh` login, `<org>` being taken from `git remote get-url origin`.
- **Contributors.** Changes reach `main` through pull requests; `main` is protected by branch protection, whose required status checks are a subset of the checks named in [CONTRIBUTING.md](../CONTRIBUTING.md#governance-and-policies).
- **CI.** Workflows run on GitHub-hosted runners. `validate.yml`, `eval-validate.yml`, `harness-verify.yml` and `tests.yml` call reusable workflows of netresearch/skill-repo-skill that set `permissions: contents: read`; `tests.yml` and `auto-merge-deps.yml` start from `permissions: {}`. `auto-merge-deps.yml` runs on `pull_request_target` and calls the netresearch/.github reusable, which approves and merges pull requests opened by Renovate or Dependabot and does not check out pull request code. `release.yml` runs only on pushed `v*` tags.

## Threats and countermeasures

| Threat | Countermeasure | Evidence |
| --- | --- | --- |
| A crafted `datagrids.yml` makes the checker run code | The checker reads files with `file()` and matches lines with regular expressions; it calls no `eval`, `exec`, `system`, `proc_open` or YAML parser | `scripts/check-datagrid-aliases.php` |
| A crafted pattern makes a regular expression run for a very long time (CWE-1333) | No pattern in the checker contains a nested quantifier, and the line patterns are anchored at the start | `scripts/check-datagrid-aliases.php` |
| An unreadable path or directory aborts the checker with a stack trace | Unreadable files, missing paths, directories without `datagrids.yml` and unopenable subdirectories exit 2 with one line on stderr | `scripts/check-datagrid-aliases.php` (`fail()`, `findDatagridFiles()`); `tests/check-datagrid-aliases-test.php` |
| A trailing YAML comment hides a mismatch or reports a false one | Comments after whitespace and outside quotes are removed before a line is parsed | `stripComment()`; `tests/fixtures/datagrid-aliases/comments.yml` |
| An alias of one grid makes a data_name of another grid look correct | Aliases are collected per grid and compared per grid | `checkFile()`; `tests/fixtures/datagrid-aliases/two-grids.yml` |
| A change breaks the checker unnoticed | The behavioural tests run on every pull request and push to `main` | `tests/check-datagrid-aliases-test.php`, `.github/workflows/tests.yml` |
| A value from AGENTS.md is executed by the harness checker (CWE-78) | Documented `make`, `composer` and `npm run` names are extracted with `[a-zA-Z0-9_-]`/`[a-zA-Z0-9:_-]` patterns and only used in `grep`; no input reaches `eval` or a command position | `scripts/verify-harness.sh` (`check_commands()`) |
| Skill content teaches insecure Oro code | The skills name the secure v6.1 mechanism and warn against the insecure one, for example `#[Acl]` attributes and `AclHelper::apply()` for query filtering (`oro-security`), and keeping `.behat-secrets.yml` out of git (`oro-e2e-testing`); content changes go through pull requests | `skills/oro-security/`, `skills/oro-e2e-testing/`, `AGENTS.md` (PR checklist) |
| Workflow files contain an injectable pattern | CodeQL analyses the workflows on every pull request (`Analyze (actions)`, a required check); actionlint runs in Skill Validation | GitHub code scanning (required check `Analyze (actions)`); `.github/workflows/validate.yml` |
| A shell script contains a latent defect | ShellCheck runs on every `*.sh` file at severity style in Skill Validation and in the pre-commit hook | `.github/workflows/validate.yml`, `.pre-commit-config.yaml` |
| A released archive is tampered with | Releases are built only from annotated, signed tags; the release workflow publishes a Cosign-signed `SHA256SUMS.txt` and build-provenance attestations for the archives | `.github/workflows/release.yml` (calls the netresearch/skill-repo-skill release reusable) |
| An outdated hook version stays in use | Renovate proposes updates for the pre-commit hook revisions | `renovate.json` (`pre-commit.enabled`) |

## Secure design principles applied

- **Least privilege:** the checker only reads; CI runs with `contents: read` (set by the skill-repo-skill reusables and by `tests.yml`) except the auto-merge and release jobs, which get the write scopes their reusable needs.
- **Fail-safe defaults:** grids the checker cannot judge (`extends`, `extended_from`, no recognised alias) are skipped rather than reported as correct or wrong, and input errors end with exit 2 rather than a partial result.
- **Economy of mechanism:** the checker is one PHP file without dependencies; the harness checker needs bash, git and the usual text tools.
- **Open design:** everything the skills tell an agent to do is plain text in `skills/`, reviewable before use.

## What a user cannot expect

- The skills give guidance; they do not enforce it. The agent writes code and runs commands with the user's privileges. Review what an agent proposes, in particular ACL configuration, access rules and anything that handles credentials.
- The checker covers one pitfall: `data_name` aliases in `filters` and `sorters`. It does not validate a `datagrids.yml` as YAML, does not resolve `extends` or `extended_from`, skips grids without a recognised `from`/`join` alias, and does not read the flow-mapping form `field: { data_name: ... }`. Exit 0 is not evidence that a grid is correct.
- The checker prints alias names from `alias:` values as they appear in the file.
- `verify-harness.sh` reports on documentation consistency; it is not a security check.
- The repository's workflows run no dependency-vulnerability scan, SAST tool other than CodeQL for the workflow files, or secret scan on pull requests.
- Security fixes follow the supported-versions rules of the organisation's security policy; older releases may not receive them.
