<?php
/**
 * Settings — the values an administrator can change without a deployment.
 *
 * Engine weights, model calibration, and programme policy. Anything shown
 * here takes effect on the next request; nothing is cached beyond the
 * request.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$groups = Settings::all();
$errors = [];
$saved   = false;

/* ---------------- Save ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save')) {
    if (!Auth::can('manage')) {
        jsonOut(['error' => 'Your role cannot change settings.'], 403);
    }
    Auth::requireCsrf();

    $saveErrors = [];

    /* Build the field specs from what the database declares, so a value
       outside its declared bounds is refused rather than stored. */
    foreach ($groups as $groupRows) {
        foreach ($groupRows as $row) {
            if (!$row['is_editable']) {
                continue;
            }
            $key = $row['setting_key'];
            if (!array_key_exists($key, $_POST)) {
                continue;
            }

            $spec = [
                'label'   => $row['label'],
                'min'     => $row['min_value'],
                'max'     => $row['max_value'],
                'options' => $row['input_type'] === 'select'
                    ? (json_decode((string) $row['options_json'], true) ?: []) : null,
            ];

            $err = Settings::put($key, (string) $_POST[$key], $spec);
            if ($err !== null) {
                $saveErrors[$key] = $err;
            }
        }
    }

    if ($saveErrors === []) {
        Auth::log('update-settings', 'settings', null,
            'Settings changed: ' . implode(', ', array_keys(array_diff_key($_POST, ['csrf' => 1, 'action' => 1]))));
        header('Location: settings.php?saved=1');
        exit;
    }

    $errors = $saveErrors;
}

/* ---------------- Backup ---------------- */
$backupNotice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'backup')) {
    if (!Auth::can('manage')) {
        jsonOut(['error' => 'Your role cannot take a backup.'], 403);
    }
    Auth::requireCsrf();

    try {
        require_once __DIR__ . '/../lib/Backup.php';
        $result = Backup::create(trim((string) ($_POST['reason'] ?? '')));
        Auth::log('create-backup', 'backups', $result['id'],
            $result['reference'] . ' — ' . num($result['rows']) . ' rows');
        $backupNotice = [
            'tone' => 'ok',
            'text' => sprintf('Backup %s written: %s rows, %s. Checksum %s.',
                $result['reference'], num($result['rows']),
                $result['bytes'] > 1048576
                    ? number_format($result['bytes'] / 1048576, 1) . ' MB'
                    : number_format($result['bytes'] / 1024) . ' KB',
                substr((string) $result['checksum'], 0, 12)),
        ];
    } catch (Throwable $e) {
        $backupNotice = ['tone' => 'bad', 'text' => $e->getMessage()];
    }
}

require_once __DIR__ . '/../lib/Backup.php';
$backupStats = Backup::stats();
$backups     = Backup::recent(8);
$canManage   = Auth::can('manage');

$groupLabels = [
    'relocation' => ['Relocation engine weights', 'How a trader is matched to a market. Must total 100%.',
        'swap'],
    'congestion' => ['Congestion engine weights', 'How badly a site needs clearing. Must total 100%.',
        'pulse'],
    'reach'      => ['Reach model calibration', 'How footfall becomes customers, and what counts as a strong market.', 'chart'],
    'programme'  => ['Programme policy', 'How offers and recommendations are handled.', 'clock'],
    'authority'  => ['Public information', 'Shown on the trader portal.', 'building'],
];

$pageTitle = 'Settings';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Settings
    </div>
    <div class="eyebrow">Administration</div>
    <h1>Settings</h1>
    <p>
      The policy values behind the two engines and the reach model. Changing a weight changes how every
      recommendation is scored from the next request onward — so each change is recorded in the audit
      trail with the name of the officer who made it.
    </p>
  </div>
  <div class="actions">
    <?php if (isset($_GET['saved'])): ?>
      <span class="badge ok">Saved</span>
    <?php endif; ?>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<?php if (!$canManage): ?>
  <div class="alert alert-info">
    Your role can read these settings but not change them. Only the System Administrator and the
    Executive Director may alter engine weights.
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-bad">
    <strong>Some values were not saved.</strong> Correct the highlighted fields.
  </div>
<?php endif; ?>

