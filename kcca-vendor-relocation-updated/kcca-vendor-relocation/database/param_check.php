<?php
/**
 * Find named placeholders used twice in one prepared statement.
 *
 *     php database/param_check.php
 *
 * PDO is configured with ATTR_EMULATE_PREPARES off, so a named parameter is
 * bound exactly once per statement. Writing ':q OR x LIKE :q' — which reads
 * perfectly naturally, and is the obvious way to say "search two columns" —
 * throws HY093 "Invalid parameter number" when the statement is prepared.
 *
 * It is not caught by the linter, not caught by a smoke test that only
 * exercises the unfiltered path, and it fails at prepare time rather than at
 * query time, so the error points at PDO rather than at the line that wrote
 * it. Here it broke a mayor's view of the notice register while leaving the
 * citywide view working, because only a mayor passes a division.
 *
 * The source is read with token_get_all rather than by matching quotes with a
 * regular expression. A regex over PHP source miscounts its own delimiters
 * sooner or later — that was the first version of this file, and it reported
 * "none found" while silently failing to match anything.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$hits = [];
$scanned = 0;

/** Every string literal in a PHP file, as the parser sees them. */
function stringLiterals(string $src): array
{
    $out = [];
    foreach (token_get_all($src) as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $out[] = $token[1];
        }
    }
    return $out;
}

foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
) as $file) {
    $path = $file->getPathname();

    if (!str_ends_with($path, '.php')
        || str_contains($path, '/.git/')
        || str_contains($path, '/vendor/')) {
        continue;
    }

    $src = file_get_contents($path);
    if (!str_contains($src, ':')) {
        continue;
    }
    $scanned++;
    $rel = str_replace($root . '/', '', $path);

    foreach (stringLiterals($src) as $literal) {
        $blob = eval('return ' . $literal . ';');
        if (!is_string($blob) || !str_contains($blob, ':')) {
            continue;
        }
        /* No SQL keyword filter. Most of these placeholders live in a bare
           predicate fragment — "(n.subject LIKE :q OR n.reference LIKE :q)" —
           concatenated into a statement elsewhere, so requiring SELECT or
           UPDATE in the literal silently skipped every one of them, which is
           the whole class this file exists to catch. The defect is a name
           repeated inside one literal, whatever that literal is for. */
        if (!preg_match_all('/:([a-z_][a-z0-9_]*)/i', $blob, $ph)) {
            continue;
        }
        $counts = array_count_values(array_map('strtolower', $ph[1]));
        foreach ($counts as $name => $n) {
            if ($n > 1) {
                $hits[] = sprintf('%s  :%s used %d times', $rel, $name, $n);
            }
        }
    }
}

printf("Scanned %d PHP files for repeated named placeholders.\n\n", $scanned);

if ($hits === []) {
    echo "  none\n\n";
    echo "Every named placeholder appears once per statement, which is what PDO\n";
    echo "requires when ATTR_EMULATE_PREPARES is off.\n";
    exit(0);
}

echo "  REPEATED NAMED PLACEHOLDER\n";
foreach ($hits as $h) {
    echo '    ' . $h . "\n";
}
echo "\n";
echo "Each throws 'Invalid parameter number' when that query runs with its\n";
echo "parameters bound. Give each occurrence its own name:\n";
echo "    'x LIKE :q1 OR y LIKE :q2'   with ['q1' => ..., 'q2' => ...]\n";
exit(1);