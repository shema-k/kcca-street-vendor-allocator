<?php
/**
 * Accounts — add staff, and change what they can do.
 *
 * The System Administrator's instrument over every account in the system.
 * Everyone else sees the accounts they are entitled to change and nothing
 * more, and cannot add staff at all.
 *
 * The authority rules live in AccountService. This page draws; it does not
 * decide.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$canEdit = Auth::can('manage');

/* Who exists, and who this officer may touch. */
$all = Database::all(
    "SELECT u.id, u.username, u.full_name, u.title, u.directorate, u.role, u.division_id,
            u.email, u.phone, u.name_basis, u.is_active, u.last_login_at,
            d.name AS division_name
       FROM users u LEFT JOIN divisions d ON d.id = u.division_id
      ORDER BY FIELD(u.role, 'administrator','executive','mayor','planner',
                      'enforcement','revenue','vendor'), u.id"
);

$editable = [];
$mine     = null;
foreach ($all as $u) {
    if ((int) $u['id'] === Auth::id()) {
        $mine = $u;
        continue;
    }
    if ($canEdit && Auth::rank() > Auth::rank((string) $u['role'])) {
        $editable[] = $u;
    }
}

$divisions = Database::all('SELECT id, name, code FROM divisions ORDER BY name');

/* Which account the form shows: a new one, or one already in the list. */
$formId = (int) ($_GET['user'] ?? $_POST['user_id'] ?? 0);
$isNew  = isset($_GET['new']);
$open   = null;
if (!$isNew) {
    foreach ($all as $u) {
        if ((int) $u['id'] === $formId) { $open = $u; break; }
    }
}

$banner = '';
$errors = [];
$values = [];

