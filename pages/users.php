<?php
/**
 * Accounts — who can sign in, and at what level of authority.
 *
 * An officer here sees only the accounts they are entitled to change.
 * "Entitled" is decided by position in the order of authority: nobody edits
 * an account at or above their own level, and nobody edits their own —
 * which is what their profile is for. That is the whole rule, and it is one
 * rule rather than a second permission list to keep in step.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$me   = Auth::user();
$canEdit = Auth::can('manage');

/* Who this officer may edit. */
$all = Database::all(
    'SELECT u.id, u.username, u.full_name, u.title, u.directorate, u.role, u.division_id,
            u.email, u.phone, u.is_active, u.last_login_at, d.name AS division_name
       FROM users u LEFT JOIN divisions d ON d.id = u.division_id
      ORDER BY u.id'
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

/* Editing one account. */
$openId = (int) ($_GET['user'] ?? $_POST['user_id'] ?? 0);
$open   = null;
foreach ($editable as $u) {
    if ((int) $u['id'] === $openId) { $open = $u; break; }
}

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!$canEdit) {
        jsonOut(['error' => 'Your role cannot change accounts.'], 403);
    }
    Auth::requireCsrf();

    $id  = (int) ($_POST['user_id'] ?? 0);
    $row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);

    if (!$row) {
        $errors['_form'] = 'That account does not exist.';
    } elseif ((int) $row['id'] === Auth::id()) {
        $errors['_form'] = 'You cannot edit your own account here. Use your profile instead.';
    } elseif (Auth::rank() <= Auth::rank((string) $row['role'])) {
        $errors['_form'] = sprintf(
            'You cannot change %s’s account — they hold the same or a higher level of authority.',
            $row['full_name']
        );
    } else {
        $v = new Validator($_POST, [
            'full_name'  => ['type' => 'text', 'label' => 'Full name', 'required' => true, 'min' => 3, 'max' => 140],
            'title'      => ['type' => 'text', 'label' => 'Position', 'max' => 140],
            'email'      => ['type' => 'email', 'label' => 'Email address'],
            'phone'      => ['type' => 'phone', 'label' => 'Telephone number'],
            'division_id'=> ['type' => 'int', 'label' => 'Division'],
            'role'       => ['type' => 'enum', 'label' => 'Role', 'required' => true,
                              'options' => Auth::roles()],
        ]);
        $v->checkExists('division_id', 'divisions', $_POST['division_id'] ?? null);

        if ($v->fails()) {
            $errors = $v->errors();
            $values = $_POST;
            $open   = $row;
        } else {
            $data = $v->validated();
            $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;

            /* Nobody may promote an account to their own level or above,
               which would let an officer create a peer. */
            if ($data['role'] !== 'administrator'
                && Auth::rank() <= Auth::rank($data['role'])
                && (string) $row['role'] !== $data['role']) {
                $errors['role'] = sprintf(
                    'You cannot appoint somebody to %s — that is your own level of authority or above.',
                    Auth::remit($data['role'])['label']
                );
                $values = $_POST;
                $open   = $row;
            } else {
                Database::update('users', $data, 'id = :id', ['id' => $id]);
                Auth::log('update-account', 'users', $id,
                    $row['username'] . ' — ' . implode(', ', array_keys($data)));
                header('Location: users.php?saved=' . $id);
                exit;
            }
        }
    }
}

