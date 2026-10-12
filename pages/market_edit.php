<?php
/**
 * Location record — add or edit a gazetted location.
 *
 * The System Administrator's instrument over the location register, which
 * is also what the map, the stall map and the vacancy figures all read from.
 *
 * One thing this page will not do quietly: change a public market's capacity
 * or vacancy without saying so. Those are the Authority's published figures,
 * and verify.php reconciles against them. So a change to them is allowed —
 * the officer owns the system — but the consequence is stated on the way
 * out, and the reconciliation check is re-run and shown.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('manage');

$vac = facts()['vacancy'];

$divisions = Database::all('SELECT id, name, code FROM divisions ORDER BY name');

$isNew = isset($_GET['new']) || (($_POST['action'] ?? '') === 'create');
$id    = (int) ($_GET['market'] ?? $_POST['market_id'] ?? 0);

$market = null;
if (!$isNew) {
    $market = Database::one('SELECT * FROM markets WHERE id = ?', [$id]);
    if (!$market) {
        header('Location: locations.php');
        exit;
    }
}

$errors = [];
$values = [];
$saved  = false;
$warn   = null;

/* -------------------------------------------------------------- */
/* Field rules                                                     */
/* -------------------------------------------------------------- */
$rules = [
    'code'            => ['type' => 'text', 'label' => 'Location code', 'required' => true,
                          'max' => 20, 'pattern' => '/^[A-Z]{2,3}-[0-9]{2}$/',
                          'pattern_message' => 'Three or four capitals, a hyphen, two digits — e.g. USF-01.'],
    'name'            => ['type' => 'text', 'label' => 'Location name', 'required' => true, 'max' => 120],
    'division_id'     => ['type' => 'int', 'label' => 'Division', 'required' => true, 'min' => 1],
    'parish'          => ['type' => 'text', 'label' => 'Parish', 'max' => 120],
    'category'        => ['type' => 'enum', 'label' => 'Category', 'required' => true,
                          'options' => ['public', 'private']],
    'market_type'     => ['type' => 'enum', 'label' => 'Site type', 'required' => true,
                          'options' => ['permanent', 'seasonal', 'temporary']],
    'ownership'       => ['type' => 'text', 'label' => 'Owned or operated by', 'max' => 160],
    'status'          => ['type' => 'enum', 'label' => 'Operational status', 'required' => true,
                          'options' => ['operational', 'upgrading', 'under-rehabilitation', 'closed']],
    'lat'             => ['type' => 'lat', 'label' => 'Latitude', 'required' => true],
    'lng'             => ['type' => 'lng', 'label' => 'Longitude', 'required' => true],
    'capacity'        => ['type' => 'int', 'label' => 'Capacity (pitches)', 'required' => true, 'min' => 0, 'max' => 100000],
    'registered_vendors' => ['type' => 'int', 'label' => 'Registered vendors', 'required' => true, 'min' => 0, 'max' => 100000],
    'vacant_spaces'   => ['type' => 'int', 'label' => 'Vacant spaces', 'required' => true, 'min' => 0, 'max' => 100000],
    'opened_year'     => ['type' => 'int', 'label' => 'Year opened', 'min' => 1900, 'max' => (int) date('Y')],
    'condition_score' => ['type' => 'int', 'label' => 'Condition score out of 5', 'required' => true, 'min' => 1, 'max' => 5],
    'data_basis'      => ['type' => 'enum', 'label' => 'Basis of the figures', 'required' => true,
                          'options' => ['official', 'derived', 'estimated']],
    'transport_stand' => ['type' => 'text', 'label' => 'Nearest transport stand', 'max' => 120],
    'admin_name'      => ['type' => 'text', 'label' => 'Market administrator', 'max' => 120],
    'admin_phone'     => ['type' => 'phone', 'label' => 'Administrator telephone'],
    'category_mix'    => ['type' => 'text', 'label' => 'Trades present', 'max' => 255],
    'notes'           => ['type' => 'textarea', 'label' => 'Notes', 'max' => 2000],
];

$facilities = [
    'water_available'  => 'Water supply',
    'power_available'  => 'Electricity',
    'toilets'          => 'Toilets',
    'waste_collection' => 'Waste collection',
    'storage_rooms'    => 'Storage rooms',
    'cold_chain'       => 'Cold chain',
    'accessible_ramps' => 'Accessible ramps',
];