/* ---------------- Create ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!$canEdit) {
        jsonOut(['error' => 'Your role cannot add staff.'], 403);
    }
    Auth::requireCsrf();

    $result = AccountService::create($_POST);

    if (!$result['ok']) {
        $errors = $result['errors'] + (isset($result['error']) ? ['_form' => $result['error']] : []);
        $values = $_POST;
        $isNew  = true;
    } else {
        $name = $_POST['full_name'] ?? 'The account';
        $banner = sprintf('%s has been added. They can sign in with the username and password set here.', $name);
        $all = Database::all(
            'SELECT u.id, u.username, u.full_name, u.title, u.directorate, u.role, u.division_id,
                    u.email, u.phone, u.name_basis, u.is_active, u.last_login_at,
                    d.name AS division_name
               FROM users u LEFT JOIN divisions d ON d.id = u.division_id
              ORDER BY u.id'
        );
        $editable = [];
        $mine     = null;
        foreach ($all as $u) {
            if ((int) $u['id'] === Auth::id()) { $mine = $u; continue; }
            if ($canEdit && Auth::rank() > Auth::rank((string) $u['role'])) { $editable[] = $u; }
        }
    }
}

/* ---------------- Update ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!$canEdit) {
        jsonOut(['error' => 'Your role cannot change accounts.'], 403);
    }
    Auth::requireCsrf();

    $id     = (int) ($_POST['user_id'] ?? 0);
    $result = AccountService::update($id, $_POST);

    if (!$result['ok']) {
        $errors = $result['errors'] + (isset($result['error']) ? ['_form' => $result['error']] : []);
        $values = $_POST;
        $open   = Database::one(
            'SELECT u.*, d.name AS division_name FROM users u
               LEFT JOIN divisions d ON d.id = u.division_id WHERE u.id = ?', [$id]
        );
    } else {
        $banner = 'The account has been updated.';
        $all = Database::all(
            'SELECT u.id, u.username, u.full_name, u.title, u.directorate, u.role, u.division_id,
                    u.email, u.phone, u.name_basis, u.is_active, u.last_login_at,
                    d.name AS division_name
               FROM users u LEFT JOIN divisions d ON d.id = u.division_id
              ORDER BY u.id'
        );
        $editable = [];
        $mine     = null;
        foreach ($all as $u) {
            if ((int) $u['id'] === Auth::id()) { $mine = $u; continue; }
            if ($canEdit && Auth::rank() > Auth::rank((string) $u['role'])) { $editable[] = $u; }
        }
    }
}

if (isset($_GET['created'])) {
    $banner = 'Staff member added.';
}

$pageTitle = 'Accounts';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb"><a href="dashboard.php">Command</a><span class="sep">/</span>Accounts</div>
    <div class="eyebrow">System administration</div>
    <h1>Accounts</h1>
    <p>
      <?php if ($canEdit): ?>
        Add staff, set what they may do, and set their password. Only accounts below your own
        level of authority can be changed.
      <?php else: ?>
        Read-only for your role.
      <?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <?php if ($canEdit): ?>
      <a class="btn btn-primary" href="users.php?new=1"><?= KCCA_icon('plus', 15) ?> Add staff</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($banner): ?>
  <div class="alert alert-ok"><?= e($banner) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-bad">
    <strong><?= count($errors) ?> field<?= count($errors) === 1 ? '' : 's' ?> could not be saved.</strong>
    Everything else was kept — correct only what is marked below.
  </div>
<?php endif; ?>

<!-- ==================== ADD OR EDIT A MEMBER OF STAFF ============== -->
<?php if (($isNew || $open) && $canEdit):
    $creating = $isNew;
    $row      = $creating ? [] : $open;
    $pick     = static fn(string $k, $def = '') => $values[$k] ?? $row[$k] ?? $def;
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2><?= $creating ? 'Add staff' : 'Editing ' . e($row['full_name']) ?></h2>
      <p class="desc">
        <?= $creating
            ? 'The username and password are set once here. Both can be changed later; the password cannot be recovered.'
            : 'Level ' . Auth::rank((string) $row['role']) . ' · ' . e(Auth::remit((string) $row['role'])['label']) ?>
      </p>
    </div>
    <a class="btn btn-quiet btn-sm" href="users.php">Close</a>
  </div>

  <form method="post" action="users.php">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="action" value="<?= $creating ? 'create' : 'save' ?>">
    <input type="hidden" name="user_id" value="<?= (int) ($row['id'] ?? 0) ?>">

    <div class="card-body">
      <?php if (isset($errors['_form'])): ?>
        <div class="alert alert-bad"><?= e($errors['_form']) ?></div>
      <?php endif; ?>

      <div class="grid g2">
        <?php if ($creating): ?>
          <div class="field">
            <label for="username">Username <span class="req">*</span></label>
            <input type="text" id="username" name="username" required maxlength="60"
                   value="<?= e($pick('username')) ?>"
                   class="<?= isset($errors['username']) ? 'invalid' : '' ?>"
                   placeholder="e.g. n.nyamwasa">
            <?php if (isset($errors['username'])): ?>
              <span class="hint error"><?= e($errors['username']) ?></span>
            <?php else: ?>
              <span class="hint">Lower-case letters, digits, dot, underscore and hyphen.</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="field">
          <label for="full_name">Full name <span class="req">*</span></label>
          <input type="text" id="full_name" name="full_name" required maxlength="140"
                 value="<?= e($pick('full_name')) ?>"
                 class="<?= isset($errors['full_name']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['full_name'])): ?>
            <span class="hint error"><?= e($errors['full_name']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="title">Position</label>
          <input type="text" id="title" name="title" maxlength="140"
                 value="<?= e($pick('title')) ?>"
                 placeholder="e.g. Mayor, Nakawa Division">
        </div>

        <div class="field">
          <label for="directorate">Directorate</label>
          <input type="text" id="directorate" name="directorate" maxlength="160"
                 value="<?= e($pick('directorate')) ?>"
                 placeholder="e.g. physical-planning">
          <span class="hint">As KCCA names it. Shown on the account and in the rail.</span>
        </div>

        <div class="field">
          <label for="role">Role <span class="req">*</span></label>
          <select id="role" name="role" required class="<?= isset($errors['role']) ? 'invalid' : '' ?>">
            <?php foreach (Auth::roles() as $r): $lv = Auth::rank($r); ?>
              <option value="<?= e($r) ?>"
                <?= (string) $pick('role', 'vendor') === (string) $r ? 'selected' : '' ?>
                <?= $lv > Auth::rank() ? 'disabled' : '' ?>>
                <?= e(Auth::remit($r)['label']) ?> — level <?= $lv ?><?= $lv > Auth::rank() ? ' (above you)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['role'])): ?>
            <span class="hint error"><?= e($errors['role']) ?></span>
          <?php else: ?>
            <span class="hint">You cannot appoint anybody to your own level or above.</span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="division_id">Division</label>
          <select id="division_id" name="division_id">
            <option value="">All five divisions</option>
            <?php foreach ($divisions as $d): ?>
              <option value="<?= (int) $d['id'] ?>"
                <?= (string) $pick('division_id') === (string) $d['id'] ? 'selected' : '' ?>>
                <?= e($d['name']) ?> (<?= e($d['code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">A Mayor sees only the division named here.</span>
        </div>

        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" value="<?= e($pick('email')) ?>"
                 class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['email'])): ?>
            <span class="hint error"><?= e($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="phone">Telephone number</label>
          <input type="text" id="phone" name="phone" value="<?= e($pick('phone')) ?>"
                 class="<?= isset($errors['phone']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['phone'])): ?>
            <span class="hint error"><?= e($errors['phone']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="divider"></div>
      <h4 class="mb-1"><?= $creating ? 'Password' : 'Set a new password' ?></h4>
      <div class="grid g2">
        <div class="field">
          <label for="password"><?= $creating ? 'Password <span class="req">*</span>' : 'New password' ?></label>
          <input type="password" id="password" name="password"
                 <?= $creating ? 'required' : '' ?> minlength="<?= AccountService::MIN_PASSWORD ?>"
                 class="<?= isset($errors['password']) ? 'invalid' : '' ?>"
                 autocomplete="new-password">
          <?php if (isset($errors['password'])): ?>
            <span class="hint error"><?= e($errors['password']) ?></span>
          <?php elseif ($creating): ?>
            <span class="hint">At least <?= AccountService::MIN_PASSWORD ?> characters. The member of staff should change it on first sign-in.</span>
          <?php else: ?>
            <span class="hint">Leave both blank to keep the current password.</span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="password_confirm">Repeat password</label>
          <input type="password" id="password_confirm" name="password_confirm"
                 minlength="<?= AccountService::MIN_PASSWORD ?>"
                 class="<?= isset($errors['password_confirm']) ? 'invalid' : '' ?>"
                 autocomplete="new-password">
          <?php if (isset($errors['password_confirm'])): ?>
            <span class="hint error"><?= e($errors['password_confirm']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label>Account status</label>
        <label class="row-tight" style="margin-top:6px">
          <input type="checkbox" name="is_active" value="1" style="width:auto"
                 <?= ($row['is_active'] ?? 1) ? 'checked' : '' ?>>
          <span class="small">Active — this person can sign in</span>
        </label>
        <span class="hint">Deactivating keeps the audit trail intact but blocks sign-in.</span>
      </div>
    </div>

    <div class="card-foot">
      <div class="row">
        <button type="submit" class="btn btn-primary">
          <?= $creating ? 'Add member of staff' : 'Save changes' ?>
        </button>
        <a class="btn btn-ghost" href="users.php">Cancel</a>
        <span class="right small muted">Every change is recorded in the audit trail.</span>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ==================== YOUR OWN ACCOUNT ========================= -->
<?php if ($mine): ?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Your account</h2>
      <p class="desc">You cannot change this here — that is what your profile is for.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="profile.php">My account</a>
  </div>
  <div class="card-body">
    <div class="grid g4">
      <div class="stat">
        <div class="label">Signed in as</div>
        <div class="value" style="font-size:18px"><?= e($mine['username']) ?></div>
        <div class="meta"><?= e($mine['full_name']) ?></div>
      </div>
      <div class="stat<?= Auth::rank() >= 6 ? ' bad' : '' ?>">
        <div class="label">Role</div>
        <div class="value" style="font-size:18px"><?= e(Auth::remit()['label']) ?></div>
        <div class="meta">level <?= Auth::rank() ?> of <?= Auth::maxRank() ?></div>
      </div>
      <div class="stat info">
        <div class="label">Division</div>
        <div class="value" style="font-size:18px"><?= e($mine['division_name'] ?: 'All five') ?></div>
        <div class="meta"><?= e($mine['directorate'] ? humanise($mine['directorate']) : '—') ?></div>
      </div>
      <div class="stat gold">
        <div class="label">Last sign-in</div>
        <div class="value" style="font-size:18px"><?= $mine['last_login_at'] ? e(fmtDate($mine['last_login_at'], 'd M')) : 'First' ?></div>
        <div class="meta"><?= $mine['last_login_at'] ? e(fmtDate($mine['last_login_at'], 'H:i')) : '' ?></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ==================== THE ACCOUNTS ============================= -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Accounts</h2>
      <p class="desc">
        <?= num(count($all)) ?> account(s).
        <?php if ($canEdit): ?>
          Only those below your own level of authority can be changed.
        <?php endif; ?>
      </p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Officer</th><th>Role</th><th class="center">Level</th><th>Division</th>
              <th class="center">Active</th><th>Last signed in</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($all as $u):
            $level = Auth::rank((string) $u['role']);
            $can   = $canEdit && (int) $u['id'] !== Auth::id() && $level < Auth::rank(); ?>
            <tr>
              <td>
                <strong><?= e($u['full_name']) ?></strong>
                <div class="xsmall muted">
                  <?= e($u['username']) ?><?= $u['title'] ? ' · ' . e($u['title']) : '' ?>
                </div>
                <?php if (($u['name_basis'] ?? 'illustrative') !== 'published'): ?>
                  <div class="xsmall" style="color:var(--ink-4)">illustrative officer</div>
                <?php endif; ?>
              </td>
              <td class="xsmall"><?= e(Auth::remit((string) $u['role'])['label']) ?></td>
              <td class="center">
                <span class="badge <?= $level >= 6 ? 'bad' : ($level >= 5 ? 'warn' : 'muted') ?>"><?= $level ?></span>
              </td>
              <td class="xsmall"><?= e($u['division_name'] ?: 'All five') ?></td>
              <td class="center">
                <?= $u['is_active'] ? '<span class="badge ok">Yes</span>' : '<span class="badge bad">No</span>' ?>
              </td>
              <td class="xsmall muted"><?= $u['last_login_at'] ? e(fmtDate($u['last_login_at'], 'd M H:i')) : 'never' ?></td>
              <td class="right nowrap">
                <?php if ($can): ?>
                  <a class="btn btn-ghost btn-sm"
                     href="users.php?user=<?= (int) $u['id'] ?>#edit" title="Edit this account">Edit</a>
                  <button type="button" class="btn btn-ghost btn-sm"
                          data-reset-pw="<?= (int) $u['id'] ?>"
                          title="Issue a temporary password">Reset</button>
                  <button type="button" class="btn btn-ghost btn-sm"
                          data-delete-user="<?= (int) $u['id'] ?>"
                          title="Block sign-in">Remove</button>
                <?php elseif ((int) $u['id'] === Auth::id()): ?>
                  <span class="xsmall muted" title="Edit this from your profile">your account</span>
                <?php elseif ($level >= Auth::rank()): ?>
                  <span class="xsmall muted" title="Same or higher level of authority">locked</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    A password is stored hashed and is never shown. Setting one takes effect immediately, and the
    change is recorded as a password event rather than an ordinary edit.
  </div>
</div>

<script>
/* Issue a temporary password: shown once, because it cannot be recovered. */
document.querySelectorAll('[data-reset-pw]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-reset-pw');
    KCCA.ask(
      'Issue a temporary password',
      'The member of staff should change it when they next sign in. This is the only time it is shown.',
      { label: 'Why is it being reset?', required: true, rows: 2 }
    ).then(function (why) {
      if (why === null) return;
      KCCA.post(KCCA.api('api/account_action.php'), {
        action: 'reset-password', user_id: id, reason: why
      }).then(function (res) {
        KCCA.flash(res.ok ? res.message + '  ' + res.temp : (res.error || 'Could not reset.'),
                   res.ok ? 'ok' : 'bad', 20000);
      });
    });
  });
});

/* Remove: blocks sign-in but keeps the audit trail. */
document.querySelectorAll('[data-delete-user]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-delete-user');
    KCCA.ask(
      'Block this account',
      'The person can no longer sign in. Everything they did stays in the audit trail.',
      { label: 'Reason', required: true, rows: 2, buttonLabel: 'Block sign-in' }
    ).then(function (why) {
      if (why === null) return;
      KCCA.post(KCCA.api('api/account_action.php'), {
        action: 'delete', user_id: id, reason: why
      }).then(function (res) {
        KCCA.flash(res.ok ? res.message : (res.error || 'Could not remove.'),
                   res.ok ? 'ok' : 'bad', 12000);
        if (res.ok) setTimeout(function () { location.reload(); }, 1200);
      });
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>