<?php
/**
 * Language catalogue checks.
 *
 * Split out of verify.php because these are checks over PHP files rather than
 * over the database, and because they should be runnable without a database —
 * a translator working on lang/lg.php should be able to check their work.
 *
 *     php database/lang_check.php
 *
 * The checks exist because of one specific failure that is easy to make and
 * hard to see: a stray character from another script. A catalogue is all one
 * script at a glance, so a Han or Hangul character sitting inside a Runyankole
 * string does not jump out of the page. It reached two of the four
 * catalogues here and was only caught by scanning for it. That scan is now a
 * test rather than a habit.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$en = require $root . '/lang/en.php';
if (!is_array($en) || !$en) {
    fwrite(STDERR, "lang/en.php is empty or does not return an array.\n");
    exit(1);
}

$codes   = ['lg', 'rn', 'so', 'ach'];
$pass    = 0;
$fail    = 0;
$notes   = 0;

function ok(string $label, bool $good, string $detail = ''): void
{
    global $pass, $fail;
    if ($good) {
        $pass++;
        printf("  PASS  %-46s %s\n", $label, $detail);
    } else {
        $fail++;
        printf("  FAIL  %-46s %s\n", $label, $detail);
    }
}

function info(string $label, string $detail = ''): void
{
    global $notes;
    $notes++;
    printf("  note  %-46s %s\n", $label, $detail);
}

/** Characters that have no business in a Ugandan language catalogue. */
$foreignScripts = '/[\x{0400}-\x{04FF}\x{0590}-\x{05FF}\x{0600}-\x{06FF}'
                . '\x{0900}-\x{097F}\x{0E00}-\x{0E7F}\x{3040}-\x{30FF}'
                . '\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}'
                . '\x{F900}-\x{FAFF}]/u';

echo "1. English catalogue\n";

ok('English catalogue is populated', count($en) > 0, count($en) . ' strings');

$badKeys = array_filter(array_keys($en), static fn($k) => !preg_match('/^[a-z][a-z0-9]*(\.[a-z0-9_]+)+$/', (string) $k));
ok('Every English key is a namespaced lowercase key',
    $badKeys === [],
    $badKeys ? 'offending: ' . implode(', ', array_slice($badKeys, 0, 5)) : count($en) . ' keys');

$empty = array_filter($en, static fn($v) => trim((string) $v) === '');
ok('No English string is empty', $empty === [],
    $empty ? count($empty) . ' empty' : '');

/* Every placeholder in English must be preserved, or a translated string will
   quietly lose the one thing it had to carry — a name, an amount, a date. */
$placeholders = static function (string $s): array {
    preg_match_all('/%\{(\w+)\}/', $s, $m);
    sort($m[1]);
    return $m[1];
};
$enVars = array_map($placeholders, $en);
ok('English catalogue declares no placeholders it cannot fill',
    array_filter($enVars) === [],
    'the trader-facing strings are all fixed text');

echo "\n2. Translated catalogues\n";

foreach ($codes as $code) {
    $file = $root . '/lang/' . $code . '.php';
    echo "\n  lang/{$code}.php\n";

    if (!is_file($file)) {
        ok("{$code}: file exists", false, 'not written yet');
        continue;
    }
    ok("{$code}: file exists", true);

    /* A syntax error is caught by the linter, not by require(). */
    $lint = [];
    $rc   = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $lint, $rc);
    ok("{$code}: parses", $rc === 0, $rc === 0 ? '' : trim($lint[0] ?? ''));

    $strings = require $file;
    if (!is_array($strings)) {
        ok("{$code}: returns an array", false);
        continue;
    }
    ok("{$code}: returns an array", true, count($strings) . ' strings');

    /* Key parity. An extra key is harmless but a missing one means a trader
       reads English mid-sentence, which reads as an error rather than a gap. */
    $missing = array_values(array_diff(array_keys($en), array_keys($strings)));
    $extra   = array_values(array_diff(array_keys($strings), array_keys($en)));
    ok("{$code}: every English key is present", $missing === [],
        $missing ? count($missing) . ' missing, e.g. ' . implode(', ', array_slice($missing, 0, 3)) : '');
    ok("{$code}: no keys English does not have", $extra === [],
        $extra ? count($extra) . ' extra, e.g. ' . implode(', ', array_slice($extra, 0, 3)) : '');

    $blank = array_keys(array_filter($strings, static fn($v) => trim((string) $v) === ''));
    ok("{$code}: nothing left blank", $blank === [], $blank ? implode(', ', $blank) : '');

    /* The check this file exists for. */
    $foreign = [];
    foreach ($strings as $k => $v) {
        if (preg_match($foreignScripts, (string) $v, $m)) {
            $foreign[] = $k . ' (' . implode('', array_unique($m[0])) . ')';
        }
    }
    ok("{$code}: no character from another script", $foreign === [],
        $foreign ? implode(', ', $foreign) : '');

    /* A literal \n typed into a single-quoted string prints as the two
       characters, not a newline. Same for \t. */
    $escapes = array_keys(array_filter($strings, static fn($v) => preg_match('/\\\\[nrtv]/', (string) $v)));
    ok("{$code}: no literal escape sequence left in a value", $escapes === [],
        $escapes ? implode(', ', $escapes) : '');

    /* Left in English without being marked. Same script is fine and normal —
       "Email" is "Email" — but a whole clause untranslated is not. */
    $untranslated = [];
    foreach ($strings as $k => $v) {
        $v = (string) $v;
        if (trim($v) !== '' && $v === (string) ($en[$k] ?? '')) {
            $words = preg_split('/\s+/', trim($v));
            if (is_array($words) && count($words) >= 4) {
                $untranslated[] = $k;
            }
        }
    }
    if ($untranslated === []) {
        info("{$code}: nothing left wholly untranslated", '');
    } else {
        info("{$code}: wholly untranslated strings", implode(', ', $untranslated));
    }

    /* Placeholders must survive translation. */
    $lost = [];
    foreach ($strings as $k => $v) {
        $want = $placeholders((string) ($en[$k] ?? ''));
        if ($want !== [] && $want !== $placeholders((string) $v)) {
            $lost[] = $k;
        }
    }
    ok("{$code}: placeholders preserved", $lost === [], $lost ? implode(', ', $lost) : '');

    $done = count(array_filter($strings, static fn($v) => trim((string) $v) !== ''));
    info("{$code}: coverage", sprintf('%d/%d (%.0f%%)', $done, count($en), $done / max(1, count($en)) * 100));
}

echo "\n" . str_repeat('-', 74) . "\n";
printf("%d passed, %d failed", $pass, $fail);
echo $fail === 0 ? ", and {$notes} noted.\n" : ".\n";

if ($fail > 0) {
    echo "\nThe catalogues are not consistent yet. A trader who reads a page\n";
    echo "part-translated is worse off than one reading it in English, so a\n";
    echo "failed check here should block release rather than be noted.\n";
    exit(1);
}

echo "\nAll catalogues parse, carry every key, and are free of characters\n";
echo "from another script. The wording itself is still unreviewed — that is\n";
echo "a matter for a native speaker, and nothing in this file can check it.\n";
exit(0);