<?php
/**
 * Sign in.
 *
 * One form, and beneath it the roles that exist — click a role and the form
 * fills itself and submits, because the demonstration accounts are fixed and
 * asking an officer to type a username adds nothing. A trader who has no
 * account is sent to the portal instead, which needs no sign-in.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if (Auth::check()) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$f = facts();
$a = $f['authority'];

$error = '';
$old   = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = ['username' => (string) ($_POST['username'] ?? '')];

    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session has expired. Please try again.';
    } else {
        $ok = Auth::attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($ok) {
            header('Location: ../pages/dashboard.php');
            exit;
        }
        $error = 'That username and password do not match. Please try again.';
    }
}

/* The demonstration accounts, highest role first. Only an account that
   still uses the shared demo password is listed, so the page never offers
   a sign-in that would fail. */
$accounts = [];
foreach (Auth::roles() as $role) {
    $u = Database::one('SELECT username, password_hash FROM users WHERE role = ? ORDER BY id LIMIT 1', [$role]);
    if (!$u) {
        continue;
    }

    $password = $role === 'administrator' ? 'KCCA VRSP 2026' : 'kcca@2026';
    if (!password_verify($password, (string) $u['password_hash'])) {
        continue;
    }

    $accounts[] = [
        'username' => $u['username'],
        'password' => $password,
        'role'     => $role,
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#DC351D">
<title>Sign in · <?= e(APP_SHORT) ?></title>
<link rel="icon" type="image/png" href="../assets/brand/kcca-favicon.png">
<link rel="stylesheet" href="../assets/css/kcca.css">
<link rel="stylesheet" href="../assets/css/activities.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="../assets/css/dark.css?v=<?= e(APP_VERSION) ?>">
<script>
/* Applied before first paint so a dark-mode reader never sees a white
   flash while the stylesheets settle. */
(function(){try{var c=document.cookie.match(/(^| )kcca-theme=([^;]+)/),t=c?c[2]:localStorage.getItem('kcca-theme');
if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
</head>
<body>

<div class="login-wrap">
  <main class="login-main">
    <div class="login-card wide">

      <button type="button" class="login-theme" data-theme-toggle aria-pressed="false"
              aria-label="Switch to dark mode" title="Switch to dark mode">
        <?= KCCA_icon('moon', 15) ?><span class="moon-text">Dark</span>
        <?= KCCA_icon('sun', 15) ?><span class="sun-text">Light</span>
      </button>

      <img class="login-logo" src="../assets/brand/kcca-logo.png" alt="Kampala Capital City Authority">

      <h2>Sign in</h2>
      <p class="sub">Kampala Capital City Authority · Vendor Relocation</p>

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

      <?php if ($accounts): ?>
        <div class="role-pick">
          <h3>Sign in as</h3>
          <div class="role-grid">
            <?php foreach ($accounts as $ac): ?>
              <button type="button" class="role-card" data-login
                      data-username="<?= e($ac['username']) ?>"
                      data-password="<?= e($ac['password']) ?>">
                <span class="role-lvl">Level <?= Auth::rank($ac['role']) ?></span>
                <span class="role-name"><?= e(Auth::remit($ac['role'])['label']) ?></span>
                <span class="role-sub"><?= e(Auth::remit($ac['role'])['blurb']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <p class="xsmall mt-2" style="text-align:center">
        <a href="../pages/portal.php">I am a trader — find gazetted space near me</a>
      </p>

      <div class="login-footer">
        <strong style="color:var(--ink-2)"><?= e($a['legal_name']) ?></strong><br>
        <?= e($a['address']) ?><br>
        Tel <?= e($a['telephone']) ?> · Toll free <?= e($a['toll_free']) ?> ·
        <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a><br>
        <a href="https://www.kcca.go.ug" target="_blank" rel="noopener">www.kcca.go.ug</a>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/activities.js?v=<?= e(APP_VERSION) ?>"></script>
<script>
/* The role cards sit outside the form, so the form is found by its field
   rather than by climbing from the button. */
document.querySelectorAll('[data-login]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var form = document.getElementById('username').form;
    if (!form) return;
    form.querySelector('#username').value = btn.getAttribute('data-username');
    form.querySelector('#password').value = btn.getAttribute('data-password');
    form.submit();
  });
});
</script>

</body>
</html>