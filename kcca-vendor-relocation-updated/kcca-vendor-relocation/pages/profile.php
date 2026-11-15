<?php
/**
 * My account — where an officer maintains their own details.
 *
 * The accounts page deliberately refuses to let anybody edit their own
 * record, because that is how permissions get widened without anyone
 * noticing. This is the counterpart: the one place an officer can change
 * their own contact details and their own password, and nothing else.
 *
 * Role and division are not editable here. An officer cannot promote
 * themselves; that is an act of another officer, and it is recorded against
 * that officer in the audit trail.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$me    = Auth::user();
$meId  = Auth::id();
$errors = [];
$saved  = false;

/* ---------------- Details ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'details') {
    Auth::requireCsrf();

    $v = new Validator($_POST, [
        'full_name' => ['type' => 'text', 'label' => 'Full name', 'required' => true, 'min' => 3, 'max' => 140],
        'email'     => ['type' => 'email', 'label' => 'Email address'],
        'phone'     => ['type' => 'phone', 'label' => 'Telephone number'],
    ]);

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $data = $v->validated();
        Database::update('users', $data, 'id = :id', ['id' => $meId]);
        Auth::log('update-own-account', 'users', $meId, implode(', ', array_keys($data)));
        flashSet('Your contact details have been updated.', 'ok');
        header('Location: profile.php');
        exit;
    }
}

/* ---------------- Password ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    Auth::requireCsrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $next    = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    /* Read the hash here rather than taking it from Auth::user(): that row
       is fetched for every page, and there is no reason to put a password
       hash in front of code that only wants a display name. */
    $hash = (string) Database::value('SELECT password_hash FROM users WHERE id = ?', [$meId]);

    if ($current === '' || $next === '' || $confirm === '') {
        $errors['_password'] = 'All three password fields are required.';
    } elseif (!password_verify($current, $hash)) {
        $errors['current_password'] = 'That is not your current password.';
    } elseif (strlen($next) < 10) {
        $errors['new_password'] = 'A password must be at least 10 characters.';
    } elseif ($next === $current) {
        $errors['new_password'] = 'The new password must be different from the current one.';
    } elseif ($next !== $confirm) {
        $errors['confirm_password'] = 'The two new passwords do not match.';
    } else {
        Database::run(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($next, PASSWORD_DEFAULT), $meId]
        );
        Auth::log('change-own-password', 'users', $meId, 'Password changed by the account holder');
        flashSet('Your password has been changed.', 'ok');
        header('Location: profile.php');
        exit;
    }
}

/* Reload so the page shows what is actually stored. */
if ($saved) {
    Auth::clearUserCache();
    $me = Auth::user();
}

$remit = Auth::remit();
$myDiv = !empty($me['division_id'])
    ? Database::one('SELECT name, code, mayor FROM divisions WHERE id = ?', [(int) $me['division_id']])
    : null;

$pageTitle = 'My Account';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb"><a href="dashboard.php">Command</a><span class="sep">/</span>My Account</div>
    <div class="eyebrow"><?= e($remit['label']) ?></div>
    <h1>My account</h1>
    <p>
      Your own contact details and password. Role, division and directorate are set by the Authority
      and cannot be changed here — that is done by an officer above you in the order of authority, and
      it is recorded against them.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="dashboard.php">Back to the dashboard</a>
  </div>
</div>

<?php if ($saved): ?>
  <div class="alert alert-ok"><?= e($saved) ?></div>
<?php endif; ?>