/* -------------------------------------------------------------- */
/* Save                                                            */
/* -------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create', 'save'], true)) {
    Auth::requireCsrf();

    $v = new Validator($_POST, $rules);
    $v->checkExists('division_id', 'divisions', $_POST['division_id'] ?? null);

    /* A code names one location and only one. */
    $existingCode = Database::one(
        'SELECT id FROM markets WHERE code = ? AND id <> ?',
        [$_POST['code'] ?? '', $id]
    );
    if ($existingCode) {
        $v->addError('code', 'That location code is already used.');
    }

    /* Registered cannot exceed capacity, and vacant cannot exceed what is left. */
    $cap  = (int) ($_POST['capacity'] ?? 0);
    $reg  = (int) ($_POST['registered_vendors'] ?? 0);
    $free = (int) ($_POST['vacant_spaces'] ?? 0);
    if ($reg > $cap) {
        $v->addError('registered_vendors', 'Cannot exceed the capacity.');
    }
    if ($free > $cap - $reg) {
        $v->addError('vacant_spaces',
            'Cannot exceed ' . ($cap - $reg) . ' — the pitches left after registered vendors.');
    }

    if ($v->fails()) {
        $errors = $v->errors();
        $values = $_POST;
    } else {
        $data = $v->validated();
        foreach ($facilities as $col => $label) {
            $data[$col] = isset($_POST[$col]) ? 1 : 0;
        }

        /* Does this change break the published reconciliation? */
        $isPublicBefore = $market && $market['category'] === 'public';
        $isPublicAfter  = $data['category'] === 'public';
        $capacityDelta  = $data['capacity'] - (int) ($market['capacity'] ?? 0);
        $vacancyDelta   = $data['vacant_spaces'] - (int) ($market['vacant_spaces'] ?? 0);

        if (isPublicBefore($isPublicBefore, $isPublicAfter) && ($capacityDelta !== 0 || $vacancyDelta !== 0)) {
            $warn = sprintf(
                'This changes the Authority’s published public-market figures by %+d capacity '
              . 'and %+d vacancy. The reconciliation check will now report a difference.',
                $capacityDelta, $vacancyDelta
            );
        }

        if ($isNew) {
            $id = Database::insert('markets', $data);
            Auth::log('create-market', 'markets', $id, $data['name'] . ' (' . $data['code'] . ')');
            header('Location: locations.php?location=' . $id . '&saved=1');
            exit;
        }

        Database::update('markets', $data, 'id = :id', ['id' => $id]);
        Auth::log('update-market', 'markets', $id,
            $data['name'] . ' — changed: ' . implode(', ', array_keys($data)));

        header('Location: locations.php?location=' . $id . '&saved=1');
        exit;
    }
}

/**
 * Public figures are the published ones. A market changing category into or
 * out of "public" changes what is published, so treat it like a figure change.
 */
function isPublicBefore(bool $before, bool $after): bool
{
    return $before || $after;
}

$pick = static fn(string $k, $def = '') => $values[$k] ?? ($market[$k] ?? $def);

$pageTitle = $isNew ? 'Add a location' : 'Edit location';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="locations.php">Trading Locations</a><span class="sep">/</span>
      <?= $isNew ? 'Add' : e($market['name']) ?>
    </div>
    <div class="eyebrow">System administration</div>
    <h1><?= $isNew ? 'Add a location' : 'Edit ' . e($market['name']) ?></h1>
    <p>
      <?= $isNew
          ? 'A new gazetted location. Everything the map, the stall map and the vacancy figures read comes from this record.'
          : 'Every figure shown across the system for this location comes from this record.' ?>
    </p>
  </div>
  <div class="actions">
    <?php if (!$isNew): ?>
      <a class="btn btn-ghost" href="locations.php?location=<?= (int) $market['id'] ?>">View the record</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="locations.php">Back</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-bad">
    <strong><?= count($errors) ?> field<?= count($errors) === 1 ? '' : 's' ?> could not be saved.</strong>
    Everything else was kept — correct only what is marked below.
  </div>
<?php endif; ?>

<?php if ($warn): ?>
  <div class="alert alert-warn"><?= e($warn) ?></div>
<?php endif; ?>