<?php if ($backupNotice): ?>
  <div class="alert alert-<?= e($backupNotice['tone']) ?>"><?= e($backupNotice['text']) ?></div>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
  <input type="hidden" name="action" value="save">

  <div class="grid g2">
    <?php foreach ($groupLabels as $key => [$title, $desc, $icon]): ?>
      <?php if (!isset($groups[$key])) { continue; } ?>
      <div class="card">
        <div class="card-head">
          <div class="grow">
            <h2><?= KCCA_icon($icon, 16) ?> <?= e($title) ?></h2>
            <p class="desc"><?= e($desc) ?></p>
          </div>
        </div>
        <div class="card-body">
          <?php foreach ($groups[$key] as $s):
              $value = $s['setting_value'];
              $bad   = isset($errors[$s['setting_key']]);
          ?>
            <div class="field">
              <label for="<?= e($s['setting_key']) ?>"><?= e($s['label']) ?></label>

              <?php if ($s['input_type'] === 'boolean'): ?>
                <label class="row-tight" style="margin-top:4px">
                  <input type="checkbox" id="<?= e($s['setting_key']) ?>"
                         name="<?= e($s['setting_key']) ?>" value="1"
                         <?= in_array(strtolower($value), ['1','true','yes','on'], true) ? 'checked' : '' ?>
                         style="width:auto" <?= $canManage ? '' : 'disabled' ?>>
                  <span class="small">Enabled</span>
                </label>

              <?php elseif ($s['input_type'] === 'textarea'): ?>
                <textarea id="<?= e($s['setting_key']) ?>" name="<?= e($s['setting_key']) ?>"
                          rows="3" maxlength="255" <?= $canManage ? '' : 'disabled' ?>><?= e($value) ?></textarea>

              <?php elseif ($s['input_type'] === 'select'):
                  $options = json_decode((string) $s['options_json'], true) ?: []; ?>
                <select id="<?= e($s['setting_key']) ?>" name="<?= e($s['setting_key']) ?>"
                        <?= $canManage ? '' : 'disabled' ?>>
                  <?php foreach ($options as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= (string) $value === (string) $opt ? 'selected' : '' ?>>
                      <?= e($opt) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

              <?php else: ?>
                <input type="<?= $s['input_type'] === 'number' ? 'number' : 'text' ?>"
                       id="<?= e($s['setting_key']) ?>" name="<?= e($s['setting_key']) ?>"
                       value="<?= e($value) ?>"
                       <?= $s['input_type'] === 'number' && $s['min_value'] !== null
                            ? 'min="' . e((string) $s['min_value']) . '" step="any"' : '' ?>
                       <?= $s['input_type'] === 'number' && $s['max_value'] !== null
                            ? 'max="' . e((string) $s['max_value']) . '"' : '' ?>
                       class="<?= $bad ? 'invalid' : '' ?>"
                       <?= $canManage ? '' : 'disabled' ?>>
              <?php endif; ?>

              <?php if ($bad): ?>
                <span class="hint error"><?= e($errors[$s['setting_key']]) ?></span>
              <?php elseif ($s['description']): ?>
                <span class="hint"><?= e($s['description']) ?></span>
              <?php endif; ?>

              <?php if ($s['min_value'] !== null && $s['input_type'] === 'number'): ?>
                <span class="xsmall muted">
                  Range <?= e(rtrim(rtrim(number_format((float) $s['min_value'], 3), '0'), '.')) ?>
                  to <?= e(rtrim(rtrim(number_format((float) $s['max_value'], 3), '0'), '.')) ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($canManage): ?>
    <div class="card">
      <div class="card-body">
        <div class="row">
          <button type="submit" class="btn btn-primary">Save settings</button>
          <a class="btn btn-ghost" href="settings.php">Discard changes</a>
          <span class="right small muted">
            Weights are normalised to 100% when they are read, so a set that does not total 100 is still applied fairly.
          </span>
        </div>
      </div>
    </div>
  <?php endif; ?>
</form>

<!-- ======================= CURRENT VALUES ======================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Weights in force</h2>
      <p class="desc">
        Exactly what the engines are using now, normalised to 100%. This is the version that produced
        every recommendation currently on the register.
      </p>
    </div>
  </div>
  <div class="card-body">
    <div class="grid g2">
      <?php
      $relocation = Settings::relocationWeights();
      $congestion = Settings::zoneWeights();
      $sumR = array_sum($relocation);
      $sumC = array_sum($congestion);
      ?>
      <div>
        <h3 class="mb-1">Relocation engine</h3>
        <div class="ranked">
          <?php foreach ($relocation as $k => $w): ?>
            <div class="row-i">
              <span class="nm"><?= e(ucwords(str_replace('_', ' ', $k))) ?></span>
              <span class="v"><?= pct($w * 100, 0) ?></span>
              <span class="track"><span class="fill" style="width:<?= (float) round($w * 250, 1) ?>%"></span></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="xsmall muted mt-1">Totals <?= pct($sumR * 100, 1) ?></p>
      </div>
      <div>
        <h3 class="mb-1">Congestion engine</h3>
        <div class="ranked">
          <?php foreach ($congestion as $k => $w): ?>
            <div class="row-i">
              <span class="nm"><?= e(ucwords(str_replace('_', ' ', $k))) ?></span>
              <span class="v"><?= pct($w * 100, 0) ?></span>
              <span class="track"><span class="fill" style="width:<?= (float) round($w * 250, 1) ?>%"></span></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="xsmall muted mt-1">Totals <?= pct($sumC * 100, 1) ?></p>
      </div>
    </div>
  </div>
</div>

<!-- =========================== BACKUPS =========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Backups</h2>
      <p class="desc">
        A backup contains everything needed to rebuild the operational records — including personal
        data, so it must be handled under the Authority's data protection policy.
      </p>
    </div>
    <span class="badge <?= $backupStats['count'] > 0 ? 'ok' : 'warn' ?>">
      <?= num($backupStats['count']) ?> taken
    </span>
  </div>

  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat info">
        <div class="label">Last backup</div>
        <div class="value" style="font-size:17px"><?= $backupStats['last'] ? e(fmtDate($backupStats['last'], 'd M Y')) : 'Never' ?></div>
        <div class="meta"><?= $backupStats['last'] ? e(relativeDays($backupStats['last'])) : 'take one now' ?></div>
      </div>
      <div class="stat">
        <div class="label">Total size</div>
        <div class="value" style="font-size:17px"><?= e($backupStats['size_human']) ?></div>
      </div>
      <div class="stat gold">
        <div class="label">Records backed up</div>
        <div class="value" style="font-size:17px"><?= num($backupStats['rows']) ?></div>
      </div>
      <div class="stat <?= $backupStats['count'] > 0 ? 'ok' : 'warn' ?>">
        <div class="label">Coverage</div>
        <div class="value" style="font-size:17px"><?= num(count(Backup::TABLES)) ?></div>
        <div class="meta">core tables</div>
      </div>
    </div>

    <?php if ($canManage): ?>
      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="backup">
        <div class="field grow mb-0">
          <label for="reason">Reason for this backup</label>
          <input type="text" id="reason" name="reason" maxlength="255"
                 placeholder="e.g. Before the market data migration">
        </div>
        <button type="submit" class="btn btn-primary"><?= KCCA_icon('download', 14) ?> Take a backup now</button>
      </form>
    <?php endif; ?>

    <?php if ($backups): ?>
      <div class="divider"></div>
      <div class="table-wrap">
        <table class="data compact">
          <thead>
            <tr><th>Reference</th><th>Taken</th><th class="right">Rows</th><th class="right">Size</th>
                <th>Checksum</th><th>Taken by</th><th>Reason</th></tr>
          </thead>
          <tbody>
            <?php foreach ($backups as $b): ?>
              <tr>
                <td class="mono xsmall"><?= e($b['reference']) ?></td>
                <td class="xsmall"><?= e(fmtDate($b['created_at'])) ?></td>
                <td class="right num xsmall"><?= num((int) $b['row_count']) ?></td>
                <td class="right xsmall"><?= (int) $b['bytes'] > 1048576
                    ? number_format((int) $b['bytes'] / 1048576, 1) . ' MB'
                    : number_format((int) $b['bytes'] / 1024) . ' KB' ?></td>
                <td class="mono xsmall muted"><?= e(substr((string) $b['checksum'], 0, 12)) ?></td>
                <td class="xsmall"><?= e($b['requested_by']) ?></td>
                <td class="xsmall muted"><?= e($b['reason'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card-foot">
    Restore with <code>mysql <?= e(DB_NAME) ?> &lt; &lt;reference&gt;.sql</code>. The file carries a
    restore-order note in its header, so it does not need this application to be replayed.
  </div>
</div>

<!-- ======================= SYSTEM INFORMATION ==================== -->
<div class="card">
  <div class="card-head"><div class="grow"><h2>System</h2></div></div>
  <div class="card-body">
    <div class="grid g3">
      <dl class="kv">
        <dt>Application</dt><dd><?= e(APP_SHORT) ?> v<?= e(APP_VERSION) ?></dd>
        <dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd>
        <dt>Database</dt><dd><?= e(DB_NAME) ?> on <?= e(DB_HOST) ?>:<?= e(DB_PORT) ?></dd>
        <dt>Server time</dt><dd><?= e(date('d F Y H:i')) ?> EAT</dd>
      </dl>
      <dl class="kv">
        <dt>Reach model</dt><dd><?= e(ReachModel::VERSION) ?></dd>
        <dt>Footfall scale</dt><dd><?= num(Settings::num('reach_footfall_strong_daily', 12000)) ?> movements = full marks</dd>
        <dt>Capture base</dt><dd><?= pct(Settings::num('reach_capture_base', 0.012) * 100, 2) ?></dd>
        <dt>Trading days</dt><dd><?= num(Settings::int('reach_trading_days', 26)) ?> per month</dd>
      </dl>
      <dl class="kv">
        <?php
        $counts = [
            'Vendors'          => (int) Database::value('SELECT COUNT(*) FROM vendors'),
            'Locations'        => (int) Database::value('SELECT COUNT(*) FROM markets'),
            'Trading spaces'   => (int) Database::value('SELECT COUNT(*) FROM market_spaces'),
            'Footfall counts'  => (int) Database::value('SELECT COUNT(*) FROM footfall_observations'),
            'Recommendations'  => (int) Database::value('SELECT COUNT(*) FROM recommendations'),
            'Allocations'      => (int) Database::value('SELECT COUNT(*) FROM allocations'),
            'Relocation events'=> (int) Database::value('SELECT COUNT(*) FROM relocation_events'),
            'Audit entries'    => (int) Database::value('SELECT COUNT(*) FROM audit_log'),
        ];
        foreach ($counts as $label => $n): ?>
          <dt><?= e($label) ?></dt><dd><?= num($n) ?></dd>
        <?php endforeach; ?>
      </dl>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';