<?php
/**
 * Dashboard section: accounts and access (System Administrator).
 *
 * Who can sign in, at what level of authority, and what each level may do.
 * The rank column is the order of authority, which is also what decides
 * whether one officer may edit another's record.
 */

declare(strict_types=1);

$accounts = Database::all(
    'SELECT u.id, u.username, u.full_name, u.title, u.role, u.division_id,
            u.email, u.phone, u.name_basis, u.is_active, u.last_login_at, d.name AS division_name
       FROM users u LEFT JOIN divisions d ON d.id = u.division_id
      ORDER BY u.id'
);
$byRole = [];
foreach ($accounts as $a) {
    $byRole[$a['role']] = ($byRole[$a['role']] ?? 0) + 1;
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Accounts and access</h2>
      <p class="desc">
        <?= num(count($accounts)) ?> accounts, in the order of authority. Only an officer above
        another in that order may edit their record.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="users.php">Manage accounts</a>
  </div>
  <div class="card-body">
    <svg class="chart" data-chart='<?= ejs([
      'type' => 'hbar',
      'opts' => [
        'labelW' => 170, 'rowH' => 26,
        /* array_map over array_keys() hands the closure each key, not each
           row of the array — so build the items explicitly rather than
           reaching for a $r that is a string. */
        'items' => array_values(array_map(
          static fn(string $role): array => [
            'label' => Auth::remit($role)['label'],
            'value' => (int) ($byRole[$role] ?? 0),
            'color' => Auth::rank($role) >= 6 ? '#C0261A' : (Auth::rank($role) >= 5 ? '#EAA515' : '#0A6339'),
          ],
          Auth::roles()
        )),
      ],
    ]) ?>' aria-label="Accounts by level of authority"></svg>

    <div class="divider"></div>
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Officer</th><th>Role</th><th class="center">Rank</th><th>Division</th>
              <th class="center">Active</th><th>Last signed in</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $a): ?>
            <tr>
              <td>
                <strong><?= e($a['full_name']) ?></strong>
                <div class="xsmall muted">
                  <?= e($a['username']) ?><?= $a['title'] ? ' · ' . e($a['title']) : '' ?>
                </div>
                <?php if (($a['name_basis'] ?? 'illustrative') !== 'published'): ?>
                  <div class="xsmall" style="color:var(--ink-4)">illustrative officer</div>
                <?php endif; ?>
              </td>
              <td class="xsmall"><?= e(Auth::remit($a['role'])['label']) ?></td>
              <td class="center"><span class="badge <?= Auth::rank($a['role']) >= 6 ? 'bad' : (Auth::rank($a['role']) >= 5 ? 'warn' : 'muted') ?>"><?= Auth::rank($a['role']) ?></span></td>
              <td class="xsmall"><?= e($a['division_name'] ?: '—') ?></td>
              <td class="center"><?= $a['is_active']
                  ? '<span class="badge ok">Yes</span>'
                  : '<span class="badge bad">No</span>' ?></td>
              <td class="xsmall muted"><?= $a['last_login_at'] ? e(fmtDate($a['last_login_at'], 'd M Y H:i')) : 'never' ?></td>
              <td class="right">
                <?php if (Auth::canManageUser($a)): ?>
                  <a class="btn btn-ghost btn-sm" href="users.php?user=<?= (int) $a['id'] ?>">Edit</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">Nobody edits an account at or above their own level, and nobody edits their own.</div>
</div>
