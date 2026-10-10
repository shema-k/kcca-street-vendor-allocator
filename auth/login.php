<?php
/**
 * Sign-in.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if (Auth::check()) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$f     = facts();
$error = null;
$old   = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $old['username'] = $username;

    if ($username === '' || $password === '') {
        $error = 'Enter both your username and your password.';
    } elseif (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please sign in again.';
    } elseif (Auth::attempt($username, $password)) {
        header('Location: ../pages/dashboard.php');
        exit;
    } else {
        $error = 'Those sign-in details were not recognised. Check the username and password, or ask '
               . 'the System Administrator to reset your account.';
        Auth::log('login-failed', 'users', null, 'Failed attempt for username "' . $username . '"');
    }
}

$vac    = $f['vacancy'];
$city   = $f['city'];
$a      = $f['authority'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
<meta name="description" content="Sign in to the KCCA Vendor Relocation and Space Allocation System.">
<meta name="robots" content="noindex">
<title>Sign in · <?= e(APP_SHORT) ?></title>
<link rel="icon" type="image/png" href="../assets/brand/kcca-favicon.png">
<link rel="stylesheet" href="../assets/css/kcca.css">
</head>
<body>
<div class="login-body">

  <!-- ===================== Brand / mission panel ===================== -->
  <aside class="login-aside">
    <div>
      <img src="../assets/brand/kcca-logo-light.png" alt="Kampala Capital City Authority"
           style="height:46px;width:auto;margin-bottom:34px">
      <h1>A Vibrant, Attractive, Livable and Sustainable City.</h1>
      <p class="lead">
        The Vendor Relocation and Space Allocation System is the Authority’s operational instrument for
        restoring public space and giving Kampala’s traders a lawful place to trade. It matches every
        surveyed vendor to suitable gazetted space, using the
        <?= num($vac['public_vacant_spaces']) ?> public and
        <?= num($vac['private_vacant_spaces']) ?> private market spaces the Authority has identified as available.
      </p>

      <ul class="pillar-list">
        <?php foreach ($f['mission']['commitments'] as $c): ?>
          <li>
            <span class="tick" aria-hidden="true">&#10003;</span>
            <span><strong style="color:#fff"><?= e($c['title']) ?>.</strong> <?= e($c['text']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="login-facts">
      <div>
        <span class="n"><?= num($vac['public_vacant_spaces'] + $vac['private_vacant_spaces']) ?></span>
        <span class="l">Vacant spaces identified</span>
      </div>
      <div>
        <span class="n"><?= num($vac['public_markets'] + $vac['private_markets']) ?></span>
        <span class="l">Markets in the register</span>
      </div>
      <div>
        <span class="n"><?= num($city['population']) ?></span>
        <span class="l">City residents</span>
      </div>
      <div>
        <span class="n"><?= num($city['divisions']) ?></span>
        <span class="l">Division councils</span>
      </div>
    </div>
  </aside>

  <!-- ========================= Sign-in form ======================== -->
  <main class="login-main">
    <div class="login-card">
      <img class="login-logo" src="../assets/brand/kcca-logo.png"
           alt="Kampala Capital City Authority">

      <h2>Sign in to the system</h2>
      <p class="sub">Authorised KCCA staff only. Access is logged for audit.</p>

      <?php if ($error): ?>
        <div class="alert alert-bad" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" autocomplete="username"
                 value="<?= e($old['username']) ?>" required autofocus>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
      </form>

      <div class="login-demo">
        <h3>Demonstration accounts — click a username to fill the form</h3>
        <table>
          <tr><td class="u" data-fill="admin">administrator</td><td>KCCA VRSP 2026</td></tr>
          <tr><td class="u" data-fill="ed">executive (ED)</td><td>kcca@2026</td></tr>
          <tr><td class="u" data-fill="mayor.central">mayor (Central)</td><td>kcca@2026</td></tr>
          <tr><td class="u" data-fill="planner">planner</td><td>kcca@2026</td></tr>
          <tr><td class="u" data-fill="enforcement">enforcement</td><td>kcca@2026</td></tr>
          <tr><td class="u" data-fill="revenue">revenue</td><td>kcca@2026</td></tr>
          <tr><td class="u" data-fill="market">market administration</td><td>kcca@2026</td></tr>
          <tr><td class="u" data-fill="auditor">internal audit</td><td>kcca@2026</td></tr>
          <tr><td class="u" data-fill="viewer">read-only viewer</td><td>kcca@2026</td></tr>
        </table>
        <p class="xsmall muted" style="margin:9px 0 0">
          Each role sees a different set of dashboards and actions.
        </p>
      </div>

      <p class="xsmall mt-2">
        <a href="../pages/portal.php">I am a trader — find gazetted space near me</a>
      </p>

      <div class="login-footer">
        <strong style="color:var(--ink-2)"><?= e($a['legal_name']) ?></strong><br>
        <?= e($a['address']) ?><br>
        Tel <?= e($a['telephone']) ?> · Toll free <?= e($a['toll_free']) ?> ·
        <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a><br>
        <a href="https://www.kcca.go.ug" target="_blank" rel="noopener">www.kcca.go.ug</a> ·
        <a href="https://www.kcca.go.ug/privacy" target="_blank" rel="noopener">Privacy policy</a> ·
        <a href="https://www.kcca.go.ug/terms" target="_blank" rel="noopener">Terms of use</a>
      </div>
    </div>
  </main>
</div>

<script>
document.querySelectorAll('[data-fill]').forEach(function (row) {
  row.addEventListener('click', function () {
    document.getElementById('username').value = row.getAttribute('data-fill');
    document.getElementById('password').value = 'kcca@2026';
    document.getElementById('password').focus();
  });
});
</script>
</body>
</html>