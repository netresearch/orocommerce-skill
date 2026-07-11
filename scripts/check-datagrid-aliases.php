#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that every `data_name` under a datagrid's `filters`/`sorters` sections
 * references an alias actually declared in that grid's `source.query.from`/`join`.
 *
 * This is Key Pitfall #1 in skills/oro-datagrid/SKILL.md: a `data_name` with an
 * undeclared alias renders in the UI but silently produces no WHERE clause /
 * ORDER BY — there is no error, just a filter or sorter that does nothing.
 *
 * Scope: recognizes the two `from`/`join` alias notations documented in
 * skills/oro-datagrid/{SKILL.md,references/datagrid-patterns.md} — inline flow
 * style (`from: [{ table: X, alias: d }]`) and block style (`- { join: d.author,
 * alias: a }` / `- table: X` + `alias: d`). Grids with no recognized `from`/`join`
 * aliases, and grids using `extends`/`extended_from` (their alias set depends on
 * a parent grid this checker doesn't resolve, possibly in another bundle), are
 * skipped rather than flagged.
 *
 * Usage:
 *   php scripts/check-datagrid-aliases.php <datagrids.yml-file-or-directory>
 *
 * Exit codes: 0 = no mismatches, 1 = mismatches found, 2 = usage/input error.
 */

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(2);
}

/**
 * @param list<string> $path
 */
function inSourceAliasContext(array $path): bool
{
    return in_array('source', $path, true)
        && in_array('query', $path, true)
        && (in_array('from', $path, true) || in_array('join', $path, true));
}

/**
 * @param list<string> $path
 */
function sectionOf(array $path): ?string
{
    if (in_array('filters', $path, true)) {
        return 'filters';
    }
    if (in_array('sorters', $path, true)) {
        return 'sorters';
    }

    return null;
}

/**
 * @param array<string, array<string, true>> $gridAliases
 */
function recordAliasesFromValue(string $value, string $grid, array &$gridAliases): void
{
    if (preg_match_all('/alias\s*:\s*[\'"]?([A-Za-z_]\w*)[\'"]?/', $value, $matches)) {
        foreach ($matches[1] as $alias) {
            $gridAliases[$grid][$alias] = true;
        }
    }
}

/**
 * @return int number of mismatches found in this file
 */
function checkFile(string $file): int
{
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fail("Could not read: {$file}");
    }

    $mismatches = 0;

    /** @var list<array{0:int,1:string}> $stack */
    $stack = [];
    $grid = null;
    /** @var array<string, array<string, true>> $gridAliases */
    $gridAliases = [];
    /** @var array<string, list<array{0:int,1:string,2:string,3:string}>> $pendingChecks */
    $pendingChecks = [];
    /** @var array<string, true> $gridExtendsParent */
    $gridExtendsParent = [];

    foreach ($lines as $i => $rawLine) {
        $lineNo = $i + 1;
        $trimmed = trim($rawLine);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $indent = strlen($rawLine) - strlen(ltrim($rawLine, ' '));

        // Dedent: pop stack entries at the same or deeper indent.
        while ($stack !== [] && end($stack)[0] >= $indent) {
            array_pop($stack);
        }
        $pathBefore = array_column($stack, 1);

        // Flow-style list item, e.g. "- { table: X, alias: d }".
        if (preg_match('/^\s*-\s*\{(.+)\}\s*$/', $rawLine, $flow)) {
            if ($grid !== null && inSourceAliasContext($pathBefore)) {
                recordAliasesFromValue($flow[1], $grid, $gridAliases);
            }
            continue;
        }

        // "key: value" line (block mapping key, possibly with an inline value).
        if (!preg_match('/^\s*([A-Za-z_][\w.\-]*)\s*:\s*(.*)$/', $rawLine, $m)) {
            continue;
        }

        $key = $m[1];
        $value = trim(rtrim($m[2]), "'\"");
        $stack[] = [$indent, $key];
        $path = array_column($stack, 1);

        // Grid boundary: direct child of the top-level "datagrids:" key.
        if (count($path) === 2 && $path[0] === 'datagrids') {
            $grid = $key;
            $gridAliases[$grid] ??= [];
            $pendingChecks[$grid] ??= [];
            continue;
        }

        if ($grid === null) {
            continue;
        }

        if (($key === 'extends' || $key === 'extended_from') && count($path) === 3) {
            // Grid inheritance: this grid's effective aliases include its parent's
            // (possibly defined in another bundle's datagrids.yml). Resolving that
            // chain is out of scope — skip validating this grid instead of guessing.
            $gridExtendsParent[$grid] = true;
            continue;
        }

        if ($key === 'alias' && $value !== '' && inSourceAliasContext($path)) {
            $gridAliases[$grid][$value] = true;
            continue;
        }

        if (($key === 'from' || $key === 'join') && $value !== '' && inSourceAliasContext($path)) {
            recordAliasesFromValue($value, $grid, $gridAliases);
            continue;
        }

        if ($key === 'data_name' && $value !== '') {
            $section = sectionOf($path);
            if ($section !== null && preg_match('/^([A-Za-z_]\w*)\.[A-Za-z_]\w*$/', $value, $dm)) {
                $fieldName = $path[count($path) - 2] ?? '?';
                $pendingChecks[$grid][] = [$lineNo, $section, $fieldName, $dm[1]];
            }
        }
    }

    foreach ($pendingChecks as $gridName => $checks) {
        if (isset($gridExtendsParent[$gridName])) {
            continue;
        }
        $aliases = $gridAliases[$gridName] ?? [];
        if ($aliases === []) {
            // No from/join aliases recognized for this grid (non-ORM source, or a
            // notation this checker doesn't parse) — skip, don't guess.
            continue;
        }
        foreach ($checks as [$lineNo, $section, $fieldName, $alias]) {
            if (!isset($aliases[$alias])) {
                $declared = implode(', ', array_keys($aliases));
                printf(
                    "%s:%d: grid '%s': %s '%s' data_name uses undeclared alias '%s' (declared: %s)\n",
                    $file,
                    $lineNo,
                    $gridName,
                    $section,
                    $fieldName,
                    $alias,
                    $declared,
                );
                $mismatches++;
            }
        }
    }

    return $mismatches;
}

/**
 * @return list<string>
 */
function findDatagridFiles(string $path): array
{
    if (is_file($path)) {
        return [$path];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'datagrids.yml') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

function main(array $argv): int
{
    $path = $argv[1] ?? null;
    if ($path === null || $path === '-h' || $path === '--help') {
        fail('Usage: check-datagrid-aliases.php <datagrids.yml-file-or-directory>');
    }
    if (!file_exists($path)) {
        fail("Path not found: {$path}");
    }

    $files = findDatagridFiles($path);
    if ($files === []) {
        fail("No datagrids.yml files found under: {$path}");
    }

    $total = 0;
    foreach ($files as $file) {
        $total += checkFile($file);
    }

    if ($total === 0) {
        fwrite(STDOUT, "OK: no data_name/alias mismatches in " . count($files) . " file(s)\n");
    }

    return $total > 0 ? 1 : 0;
}

exit(main($argv));