<form method="post" action="market_edit.php">
  <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
  <input type="hidden" name="action" value="<?= $isNew ? 'create' : 'save' ?>">
  <input type="hidden" name="market_id" value="<?= (int) ($market['id'] ?? 0) ?>">

  <div class="grid g2">
    <!-- ============ IDENTITY ============ -->
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Identity</h2></div></div>
      <div class="card-body">
        <div class="grid g2">
          <div class="field">
            <label for="code">Location code <span class="req">*</span></label>
            <input type="text" id="code" name="code" required maxlength="20"
                   value="<?= e($pick('code')) ?>"
                   class="<?= isset($errors['code']) ? 'invalid' : '' ?>"
                   placeholder="e.g. USF-01"<?= $isNew ? '' : ' readonly' ?>>
            <?php if (isset($errors['code'])): ?>
              <span class="hint error"><?= e($errors['code']) ?></span>
            <?php else: ?>
              <span class="hint">Set once. Three or four capitals, a hyphen, two digits.</span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="name">Location name <span class="req">*</span></label>
            <input type="text" id="name" name="name" required maxlength="120"
                   value="<?= e($pick('name')) ?>"
                   class="<?= isset($errors['name']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['name'])): ?>
              <span class="hint error"><?= e($errors['name']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="division_id">Division <span class="req">*</span></label>
            <select id="division_id" name="division_id" required
                    class="<?= isset($errors['division_id']) ? 'invalid' : '' ?>">
              <option value="">Choose a division</option>
              <?php foreach ($divisions as $d): ?>
                <option value="<?= (int) $d['id'] ?>"
                  <?= (string) $pick('division_id') === (string) $d['id'] ? 'selected' : '' ?>>
                  <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['division_id'])): ?>
              <span class="hint error"><?= e($errors['division_id']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="parish">Parish</label>
            <input type="text" id="parish" name="parish" maxlength="120"
                   value="<?= e($pick('parish')) ?>">
          </div>

          <div class="field">
            <label for="category">Category <span class="req">*</span></label>
            <select id="category" name="category" required>
              <option value="public"  <?= $pick('category', 'public') === 'public'  ? 'selected' : '' ?>>
                Public — held by KCCA
              </option>
              <option value="private" <?= $pick('category', 'public') === 'private' ? 'selected' : '' ?>>
                Private
              </option>
            </select>
            <span class="hint">Public market figures are the published ones and are reconciled.</span>
          </div>

          <div class="field">
            <label for="market_type">Site type <span class="req">*</span></label>
            <select id="market_type" name="market_type" required>
              <?php foreach (['permanent', 'seasonal', 'temporary'] as $t): ?>
                <option value="<?= e($t) ?>" <?= $pick('market_type', 'permanent') === $t ? 'selected' : '' ?>>
                  <?= e(ucfirst($t)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label for="ownership">Owned or operated by</label>
            <input type="text" id="ownership" name="ownership" maxlength="160"
                   value="<?= e($pick('ownership')) ?>">
          </div>

          <div class="field">
            <label for="status">Operational status <span class="req">*</span></label>
            <select id="status" name="status" required>
              <?php foreach (['operational', 'upgrading', 'under-rehabilitation', 'closed'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $pick('status', 'operational') === $s ? 'selected' : '' ?>>
                  <?= e(humanise($s)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ THE FIGURES ============ -->
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>Figures</h2>
          <p class="desc">These are what the citywide totals reconcile against.</p>
        </div>
      </div>
      <div class="card-body">
        <div class="grid g3">
          <div class="field">
            <label for="capacity">Capacity (pitches) <span class="req">*</span></label>
            <input type="number" id="capacity" name="capacity" required min="0" max="100000"
                   value="<?= e($pick('capacity', 0)) ?>"
                   class="<?= isset($errors['capacity']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['capacity'])): ?>
              <span class="hint error"><?= e($errors['capacity']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="registered_vendors">Registered vendors <span class="req">*</span></label>
            <input type="number" id="registered_vendors" name="registered_vendors" required
                   min="0" max="100000"
                   value="<?= e($pick('registered_vendors', 0)) ?>"
                   class="<?= isset($errors['registered_vendors']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['registered_vendors'])): ?>
              <span class="hint error"><?= e($errors['registered_vendors']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="vacant_spaces">Vacant spaces <span class="req">*</span></label>
            <input type="number" id="vacant_spaces" name="vacant_spaces" required
                   min="0" max="100000"
                   value="<?= e($pick('vacant_spaces', 0)) ?>"
                   class="<?= isset($errors['vacant_spaces']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['vacant_spaces'])): ?>
              <span class="hint error"><?= e($errors['vacant_spaces']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="condition_score">Condition score out of 5 <span class="req">*</span></label>
            <select id="condition_score" name="condition_score" required>
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <option value="<?= $i ?>" <?= (string) $pick('condition_score', 3) === (string) $i ? 'selected' : '' ?>>
                  <?= $i ?><?= $i === 5 ? ' — as new' : ($i === 1 ? ' — poor' : '') ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="field">
            <label for="opened_year">Year opened</label>
            <input type="number" id="opened_year" name="opened_year" min="1900" max="<?= (int) date('Y') ?>"
                   value="<?= e($pick('opened_year')) ?>">
          </div>

          <div class="field">
            <label for="data_basis">Basis of the figures <span class="req">*</span></label>
            <select id="data_basis" name="data_basis" required>
              <?php foreach (['official', 'derived', 'estimated'] as $b): ?>
                <option value="<?= e($b) ?>" <?= $pick('data_basis', 'derived') === $b ? 'selected' : '' ?>>
                  <?= e(ucfirst($b)) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Shown on every export, so a reader knows what is published.</span>
          </div>
        </div>

        <div class="source">
          <span class="lbl <?= e((string) $pick('data_basis', 'derived')) ?>"><?= e(ucfirst((string) $pick('data_basis', 'derived'))) ?></span>
          <span>
            <?= (string) $pick('data_basis', 'derived') === 'official'
                ? 'These are figures the Authority has published.'
                : ((string) $pick('data_basis', 'derived') === 'derived'
                    ? 'Computed from published figures; shown as derived.'
                    : 'A survey estimate, not a published figure. Shown as estimated.') ?>
          </span>
        </div>
      </div>
      <div class="card-foot">
        Published public-market figures: <?= num($vac['public_markets']) ?> markets,
        <?= num($vac['public_vacant_spaces']) ?> vacant spaces.
      </div>
    </div>
  </div>

  <div class="grid g2">
    <!-- ============ WHERE IT IS ============ -->
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Where it is</h2></div></div>
      <div class="card-body">
        <div class="grid g2">
          <div class="field">
            <label for="lat">Latitude <span class="req">*</span></label>
            <input type="number" id="lat" name="lat" step="0.0000001" required min="-90" max="90"
                   value="<?= e($pick('lat')) ?>"
                   class="<?= isset($errors['lat']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['lat'])): ?>
              <span class="hint error"><?= e($errors['lat']) ?></span>
            <?php endif; ?>
          </div>
          <div class="field">
            <label for="lng">Longitude <span class="req">*</span></label>
            <input type="number" id="lng" name="lng" step="0.0000001" required min="-180" max="180"
                   value="<?= e($pick('lng')) ?>"
                   class="<?= isset($errors['lng']) ? 'invalid' : '' ?>">
            <?php if (isset($errors['lng'])): ?>
              <span class="hint error"><?= e($errors['lng']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <span class="hint">The position the map plots. Kampala lies between roughly 0.20 and 0.42 north, 32.50 and 32.65 east.</span>
      </div>
    </div>

    <!-- ============ FACILITIES ============ -->
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Facilities</h2></div></div>
      <div class="card-body">
        <div class="grid g2">
          <?php foreach ($facilities as $col => $label): ?>
            <label class="row-tight" style="gap:6px">
              <input type="checkbox" name="<?= e($col) ?>" value="1" style="width:auto"
                     <?= !empty($pick($col, 0)) ? 'checked' : '' ?>>
              <span class="small"><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-foot">
        The reach model reads these: a cook with no water is not sent to a market with no water.
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div class="grow"><h2>Operations and contact</h2></div></div>
    <div class="card-body">
      <div class="grid g3">
        <div class="field">
          <label for="transport_stand">Nearest transport stand</label>
          <input type="text" id="transport_stand" name="transport_stand" maxlength="120"
                 value="<?= e($pick('transport_stand')) ?>">
        </div>
        <div class="field">
          <label for="admin_name">Market administrator</label>
          <input type="text" id="admin_name" name="admin_name" maxlength="120"
                 value="<?= e($pick('admin_name')) ?>">
        </div>
        <div class="field">
          <label for="admin_phone">Administrator telephone</label>
          <input type="text" id="admin_phone" name="admin_phone"
                 value="<?= e($pick('admin_phone')) ?>"
                 class="<?= isset($errors['admin_phone']) ? 'invalid' : '' ?>">
          <?php if (isset($errors['admin_phone'])): ?>
            <span class="hint error"><?= e($errors['admin_phone']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label for="category_mix">Trades present</label>
          <input type="text" id="category_mix" name="category_mix" maxlength="255"
                 value="<?= e($pick('category_mix')) ?>"
                 placeholder="e.g. Food, produce, meat, beverages, hardware">
          <span class="hint">Used by the trade-mix chart on the location record.</span>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label for="notes">Notes</label>
          <textarea id="notes" name="notes" rows="4" maxlength="2000"><?= e($pick('notes')) ?></textarea>
        </div>
      </div>
    </div>
    <div class="card-foot">
      <div class="row">
        <button type="submit" class="btn btn-primary">
          <?= $isNew ? 'Add location' : 'Save changes' ?>
        </button>
        <a class="btn btn-ghost" href="locations.php">Cancel</a>
        <span class="right small muted">Every change is recorded in the audit trail.</span>
      </div>
    </div>
  </div>
</form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>