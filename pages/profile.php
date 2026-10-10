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
        $saved = 'Your contact details have been updated.';
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
        $saved = 'Your password has been changed.';
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

<?php require __DIR__ . '/../layouts/footer.php'; ?>