<div class="grid g-2-1">

  <!-- ====================== CONTACT DETAILS ==================== -->
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Contact details</h2>
        <p class="desc">These are what the Authority and other officers use to reach you.</p>
      </div>
    </div>
    <form method="post" action="profile.php">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
      <input type="hidden" name="action" value="details">
      <div class="card-body">
        <div class="grid g2">
          <div class="field">
            <label for="full_name">Full name <span class="req">*</span></label>
            <input type="text" id="full_name" name="full_name" required maxlength="140"
                   value="<?= e($me['full_name'] ?? '') ?>"
                   class="<?= isset($errors['full_name']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['full_name'])): ?>
              <span class="hint error"><?= e($errors['full_name']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($me['email'] ?? '') ?>"
                   class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['email'])): ?>
              <span class="hint error"><?= e($errors['email']) ?></span>
            <?php else: ?>
              <span class="hint">Left blank means no email is recorded, which is allowed.</span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="phone">Telephone number</label>
            <input type="text" id="phone" name="phone" value="<?= e($me['phone'] ?? '') ?>"
                   class="<?= isset($errors['phone']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['phone'])): ?>
              <span class="hint error"><?= e($errors['phone']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="card-foot">
        <button type="submit" class="btn btn-primary">Save contact details</button>
        <span class="right small muted">Every change is recorded in the audit trail.</span>
      </div>
    </form>
  </div>

  <!-- ======================== PASSWORD ======================== -->
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Change your password</h2>
        <p class="desc">At least 10 characters, and different from the current one.</p>
      </div>
    </div>
    <form method="post" action="profile.php">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
      <input type="hidden" name="action" value="password">
      <div class="card-body">
        <?php if (isset($errors['_password'])): ?>
          <div class="alert alert-bad"><?= e($errors['_password']) ?></div>
        <?php endif; ?>

        <div class="field">
          <label for="current_password">Current password</label>
          <input type="password" id="current_password" name="current_password" required
                 class="<?= isset($errors['current_password']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['current_password'])): ?>
            <span class="hint error"><?= e($errors['current_password']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="new_password">New password</label>
          <input type="password" id="new_password" name="new_password" required minlength="10"
                 class="<?= isset($errors['new_password']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['new_password'])): ?>
            <span class="hint error"><?= e($errors['new_password']) ?></span>
          <?php else: ?>
            <span class="hint">Ten characters minimum. A passphrase of three unrelated words is stronger than a short one with symbols.</span>
          <?php endif; ?>
        </div>

        <div class="field mb-0">
          <label for="confirm_password">Repeat the new password</label>
          <input type="password" id="confirm_password" name="confirm_password" required
                 class="<?= isset($errors['confirm_password']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['confirm_password'])): ?>
            <span class="hint error"><?= e($errors['confirm_password']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-foot">
        <button type="submit" class="btn btn-primary">Change password</button>
      </div>
    </form>
  </div>

  <!-- ==================== APPEARANCE (every account) ============= -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-head">
      <div class="grow">
        <h2>Appearance</h2>
        <p class="desc">How your own account looks. It is saved to your account and follows you to any device; nobody else is affected.</p>
      </div>
      <span class="small muted" id="pref-status" role="status" aria-live="polite"></span>
    </div>
    <div class="card-body">
      <div class="pref-list">
        <label class="pref-row">
          <span class="pref-text"><b>Glass appearance</b><small>Frosted panels over the colour background. Turn off for plain, solid panels.</small></span>
          <span class="switch"><input type="checkbox" data-pref="glass" <?= $layoutPrefs['glass'] === 'on' ? 'checked' : '' ?>><i></i></span>
        </label>
        <label class="pref-row">
          <span class="pref-text"><b>Animations</b><small>Entrances, counting figures, the photo slider and the moving background.</small></span>
          <span class="switch"><input type="checkbox" data-pref="motion" <?= $layoutPrefs['motion'] === 'on' ? 'checked' : '' ?>><i></i></span>
        </label>
        <label class="pref-row">
          <span class="pref-text"><b>Guidance text</b><small>Show the explanatory sentences under headings and fields.</small></span>
          <span class="switch"><input type="checkbox" data-pref="guide" <?= $layoutPrefs['guide'] === 'on' ? 'checked' : '' ?>><i></i></span>
        </label>
        <label class="pref-row">
          <span class="pref-text"><b>Compact layout</b><small>Tighter spacing, so more fits on the screen.</small></span>
          <span class="switch"><input type="checkbox" data-pref="density" <?= $layoutPrefs['density'] === 'compact' ? 'checked' : '' ?>><i></i></span>
        </label>
        <div class="pref-row">
          <span class="pref-text"><b>Light or dark</b><small>Switch between the light and dark colour schemes.</small></span>
          <button type="button" class="btn btn-ghost btn-sm" id="pref-theme"><?= KCCA_icon('moon', 14) ?> Switch</button>
        </div>
      </div>
    </div>
    <div class="card-foot">
      <button type="button" class="btn btn-ghost btn-sm" id="pref-reset">Restore the defaults</button>
    </div>
  </div>

  <?php if (ChangeFeed::allowed()): ?>
  <!-- ==================== NOTIFICATIONS (officers) ============= -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-head">
      <div class="grow">
        <h2>Alerts from colleagues</h2>
        <p class="desc">When another officer changes something in the shared register, the bell in the top bar tells you.</p>
      </div>
    </div>
    <div class="card-body">
      <div class="pref-list">
        <label class="pref-row">
          <span class="pref-text"><b>Pop-up alerts</b><small>Show a small notice when a colleague makes a change. The bell and its list stay on either way.</small></span>
          <span class="switch"><input type="checkbox" data-pref="notify" <?= $layoutPrefs['notify'] === 'on' ? 'checked' : '' ?>><i></i></span>
        </label>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ==================== YOUR DATA AND ACCOUNT (every account) ============= -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-head">
      <div class="grow">
        <h2>Your data and account</h2>
        <p class="desc">Keep a copy of what the system holds about you, or close your account.</p>
      </div>
    </div>
    <div class="card-body">
      <div class="pref-list">
        <div class="pref-row">
          <span class="pref-text"><b>Download my data</b><small>Your details, your settings and your recent activity, as one file.</small></span>
          <a class="btn btn-ghost btn-sm" data-no-fade href="../api/self_account.php?action=export"><?= KCCA_icon('download', 14) ?> Download</a>
        </div>
        <div class="pref-row">
          <span class="pref-text"><b>Delete my account</b>
            <small>You will be signed out and the account can never sign in again. Your contact details are erased. Work you did in the register stays, so the audit trail remains complete.</small></span>
          <button type="button" class="btn btn-danger btn-sm" id="del-open">Delete&hellip;</button>
        </div>
      </div>

      <form class="del-box" id="del-box" hidden autocomplete="off">
        <p class="small"><b>This cannot be undone.</b> Confirm it is you, then type <code>DELETE</code> to go ahead.</p>
        <div class="grid g2">
          <div class="field"><label for="del-pass">Your password</label>
            <input id="del-pass" type="password" autocomplete="current-password"></div>
          <div class="field"><label for="del-word">Type DELETE</label>
            <input id="del-word" type="text" autocomplete="off" spellcheck="false" placeholder="DELETE"></div>
        </div>
        <div class="row">
          <button type="button" class="btn btn-danger" id="del-go">Delete my account permanently</button>
          <button type="button" class="btn btn-quiet" id="del-cancel">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==================== WHAT THIS ACCOUNT CAN DO ============= -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-head">
      <div class="grow">
        <h2>What this account can do</h2>
        <p class="desc">Set by your role, not by anything on this page.</p>
      </div>
    </div>
    <div class="card-body">
      <div class="grid g4 mb-2">
        <div class="stat">
          <div class="label">Signed in as</div>
          <div class="value" style="font-size:18px"><?= e($me['username'] ?? '') ?></div>
        </div>
        <div class="stat <?= Auth::rank() >= 6 ? 'bad' : (Auth::rank() >= 5 ? 'warn' : '') ?>">
          <div class="label">Role</div>
          <div class="value" style="font-size:18px"><?= e($remit['label']) ?></div>
          <div class="meta">level <?= Auth::rank() ?> of 7</div>
        </div>
        <div class="stat info">
          <div class="label">Division</div>
          <div class="value" style="font-size:18px"><?= e($myDiv['name'] ?? 'All five') ?></div>
          <div class="meta"><?= e($me['directorate'] ?: '—') ?></div>
        </div>
        <div class="stat gold">
          <div class="label">Last sign-in</div>
          <div class="value" style="font-size:18px"><?= !empty($me['last_login_at']) ? e(fmtDate($me['last_login_at'], 'd M')) : 'First' ?></div>
          <div class="meta"><?= !empty($me['last_login_at']) ? e(fmtDate($me['last_login_at'], 'H:i')) : 'sign-in' ?></div>
        </div>
      </div>

      <p class="small muted" style="margin-bottom:0">
        <?= e($remit['blurb']) ?>
        <?php if (Auth::role() === 'mayor' && $myDiv): ?>
          You act only within <?= e($myDiv['name']) ?> Division; every figure you see is confined to it.
        <?php endif; ?>
      </p>
    </div>
    <div class="card-foot">
      <?php if (Auth::can('manage')): ?>
        <a href="users.php">Change other officers' accounts</a> ·
      <?php endif; ?>
      <a href="audit.php?actor=<?= urlencode((string) ($me['full_name'] ?? '')) ?>">See your own audit trail</a>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var status = document.getElementById('pref-status');
  function say(ok) {
    if (!status) { return; }
    status.textContent = ok ? 'Saved' : 'Could not be saved. It is applied here for now.';
    clearTimeout(say.t); say.t = setTimeout(function () { status.textContent = ''; }, 2600);
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-pref]'), function (box) {
    box.addEventListener('change', function () {
      var key = box.getAttribute('data-pref');
      var val = key === 'density' ? (box.checked ? 'compact' : 'comfortable') : (box.checked ? 'on' : 'off');
      window.KCCAPrefs.set(key, val, say);
    });
  });
  var theme = document.getElementById('pref-theme');
  if (theme) { theme.addEventListener('click', function () {
    var t = document.querySelector('[data-theme-toggle]'); if (t) { t.click(); }
  }); }
  var box = document.getElementById('del-box'), open = document.getElementById('del-open');
  if (open && box) {
    open.addEventListener('click', function () { box.hidden = false; open.disabled = true; document.getElementById('del-pass').focus(); });
    document.getElementById('del-cancel').addEventListener('click', function () { box.hidden = true; open.disabled = false; });
    document.getElementById('del-go').addEventListener('click', function () {
      var btn = this;
      KCCA.postAction(KCCA.api('api/self_account.php'), {
        action: 'delete',
        password: document.getElementById('del-pass').value,
        confirm: document.getElementById('del-word').value
      }, { button: btn, busyLabel: 'Deleting…', reload: false,
           confirm: 'Delete your account permanently? You will be signed out and cannot sign in again.' })
      .then(function (res) {
        if (res && res.ok) { setTimeout(function () { location.href = '../auth/login.php'; }, 1200); }
      });
    });
  }
  var reset = document.getElementById('pref-reset');
  if (reset) { reset.addEventListener('click', function () {
    window.KCCAPrefs.set('glass', 'on'); window.KCCAPrefs.set('motion', 'on'); window.KCCAPrefs.set('guide', 'off');
    window.KCCAPrefs.set('notify', 'on'); window.KCCAPrefs.set('density', 'comfortable', say);
  }); }
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>