<?php
/**
 * KCCA VRSP — Safe error handling.
 *
 * Two rules the Authority's own guidance implies:
 *
 *  1. A trader never sees a stack trace, a SQL fragment or a file path.
 *     They see what went wrong, what to do, and who to contact.
 *  2. A staff user sees a reference number, which matches the log, so a
 *     fault report can be traced to one record.
 *
 * Every fault is written to the error log with a reference before the
 * page is rendered.
 */

declare(strict_types=1);

final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            // Convert genuine errors into exceptions; ignore notices.
            if (!(error_reporting() & $severity)) {
                return false;
            }
            if ($severity & (E_DEPRECATED | E_NOTICE | E_USER_DEPRECATED | E_USER_NOTICE | E_STRICT)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public static function handle(Throwable $e): void
    {
        $reference = 'ERR-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        self::log($reference, $e);

        /* One handler, two audiences: a CLI script needs the trace, a
           browser needs a plain-language page. */

        /* Command-line scripts (seeder, verify) must see the real fault,
           not a styled HTML page. */
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf(
                "\nFatal: %s\n  at %s:%d\n  ref %s\n",
                $e->getMessage(), $e->getFile(), $e->getLine(), $reference
            ));
            exit(1);
        }

        /* Never swallow output already on the page — a half-rendered page
           plus an error body is worse than either alone. */
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $headersSent = headers_sent();
        if (!$headersSent) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($isApi) {
            if (!$headersSent) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'error'     => 'The request could not be completed.',
                'reference' => $reference,
                'code'      => 500,
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        echo self::page($reference, $e);
        exit;
    }

    private static function log(string $reference, Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $reference,
            $e->getFile(),
            $e->getLine(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        @file_put_contents(sys_get_temp_dir() . '/kcca-vrsp-error.log', $line, FILE_APPEND);

        // Also record it in the audit trail where possible, so the system
        // has a single place to look when a fault is reported.
        try {
            Database::insert('audit_log', [
                'user_id'    => Auth::check() ? Auth::id() : null,
                'actor'      => Auth::check() ? (string) (Auth::user()['full_name'] ?? 'staff') : 'anonymous',
                'action'     => 'system-error',
                'entity'     => 'error',
                'detail'     => $reference . ' — ' . $e->getMessage(),
                'ip_address' => serverClientIp(),
            ]);
        } catch (Throwable $ignored) {
            // The database is likely what failed. Do not recurse.
        }
    }

    private static function page(string $reference, Throwable $e): string
    {
        $f = facts();
        $a = $f['authority'];

        /* Development detail only, and only when explicitly switched on. */
        $debug = getenv('KCCA_DEBUG') === '1'
            ? '<pre style="background:#fff;border:1px solid var(--line);border-radius:8px;padding:14px;'
              . 'font-size:12px;overflow:auto;text-align:left;white-space:pre-wrap">'
              . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString(), ENT_QUOTES)
              . '</pre>'
            : '';

        $signedIn = Auth::check();

        ob_start();
        ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Something went wrong · <?= e(APP_SHORT) ?></title>
<link rel="icon" type="image/png" href="../assets/brand/kcca-favicon.png">
<link rel="stylesheet" href="../assets/css/kcca.css">
</head>
<body>
<main class="login-main" style="min-height:100vh">
  <div class="login-card" style="max-width:600px">

    <img class="login-logo" src="../assets/brand/kcca-logo.png" alt="Kampala Capital City Authority">

    <div class="alert alert-bad" style="margin-bottom:20px">
      <strong>Something went wrong.</strong> The system could not complete that request.
    </div>

    <h2>Nothing was changed</h2>
    <p class="sub">
      Any work you had already saved is intact. This fault has been recorded with the reference below.
      Quote it if you report the problem.
    </p>

    <div class="card" style="margin:18px 0">
      <div class="card-body">
        <dl class="kv">
          <dt>Reference</dt><dd><strong><?= e($reference) ?></strong></dd>
          <dt>Recorded</dt><dd><?= e(date('d F Y, H:i')) ?> EAT</dd>
          <dt>Your role</dt><dd><?= e($signedIn ? humanise(Auth::role()) : 'Not signed in') ?></dd>
        </dl>
      </div>
    </div>

    <?= $debug ?>

    <div class="row mt-2">
      <?php if ($signedIn): ?>
        <a class="btn btn-primary" href="../pages/executive.php">Back to the dashboard</a>
      <?php else: ?>
        <a class="btn btn-primary" href="../auth/login.php">Back to sign in</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="../pages/portal.php">Trader portal</a>
    </div>

    <p class="login-footer">
      <strong style="color:var(--ink-2)"><?= e($a['legal_name']) ?></strong><br>
      Tel <?= e($a['telephone']) ?> · Toll free <?= e($a['toll_free']) ?> ·
      <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a><br>
      Reference <strong><?= e($reference) ?></strong>
    </p>
  </div>
</main>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}