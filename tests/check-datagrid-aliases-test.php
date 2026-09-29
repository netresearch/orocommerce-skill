<?php

declare(strict_types=1);

/**
 * Behavioural tests for scripts/check-datagrid-aliases.php.
 *
 * Runs the checker as a separate process, the way a user runs it, against the
 * fixtures in tests/fixtures/datagrid-aliases/, and compares exit code, stdout
 * and stderr. No dependencies beyond the PHP CLI.
 *
 * Usage:
 *   php tests/check-datagrid-aliases-test.php
 *
 * Exit codes: 0 = every case passed, 1 = at least one case failed.
 *
 * SPDX-License-Identifier: MIT
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

const CHECKER = __DIR__ . '/../scripts/check-datagrid-aliases.php';
const FIXTURES = __DIR__ . '/fixtures/datagrid-aliases';

/**
 * @return array{0:int,1:string,2:string} exit code, stdout, stderr
 */
function runChecker(string ...$args): array
{
    // E_ALL on stderr: a PHP warning or notice from the checker fails every
    // case that expects an empty stderr.
    $command = [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', CHECKER, ...$args];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        fwrite(STDERR, "Could not start the checker\n");
        exit(1);
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
}

function fixture(string $name): string
{
    return FIXTURES . '/' . $name;
}

/**
 * @param list<string> $lines
 */
function lines(array $lines): string
{
    return $lines === [] ? '' : implode("\n", $lines) . "\n";
}

$failures = 0;
$cases = 0;

function check(string $name, array $result, int $exit, string $stdout, string $stderr): void
{
    global $failures, $cases;
    $cases++;
    [$gotExit, $gotStdout, $gotStderr] = $result;
    $problems = [];
    if ($gotExit !== $exit) {
        $problems[] = "exit code: expected {$exit}, got {$gotExit}";
    }
    if ($gotStdout !== $stdout) {
        $problems[] = "stdout:\n--- expected\n{$stdout}--- got\n{$gotStdout}---";
    }
    if ($gotStderr !== $stderr) {
        $problems[] = "stderr:\n--- expected\n{$stderr}--- got\n{$gotStderr}---";
    }
    if ($problems === []) {
        echo "ok   {$name}\n";

        return;
    }
    $failures++;
    echo "FAIL {$name}\n  " . str_replace("\n", "\n  ", implode("\n", $problems)) . "\n";
}

function mismatch(string $file, int $line, string $grid, string $section, string $field, string $alias, string $declared): string
{
    return "{$file}:{$line}: grid '{$grid}': {$section} '{$field}' data_name uses undeclared alias '{$alias}' (declared: {$declared})";
}

$ok = static fn (int $files): string => "OK: no data_name/alias mismatches in {$files} file(s)\n";

// --- Grids whose data_name values all use declared aliases -----------------

check('inline flow-style from', runChecker(fixture('flow-from.yml')), 0, $ok(1), '');
check('block-style from and flow-style join', runChecker(fixture('block-from-join.yml')), 0, $ok(1), '');
check('quoted alias and data_name values', runChecker(fixture('quoted.yml')), 0, $ok(1), '');
check(
    'extends, extended_from, non-ORM source and plain field names are skipped',
    runChecker(fixture('skipped.yml')),
    0,
    $ok(1),
    '',
);

// --- Mismatches ------------------------------------------------------------

$file = fixture('undeclared.yml');
check(
    'undeclared alias in sorters and filters',
    runChecker($file),
    1,
    lines([
        mismatch($file, 14, 'acme-blog-posts-grid', 'sorters', 'subject', 'post', 'd'),
        mismatch($file, 19, 'acme-blog-posts-grid', 'filters', 'subject', 'post', 'd'),
    ]),
    '',
);

$file = fixture('declared-list.yml');
check(
    'every from/join notation is read into the declared list',
    runChecker($file),
    1,
    lines([
        mismatch($file, 14, 'acme-inline-grid', 'filters', 'subject', 'x', 'd'),
        mismatch($file, 31, 'acme-block-grid', 'sorters', 'subject', 'x', 'p, a, c'),
    ]),
    '',
);

$file = fixture('two-grids.yml');
check(
    'an alias of one grid does not satisfy another grid',
    runChecker($file),
    1,
    lines([mismatch($file, 25, 'acme-posts-grid', 'sorters', 'name', 'a', 'p')]),
    '',
);

$file = fixture('comments.yml');
check(
    'trailing comments are not part of an alias or data_name',
    runChecker($file),
    1,
    lines([mismatch($file, 25, 'acme-blog-posts-grid', 'filters', 'subject', 'x', 'd, a')]),
    '',
);

// --- Directories -----------------------------------------------------------

$tree = fixture('tree');
$bad = $tree . '/AcmeShopBundle/Resources/config/oro/datagrids.yml';
check(
    'a directory is searched for datagrids.yml files only',
    runChecker($tree),
    1,
    lines([
        mismatch($bad, 14, 'acme-blog-posts-grid', 'sorters', 'subject', 'post', 'd'),
        mismatch($bad, 19, 'acme-blog-posts-grid', 'filters', 'subject', 'post', 'd'),
    ]),
    '',
);
check(
    'a directory with only clean files',
    runChecker($tree . '/AcmeBlogBundle'),
    0,
    $ok(1),
    '',
);

// --- Usage and input errors ------------------------------------------------

$usage = "Usage: check-datagrid-aliases.php <datagrids.yml-file-or-directory>\n";
check('no argument', runChecker(), 2, '', $usage);
check('--help prints the usage on stdout', runChecker('--help'), 0, $usage, '');
check('-h prints the usage on stdout', runChecker('-h'), 0, $usage, '');
check('missing path', runChecker(fixture('does-not-exist')), 2, '', 'Path not found: ' . fixture('does-not-exist') . "\n");
check(
    'directory without datagrids.yml',
    runChecker($tree . '/notes'),
    2,
    '',
    "No datagrids.yml files found under: {$tree}/notes\n",
);

// A subdirectory the checker cannot open. Built at run time: git cannot store
// a mode-000 directory. Root reads it anyway, so the case is skipped there.
$scratch = sys_get_temp_dir() . '/check-datagrid-aliases-test-' . getmypid();
mkdir($scratch . '/locked', 0o777, true);
copy(fixture('flow-from.yml'), $scratch . '/datagrids.yml');
copy(fixture('flow-from.yml'), $scratch . '/locked.yml');
chmod($scratch . '/locked.yml', 0);
chmod($scratch . '/locked', 0);
if (is_readable($scratch . '/locked')) {
    echo "skip unreadable file and subdirectory are input errors (running as root)\n";
} else {
    check(
        'an unreadable file is an input error',
        runChecker($scratch . '/locked.yml'),
        2,
        '',
        "Could not read: {$scratch}/locked.yml\n",
    );
    [$exit, $stdout, $stderr] = runChecker($scratch);
    $prefix = "Could not read directory under {$scratch}: ";
    check(
        'an unreadable subdirectory is an input error',
        [$exit, $stdout, str_starts_with($stderr, $prefix) && !str_contains($stderr, 'Fatal') ? $prefix : $stderr],
        2,
        '',
        $prefix,
    );
}
chmod($scratch . '/locked', 0o755);
rmdir($scratch . '/locked');
unlink($scratch . '/locked.yml');
unlink($scratch . '/datagrids.yml');
rmdir($scratch);

echo "\n{$cases} case(s), {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