/* Request a password reset link, rather than an officer setting a password. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    if (!$canEdit) {
        jsonOut(['error' => 'Your role cannot reset passwords.'], 403);
    }
    Auth::requireCsrf();

    $id  = (int) ($_POST['user_id'] ?? 0);
    $row = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$row) {
        jsonOut(['error' => 'That account does not exist.'], 404);
    }
    if (Auth::rank() <= Auth::rank((string) $row['role'])) {
        jsonOut(['error' => 'You cannot reset that account\'s password.'], 403);
    }

    $temp = 'KCCA-' . strtoupper(bin2hex(random_bytes(4)));
    Database::run('UPDATE users SET password_hash = ? WHERE id = ?',
        [password_hash($temp, PASSWORD_DEFAULT), $id]);

    Auth::log('reset-password', 'users', $id, $row['username'] . ' — temporary password issued');
    jsonOut([
        'ok'      => true,
        'message' => 'Give this temporary password to ' . $row['full_name'] . ' and have them change it on first sign-in.',
        'temp'    => $temp,
    ]);
}

$divisions = Database::all('SELECT id, name, code FROM divisions ORDER BY name');
$roles     = Auth::roles();

$pageTitle = 'Accounts';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb"><a href="dashboard.php">Command</a><span class="sep">/</span>Accounts</div>
    <div class="eyebrow">Access and authority</div>
    <h1>Accounts</h1>
    <p>
      <?php if ($canEdit): ?>
        <?= num(count($editable)) ?> account(s) below your own level of authority.
      <?php else: ?>
        Read-only for your role.
      <?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <?php if (isset($_GET['saved'])): ?>
      <span class="badge ok">Saved</span>
    <?php endif; ?>
    <a class="btn btn-ghost" href="audit.php"><?= KCCA_icon('log', 15) ?> Audit trail</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-bad">
    <strong><?= count($errors) ?> field<?= count($errors) === 1 ? '' : 's' ?> could not be saved.</strong>
    Everything else was kept — correct only what is marked below.
  </div>
<?php endif; ?>

<!-- ==================== YOUR OWN ACCOUNT ===================== -->
<?php if ($mine): ?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Your account</h2>
      <p class="desc">You cannot edit this here — it is yours. Use your profile.</p>
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
      <div class="stat">
        <div class="label">Role</div>
        <div class="value" style="font-size:18px"><?= e(Auth::remit($mine['role'])['label']) ?></div>
        <div class="meta">level <?= Auth::rank($mine['role']) ?></div>
      </div>
      <div class="stat info">
        <div class="label">Division</div>
        <div class="value" style="font-size:18px"><?= e($mine['division_name'] ?: 'All five') ?></div>
        <div class="meta"><?= e($mine['directorate'] ?: '—') ?></div>
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

<!-- ==================== ACCOUNTS YOU MAY EDIT ================ -->
<?php if ($canEdit && $editable): ?>
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Accounts you may change</h2>
      <p class="desc">Only those below your own level of authority are editable.</p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Officer</th><th>Role</th><th class="center">Level</th><th>Division</th>
              <th class="center">Active</th><th>Last sign-in</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($all as $u):
            if ((int) $u['id'] === Auth::id()) continue;
            $level = Auth::rank((string) $u['role']);
            $can   = $canEdit && $level < Auth::rank(); ?>
            <tr>
              <td>
                <strong><?= e($u['full_name']) ?></strong>
                <div class="xsmall muted"><?= e($u['username']) ?><?= $u['title'] ? ' · ' . e($u['title']) : '' ?></div>
              </td>
              <td class="xsmall"><?= e(Auth::remit((string) $u['role'])['label']) ?></td>
              <td class="center"><span class="badge <?= $level >= 6 ? 'bad' : ($level >= 5 ? 'warn' : 'muted') ?>"><?= $level ?></span></td>
              <td class="xsmall"><?= e($u['division_name'] ?: '—') ?></td>
              <td class="center"><?= $u['is_active'] ? '<span class="badge ok">Yes</span>' : '<span class="badge bad">No</span>' ?></td>
              <td class="xsmall muted"><?= $u['last_login_at'] ? e(fmtDate($u['last_login_at'], 'd M H:i')) : 'never' ?></td>
              <td class="right">
                <?php if ($can): ?>
                  <a class="btn btn-ghost btn-sm"
                     href="users.php?user=<?= (int) $u['id'] ?>#edit">Edit details</a>
                <?php else: ?>
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
    An account at or above your own level is locked. Nobody edits their own
    account here.
  </div>
</div>
<?php elseif ($canEdit): ?>
  <div class="empty">
    <div class="big"><?= KCCA_icon('people', 28) ?></div>
    <h3>No accounts below your level</h3>
    <p>You are at the top of the order of authority, so there is nobody for you to edit.</p>
  </div>
<?php endif; ?>

<!-- ==================== THE EDIT FORM ======================== -->
<?php if ($open): ?>
<div class="card" id="edit">
  <div class="card-head">
    <div class="grow">
      <h2>Editing <?= e($open['full_name']) ?></h2>
      <p class="desc">
        Level <?= Auth::rank((string) $open['role']) ?> · currently <?= e(Auth::remit((string) $open['role'])['label']) ?>
      </p>
    </div>
    <a class="btn btn-quiet btn-sm" href="users.php">Close</a>
  </div>
  <form method="post" action="users.php">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="user_id" value="<?= (int) $open['id'] ?>">
    <div class="card-body">
      <?php if (isset($errors['_form'])): ?>
        <div class="alert alert-bad"><?= e($errors['_form']) ?></div>
      <?php endif; ?>

      <div class="grid g2">
        <div class="field">
          <label for="full_name">Full name <span class="req">*</span></label>
          <input type="text" id="full_name" name="full_name" required maxlength="140"
                 value="<?= e($values['full_name'] ?? $open['full_name']) ?>"
                 class="<?= isset($errors['full_name']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['full_name'])): ?>
            <span class="hint error"><?= e($errors['full_name']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="title">Position</label>
          <input type="text" id="title" name="title" maxlength="140"
                 value="<?= e($values['title'] ?? ($open['title'] ?? '')) ?>"
                 placeholder="e.g. Mayor, Nakawa Division">
          <?php if (isset($errors['title'])): ?>
            <span class="hint error"><?= e($errors['title']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email"
                 value="<?= e($values['email'] ?? ($open['email'] ?? '')) ?>"
                 class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['email'])): ?>
            <span class="hint error"><?= e($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="phone">Telephone number</label>
          <input type="text" id="phone" name="phone"
                 value="<?= e($values['phone'] ?? ($open['phone'] ?? '')) ?>"
                 class="<?= isset($errors['phone']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['phone'])): ?>
            <span class="hint error"><?= e($errors['phone']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="division_id">Division</label>
          <select id="division_id" name="division_id">
            <option value="">All five divisions</option>
            <?php foreach ($divisions as $d): ?>
              <option value="<?= (int) $d['id'] ?>"
                <?= (string) ($values['division_id'] ?? $open['division_id']) === (string) $d['id'] ? 'selected' : '' ?>>
                <?= e($d['name']) ?> (<?= e($d['code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">A Division Mayor with no division sees the whole city.</span>
        </div>

        <div class="field">
          <label for="role">Role</label>
          <select id="role" name="role" required class="<?= isset($errors['role']) ? 'invalid' : '' ?>">
            <?php foreach ($roles as $r): $lv = Auth::rank($r); ?>
              <option value="<?= e($r) ?>"
                <?= (string) ($values['role'] ?? $open['role']) === (string) $r ? 'selected' : '' ?>
                <?= $lv >= Auth::rank() ? 'disabled' : '' ?>>
                <?= e(Auth::remit($r)['label']) ?> — level <?= $lv ?><?= $lv >= Auth::rank() ? ' (above you)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['role'])): ?>
            <span class="hint error"><?= e($errors['role']) ?></span>
          <?php else: ?>
            <span class="hint">You cannot appoint somebody to your own level or above.</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label>Account status</label>
        <label class="row-tight" style="margin-top:6px">
          <input type="checkbox" name="is_active" value="1" style="width:auto"
                 <?= ($values['is_active'] ?? $open['is_active']) ? 'checked' : '' ?>>
          <span class="small">Active — the officer can sign in</span>
        </label>
        <span class="hint">Deactivating keeps the audit entries attributable but denies access.</span>
      </div>
    </div>
    <div class="card-foot">
      <div class="row">
        <button type="submit" class="btn btn-primary">Save changes</button>
        <a class="btn btn-ghost" href="users.php">Cancel</a>
        <span class="right small muted">
          Signed in as <?= e($open['username']) ?>
          <?php if ($open['last_login_at']): ?>
            · last signed in <?= e(fmtDate($open['last_login_at'], 'd M Y H:i')) ?>
          <?php endif; ?>
        </span>
      </div>
    </div>
  </form>

  <div class="card-foot">
    <div class="row">
      <div class="grow small muted">
        <strong>Password.</strong> Passwords are stored hashed and are never shown. If this officer
        has forgotten theirs, issue a temporary one — they change it on first sign-in.
      </div>
      <button type="button" class="btn btn-ghost btn-sm" data-reset-pw="<?= (int) $open['id'] ?>">
        Issue a temporary password
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
/* Issue a temporary password. The reply is shown once, in full, because it
   cannot be recovered later — the hash is what is stored. */
document.querySelectorAll('[data-reset-pw]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-reset-pw');
    KCCA.ask(
      'Issue a temporary password',
      'The officer will have to change it when they next sign in. This is the only time it is shown.',
      { label: 'Why is it being reset?', required: true, rows: 2 }
    ).then(function (why) {
      if (why === null) return;
      KCCA.post(KCCA.api('api/account_action.php'), {
        action: 'reset-password', user_id: id, reason: why
      }).then(function (res) {
        if (res && res.ok) {
          KCCA.flash(res.message + '  ' + res.temp, 'ok', 20000);
        } else {
          KCCA.flash((res && res.error) || 'The password could not be reset.', 'bad');
        }
      });
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>