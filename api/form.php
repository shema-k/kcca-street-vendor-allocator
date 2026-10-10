<?php
/**
 * GET /api/form.php?form=footfall|space|restriction|vendor
 *
 * Modal forms. Returned as a fragment so the page's own styles and
 * scripts stay in charge, and so a validation error can be re-rendered
 * beside the field that caused it.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requirePerm('view');

/* The form posts back to this same endpoint, so the identifier arrives in
   the body on submit and in the query string when the modal is first
   opened. Accept either. */
$form   = (string) ($_GET['form'] ?? $_POST['form'] ?? '');
$action = (string) ($_POST['action'] ?? '');

switch ($form) {

    /* ---------------- Record a footfall count ---------------- */
    case 'footfall': {
        $locationId = (int) ($_GET['location'] ?? $_POST['market_id'] ?? 0);
        $values     = $_POST;
        $errors     = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'record') {
            $v = new Validator($_POST, [
                'market_id'       => ['type' => 'int', 'label' => 'Market', 'required' => true, 'min' => 1],
                'observed_on'     => ['type' => 'date', 'label' => 'Date', 'required' => true, 'not_future' => true],
                'hour_band'       => ['type' => 'int', 'label' => 'Time band', 'required' => true, 'min' => 0, 'max' => 5],
                'day_type'        => ['type' => 'enum', 'label' => 'Day type', 'required' => true,
                                       'options' => ['weekday', 'saturday', 'sunday', 'holiday']],
                'people_in'       => ['type' => 'int', 'label' => 'People entering', 'min' => 0, 'max' => 200000],
                'people_out'      => ['type' => 'int', 'label' => 'People leaving', 'min' => 0, 'max' => 200000],
                'traders_present' => ['type' => 'int', 'label' => 'Traders present', 'min' => 0, 'max' => 65535],
                'source'          => ['type' => 'enum', 'label' => 'Counted by', 'required' => true,
                                       'options' => ['manual-count', 'sensor', 'estimate', 'trader-log']],
                'observer'        => ['type' => 'text', 'label' => 'Observer', 'max' => 140],
                'weather'         => ['type' => 'enum', 'label' => 'Weather', 'options' => ['clear', 'rain', 'overcast']],
                'event_note'      => ['type' => 'text', 'label' => 'Event note', 'max' => 160],
            ]);
            $v->checkExists('market_id', 'markets', $_POST['market_id'] ?? null);

            if (($v->value('people_in') ?? 0) === 0 && ($v->value('people_out') ?? 0) === 0) {
                $errors['people_in'] = 'A band with nobody counted is a gap, not an observation.';
            }

            if ($v->fails() || $errors) {
                $errors += $v->errors();
                $modalErrors = $errors;
                $modalValues = $_POST;
            } else {
                $data = $v->validated();
                $data['created_by'] = Auth::id() ?: null;
                $id = Database::insert('footfall_observations', $data);
                Auth::log('record-footfall', 'footfall_observations', $id,
                    $data['observed_on'] . ' — ' . (int) $data['people_in'] . ' in, ' . (int) $data['people_out'] . ' out');
                jsonOut(['ok' => true, 'message' => 'Count recorded.', 'id' => $id], 200);
            }
        }

        $markets = Database::all(
            "SELECT m.id, m.name, d.name AS division_name FROM markets m
               JOIN divisions d ON d.id = m.division_id
              WHERE m.category = 'public' ORDER BY m.name"
        );
        $errors  = $errors  ?? [];
        $values  = $values  ?? [];
        ?>
        <div class="modal-back" data-modal-host>
        <div class="modal">
          <form method="post" action="<?= APP_REL ?>api/form.php" id="footfall-form" class="modal-form">
            <input type="hidden" name="form" value="footfall">
            <input type="hidden" name="action" value="record">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

            <div class="modal-head">
              <h3>Record a footfall count</h3>
              <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
              <?php if ($errors): ?>
                <div class="alert alert-bad">
                  <strong>The count was not saved.</strong> Correct the highlighted fields.
                </div>
              <?php endif; ?>

              <div class="grid g2">
                <div class="field">
                  <label for="market_id">Location</label>
                  <select name="market_id" id="market_id" required>
                    <option value="">Choose a market</option>
                    <?php foreach ($markets as $m): ?>
                      <option value="<?= (int) $m['id'] ?>"
                        <?= (string) ($values['market_id'] ?? $locationId) === (string) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['name']) ?> — <?= e($m['division_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="field">
                  <label for="observed_on">Date observed</label>
                  <input type="date" name="observed_on" id="observed_on" max="<?= e(date('Y-m-d')) ?>"
                         value="<?= e($values['observed_on'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="field">
                  <label for="day_type">Day type</label>
                  <select name="day_type" id="day_type">
                    <?php foreach (['weekday','saturday','sunday','holiday'] as $d): ?>
                      <option value="<?= e($d) ?>"
                        <?= ($values['day_type'] ?? 'weekday') === $d ? 'selected' : '' ?>><?= e(ucfirst($d)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="field">
                  <label for="hour_band">Time band</label>
                  <select name="hour_band" id="hour_band">
                    <?php for ($b = 0; $b < 6; $b++): ?>
                      <option value="<?= $b ?>"
                        <?= (string) ($values['hour_band'] ?? 2) === (string) $b ? 'selected' : '' ?>>
                        <?= e(ReachModel::hourBandLabel($b)) ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                  <span class="hint">Count movements in one four-hour band.</span>
                </div>

                <div class="field">
                  <label for="people_in">People entering</label>
                  <input type="number" name="people_in" id="people_in" min="0" max="200000"
                         value="<?= e($values['people_in'] ?? '') ?>" class="<?= isset($errors['people_in']) ? 'invalid' : '' ?>">
                  <?php if (isset($errors['people_in'])): ?>
                    <span class="hint error"><?= e($errors['people_in']) ?></span>
                  <?php endif; ?>
                </div>

                <div class="field">
                  <label for="people_out">People leaving</label>
                  <input type="number" name="people_out" id="people_out" min="0" max="200000"
                         value="<?= e($values['people_out'] ?? '') ?>">
                </div>

                <div class="field">
                  <label for="traders_present">Traders present</label>
                  <input type="number" name="traders_present" id="traders_present" min="0" max="65535"
                         value="<?= e($values['traders_present'] ?? '') ?>">
                </div>

                <div class="field">
                  <label for="source">How counted</label>
                  <select name="source" id="source">
                    <?php foreach (['manual-count','sensor','trader-log','estimate'] as $s): ?>
                      <option value="<?= e($s) ?>" <?= ($values['source'] ?? 'manual-count') === $s ? 'selected' : '' ?>>
                        <?= e(humanise($s)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="hint">A sensor count carries more weight than an estimate.</span>
                </div>

                <div class="field">
                  <label for="observer">Observer</label>
                  <input type="text" name="observer" id="observer" maxlength="140"
                         value="<?= e($values['observer'] ?? '') ?>">
                </div>

                <div class="field">
                  <label for="weather">Weather</label>
                  <select name="weather" id="weather">
                    <option value="">Not recorded</option>
                    <?php foreach (['clear','overcast','rain'] as $w): ?>
                      <option value="<?= e($w) ?>" <?= ($values['weather'] ?? '') === $w ? 'selected' : '' ?>><?= e(ucfirst($w)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="field">
                  <label for="event_note">Event note</label>
                  <input type="text" name="event_note" id="event_note" maxlength="160"
                         value="<?= e($values['event_note'] ?? '') ?>"
                         placeholder="e.g. Market day, City Festival">
                </div>
              </div>
            </div>

            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn-primary">Save count</button>
            </div>
          </form>
        </div>
        </div>
        <script>
        document.querySelector('[data-modal-form="footfall"]') ||
          document.addEventListener('submit', function (e) {
            var f = e.target;
            if (f.id !== 'footfall-form') return;
            e.preventDefault();
            var fd = new FormData(f);
            KCCA.post(KCCA.api('api/form.php'), Object.fromEntries(fd.entries()))
              .then(function (res) {
                if (res && res.ok) { KCCA.closeModal(); KCCA.flash(res.message, 'ok');
                                     setTimeout(function(){ location.reload(); }, 800); }
                else { KCCA.flash(res && res.error, 'bad'); }
              });
          });
        </script>
        <?php
        break;
    }

    /* ---------------- Edit a pitch ---------------- */
    case 'space': {
        $spaceId = (int) ($_GET['space_id'] ?? $_POST['space_id'] ?? 0);
        $space   = Database::one(
            'SELECT s.*, m.name AS market_name FROM market_spaces s
               JOIN markets m ON m.id = s.market_id WHERE s.id = ?',
            [$spaceId]
        );
        if (!$space) {
            jsonOut(['error' => 'That pitch does not exist'], 404);
        }

        $errors = [];
        $values = $_POST ?: $space;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
            $v = new Validator($_POST, [
                'section'          => ['type' => 'text', 'label' => 'Section', 'max' => 80],
                'size_sqm'         => ['type' => 'decimal', 'label' => 'Size', 'required' => true, 'min' => 0.5, 'max' => 500],
                'status'           => ['type' => 'enum', 'label' => 'Status', 'required' => true,
                                        'options' => ['vacant','occupied','reserved','under-maintenance']],
                'monthly_rent_ugx' => ['type' => 'money', 'label' => 'Monthly rent', 'min' => 0],
                'has_water'        => ['type' => 'checkbox', 'label' => 'Water'],
                'has_power'        => ['type' => 'checkbox', 'label' => 'Power'],
                'near_toilet'      => ['type' => 'checkbox', 'label' => 'Near toilet'],
                'step_free'        => ['type' => 'checkbox', 'label' => 'Step free'],
            ]);

            if ($v->fails()) {
                $errors = $v->errors();
                $values = $_POST;
            } else {
                $data = $v->validated();
                Database::update('market_spaces', $data, 'id = :id', ['id' => $spaceId]);
                Database::run(
                    "UPDATE markets m SET vacant_spaces =
                        (SELECT COUNT(*) FROM market_spaces s
                          WHERE s.market_id = m.id AND s.status = 'vacant')
                      WHERE m.id = ?",
                    [(int) $space['market_id']]
                );
                Auth::log('update-space', 'market_spaces', $spaceId, $space['space_code'] . ' updated');
                jsonOut(['ok' => true, 'message' => 'Pitch ' . $space['space_code'] . ' updated.'], 200);
            }
        }
        ?>
        <div class="modal-back" data-modal-host>
        <div class="modal">
          <form method="post" action="<?= APP_REL ?>api/form.php" class="modal-form">
            <input type="hidden" name="form" value="space">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="space_id" value="<?= (int) $spaceId ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

            <div class="modal-head">
              <h3><?= e($space['space_code']) ?> — <?= e($space['market_name']) ?></h3>
              <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
              <?php if ($errors): ?>
                <div class="alert alert-bad">Correct the highlighted fields.</div>
              <?php endif; ?>

              <div class="grid g2">
                <div class="field">
                  <label for="section">Section</label>
                  <input type="text" name="section" id="section" maxlength="80" value="<?= e($values['section'] ?? '') ?>">
                </div>
                <div class="field">
                  <label for="size_sqm">Size (m²)</label>
                  <input type="number" name="size_sqm" id="size_sqm" step="0.5" min="0.5" max="500" required
                         value="<?= e($values['size_sqm'] ?? '9') ?>">
                  <?php if (isset($errors['size_sqm'])): ?>
                    <span class="hint error"><?= e($errors['size_sqm']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="field">
                  <label for="status">Status</label>
                  <select name="status" id="status">
                    <?php foreach (['vacant','reserved','occupied','under-maintenance'] as $s): ?>
                      <option value="<?= e($s) ?>" <?= ($values['status'] ?? '') === $s ? 'selected' : '' ?>>
                        <?= e(humanise($s)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="monthly_rent_ugx">Monthly rent (UGX)</label>
                  <input type="number" name="monthly_rent_ugx" id="monthly_rent_ugx" min="0"
                         value="<?= e($values['monthly_rent_ugx'] ?? 0) ?>">
                  <span class="hint">KCCA does not charge for the identified vacant spaces.</span>
                </div>
              </div>

              <div class="field">
                <label>Facilities at this pitch</label>
                <div class="row-tight">
                  <?php foreach (['has_water'=>'Water','has_power'=>'Power',
                                  'near_toilet'=>'Near toilet','step_free'=>'Step free'] as $k => $l): ?>
                    <label class="row-tight" style="gap:6px">
                      <input type="checkbox" name="<?= e($k) ?>" value="1"
                             <?= !empty($values[$k]) ? 'checked' : '' ?> style="width:auto">
                      <span class="small"><?= e($l) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn-primary">Save pitch</button>
            </div>
          </form>
        </div>
        </div>
        <script>
        document.addEventListener('submit', function (e) {
          if (!e.target.matches('.modal-form') || e.target.querySelector('[name=form]').value !== 'space') return;
          e.preventDefault();
          KCCA.post(KCCA.api('api/form.php'), Object.fromEntries(new FormData(e.target).entries()))
            .then(function (res) {
              if (res && res.ok) { KCCA.closeModal(); KCCA.flash(res.message, 'ok');
                                   setTimeout(function(){ location.reload(); }, 800); }
              else { KCCA.flash(res && res.error, 'bad'); }
            });
        });
        </script>
        <?php
        break;
    }

    /* ---------------- Land-use restriction ---------------- */
    case 'restriction': {
        $marketId = (int) ($_GET['market_id'] ?? $_POST['market_id'] ?? 0);
        $errors   = [];
        $values   = $_POST;
        $excluded = isset($_POST['excluded_categories'])
            ? (array) $_POST['excluded_categories'] : [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
            $v = new Validator($_POST, [
                'market_id'   => ['type' => 'int', 'label' => 'Location', 'required' => true, 'min' => 1],
                'code'        => ['type' => 'text', 'label' => 'Code', 'required' => true, 'max' => 40,
                                   'pattern' => '/^[A-Za-z0-9\-]+$/',
                                   'pattern_message' => 'Codes may contain letters, numbers and hyphens only.'],
                'title'       => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 160],
                'description' => ['type' => 'textarea', 'label' => 'Description', 'required' => true, 'max' => 2000],
                'applies_to_section' => ['type' => 'text', 'label' => 'Section', 'max' => 80],
                'authority'   => ['type' => 'text', 'label' => 'Recorded by', 'max' => 160],
                'legal_basis' => ['type' => 'text', 'label' => 'Legal basis', 'max' => 200],
                'effective_from' => ['type' => 'date', 'label' => 'Effective from', 'required' => true],
                'effective_to'   => ['type' => 'date', 'label' => 'Effective to'],
            ]);
            $v->checkExists('market_id', 'markets', $_POST['market_id'] ?? null);

            $from = $v->value('effective_from');
            $to   = $v->value('effective_to');
            if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
                $v->errors();
            }

            if ($v->fails()) {
                $errors = $v->errors();
                $values = $_POST;
            } else {
                $data = $v->validated();
                $data['excluded_categories'] = implode(',', $excluded);
                $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
                $id = Database::insert('land_use_restrictions', $data);
                Auth::log('save-restriction', 'land_use_restrictions', $id, $data['title']);
                jsonOut(['ok' => true, 'message' => 'Restriction recorded.'], 200);
            }
        }

        $markets    = Database::all("SELECT id, name FROM markets WHERE category = 'public' ORDER BY name");
        $categories = Database::all('SELECT * FROM trade_categories ORDER BY name');
        $errors     = $errors ?? [];
        $values     = $values ?? [];
        $excluded   = $excluded ?? [];
        ?>
        <div class="modal-back" data-modal-host>
        <div class="modal wide">
          <form method="post" action="<?= APP_REL ?>api/form.php" class="modal-form">
            <input type="hidden" name="form" value="restriction">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

            <div class="modal-head">
              <h3>Record a land-use restriction</h3>
              <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
              <?php if ($errors): ?>
                <div class="alert alert-bad">
                  <strong>Not saved.</strong> Correct the highlighted fields.
                </div>
              <?php endif; ?>

              <div class="grid g2">
                <div class="field">
                  <label for="market_id">Location</label>
                  <select name="market_id" id="market_id" required>
                    <option value="">Choose a location</option>
                    <?php foreach ($markets as $m): ?>
                      <option value="<?= (int) $m['id'] ?>"
                        <?= (string) ($values['market_id'] ?? $marketId) === (string) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="code">Restriction code</label>
                  <input type="text" name="code" id="code" maxlength="40" required
                         value="<?= e($values['code'] ?? '') ?>" placeholder="e.g. LUR-SCHOOL-01"
                         class="<?= isset($errors['code']) ? 'invalid' : '' ?>">
                  <?php if (isset($errors['code'])): ?>
                    <span class="hint error"><?= e($errors['code']) ?></span>
                  <?php else: ?>
                    <span class="hint">Letters, numbers and hyphens. Unique within the location.</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="field">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" maxlength="160" required
                       value="<?= e($values['title'] ?? '') ?>"
                       placeholder="e.g. No cooking adjacent to a school">
                <?php if (isset($errors['title'])): ?>
                  <span class="hint error"><?= e($errors['title']) ?></span>
                <?php endif; ?>
              </div>

              <div class="field">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3" required maxlength="2000"><?= e($values['description'] ?? '') ?></textarea>
                <?php if (isset($errors['description'])): ?>
                  <span class="hint error"><?= e($errors['description']) ?></span>
                <?php endif; ?>
              </div>

              <div class="field">
                <label>Trades this blocks</label>
                <span class="hint">
                  Leave all unticked to restrict every trade on the site. Ticked trades will be excluded
                  from recommendations entirely, not merely scored down.
                </span>
                <div class="row-tight mt-1">
                  <?php foreach ($categories as $c): ?>
                    <label class="row-tight" style="gap:5px">
                      <input type="checkbox" name="excluded_categories[]" value="<?= e($c['code']) ?>"
                             <?= in_array($c['code'], $excluded, true) ? 'checked' : '' ?> style="width:auto">
                      <span class="small"><?= e($c['name']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="grid g3">
                <div class="field">
                  <label for="authority">Recorded by</label>
                  <input type="text" name="authority" id="authority" maxlength="160"
                         value="<?= e($values['authority'] ?? 'KCCA Physical Planning') ?>">
                </div>
                <div class="field">
                  <label for="legal_basis">Legal basis</label>
                  <input type="text" name="legal_basis" id="legal_basis" maxlength="200"
                         value="<?= e($values['legal_basis'] ?? '') ?>">
                </div>
                <div class="field">
                  <label for="applies_to_section">Section</label>
                  <input type="text" name="applies_to_section" id="applies_to_section" maxlength="80"
                         value="<?= e($values['applies_to_section'] ?? '') ?>">
                </div>
                <div class="field">
                  <label for="effective_from">Effective from</label>
                  <input type="date" name="effective_from" id="effective_from" required
                         value="<?= e($values['effective_from'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="field">
                  <label for="effective_to">Effective to</label>
                  <input type="date" name="effective_to" id="effective_to"
                         value="<?= e($values['effective_to'] ?? '') ?>">
                  <?php if (isset($errors['effective_to'])): ?>
                    <span class="hint error"><?= e($errors['effective_to']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="field">
                  <label>In force</label>
                  <label class="row-tight" style="margin-top:8px">
                    <input type="checkbox" name="is_active" value="1" style="width:auto"
                           <?= ($values['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <span class="small">Currently binding</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn-primary">Record restriction</button>
            </div>
          </form>
        </div>
        </div>
        <script>
        document.addEventListener('submit', function (e) {
          if (!e.target.matches('.modal-form')) return;
          if (e.target.querySelector('[name=form]').value !== 'restriction') return;
          e.preventDefault();
          KCCA.post(KCCA.api('api/form.php'), Object.fromEntries(new FormData(e.target).entries()))
            .then(function (res) {
              if (res && res.ok) { KCCA.closeModal(); KCCA.flash(res.message, 'ok');
                                   setTimeout(function(){ location.reload(); }, 800); }
              else { KCCA.flash(res && res.error, 'bad'); }
            });
        });
        </script>
        <?php
        break;
    }

    /* ---------------- Register / edit a vendor ---------------- */
    case 'vendor': {
        $vendorId = (int) ($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0);
        $editing  = $vendorId > 0;
        $existing = $editing
            ? Database::one('SELECT * FROM vendors WHERE id = ?', [$vendorId])
            : null;

        if ($editing && !$existing) {
            jsonOut(['error' => 'That vendor is not in the register'], 404);
        }

        $errors = [];
        $values = $_POST ?: ($existing ?: []);
        $action = $action ?: ($editing ? 'update' : 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update'], true)) {
            require_once __DIR__ . '/../lib/VendorService.php';
            requirePerm('allocate');

            /* Called directly rather than over HTTP: the service is the
               single place the rules and the write live, and a loopback
               request would need a second session, a second CSRF check and
               a correct absolute URL. */
            $payload            = $_POST;
            $payload['action']   = $action;
            $payload['vendor_id'] = $vendorId;

            $result = VendorService::save($payload, $action);

            if ($result['ok']) {
                jsonOut($result + ['ok' => true], 200);
            }

            if ($result['status'] === 422) {
                $errors = $result['errors'];
                $values = $_POST;
            } else {
                $errors = ['_form' => $result['error']];
            }
        }

        $divisions = Database::all('SELECT id, code, name FROM divisions ORDER BY name');
        $zones     = Database::all(
            'SELECT id, code, name, division_id FROM trade_zones ORDER BY name'
        );
        $categories = Database::all('SELECT code, name FROM trade_categories ORDER BY demand_index DESC');
        $value = static function (string $k, $default = '') use ($values) {
            return $values[$k] ?? $default;
        };
        $on = static function (string $k) use ($values): bool {
            return !empty($values[$k]);
        };
        ?>
        <div class="modal-back" data-modal-host>
        <div class="modal wide">
          <form method="post" action="<?= APP_REL ?>api/form.php" class="modal-form">
            <input type="hidden" name="form" value="vendor">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="<?= e($action) ?>">
            <input type="hidden" name="vendor_id" value="<?= $vendorId ?>">

            <div class="modal-head">
              <h3><?= $editing ? 'Edit vendor — ' . e($existing['full_name']) : 'Register a vendor' ?></h3>
              <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
              <?php if (isset($errors['_form'])): ?>
                <div class="alert alert-bad"><?= e($errors['_form']) ?></div>
              <?php elseif ($errors): ?>
                <div class="alert alert-bad">
                  <strong><?= count($errors) ?> field<?= count($errors) === 1 ? '' : 's' ?> could not be saved.</strong>
                  Everything else was kept — correct only what is marked below.
                </div>
              <?php endif; ?>

              <?php
              /* One helper so each field shows its own message. */
              $fieldError = static function (string $key) use ($errors): string {
                  return isset($errors[$key])
                      ? '<span class="hint error">' . e($errors[$key]) . '</span>'
                      : '';
              };
              $cls = static fn(string $key) => isset($errors[$key]) ? ' class="invalid"' : '';
              ?>

              <h4 class="mb-1">The trader</h4>
              <div class="grid g3">
                <div class="field">
                  <label for="full_name">Full name *</label>
                  <input type="text" id="full_name" name="full_name" required maxlength="140"<?= $cls('full_name') ?>
                         value="<?= e($value('full_name')) ?>">
                  <?= $fieldError('full_name') ?>
                </div>
                <div class="field">
                  <label for="national_id">National ID</label>
                  <input type="text" id="national_id" name="national_id" maxlength="20"<?= $cls('national_id') ?>
                         value="<?= e($value('national_id')) ?>">
                  <?= $fieldError('national_id') ?: '<span class="hint">10 to 16 digits.</span>' ?>
                </div>
                <div class="field">
                  <label for="phone">Telephone *</label>
                  <input type="text" id="phone" name="phone" required<?= $cls('phone') ?>
                         value="<?= e($value('phone')) ?>">
                  <?= $fieldError('phone') ?>
                </div>
                <div class="field">
                  <label for="alternative_phone">Alternative telephone</label>
                  <input type="text" id="alternative_phone" name="alternative_phone"<?= $cls('alternative_phone') ?>
                         value="<?= e($value('alternative_phone')) ?>">
                  <?= $fieldError('alternative_phone') ?>
                </div>
                <div class="field">
                  <label for="email">Email</label>
                  <input type="email" id="email" name="email"<?= $cls('email') ?> value="<?= e($value('email')) ?>">
                  <?= $fieldError('email') ?>
                </div>
                <div class="field">
                  <label for="gender">Gender *</label>
                  <select name="gender" id="gender" required<?= $cls('gender') ?>>
                    <?php foreach (['male','female','other'] as $g): ?>
                      <option value="<?= e($g) ?>" <?= $value('gender', 'male') === $g ? 'selected' : '' ?>>
                        <?= e(ucfirst($g)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?= $fieldError('gender') ?>
                </div>
                <div class="field">
                  <label for="date_of_birth">Date of birth</label>
                  <input type="date" id="date_of_birth" name="date_of_birth"
                         max="<?= e(date('Y-m-d')) ?>"<?= $cls('date_of_birth') ?>
                         value="<?= e(substr((string) $value('date_of_birth'), 0, 10)) ?>">
                  <?= $fieldError('date_of_birth') ?>
                </div>
              </div>

              <div class="divider"></div>
              <h4 class="mb-1">Where they trade now</h4>
              <div class="grid g3">
                <div class="field">
                  <label for="division_id">Division *</label>
                  <select name="division_id" id="division_id" required<?= $cls('division_id') ?>>
                    <option value="">Choose a division</option>
                    <?php foreach ($divisions as $d): ?>
                      <option value="<?= (int) $d['id'] ?>"
                        <?= (string) $value('division_id') === (string) $d['id'] ? 'selected' : '' ?>>
                        <?= e($d['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?= $fieldError('division_id') ?>
                </div>
                <div class="field">
                  <label for="parish">Parish</label>
                  <input type="text" id="parish" name="parish" maxlength="120"<?= $cls('parish') ?>
                         value="<?= e($value('parish')) ?>">
                  <?= $fieldError('parish') ?>
                </div>
                <div class="field">
                  <label for="current_zone_id">Affected site</label>
                  <select name="current_zone_id" id="current_zone_id"<?= $cls('current_zone_id') ?>>
                    <option value="">Not surveyed yet</option>
                    <?php foreach ($zones as $z): ?>
                      <option value="<?= (int) $z['id'] ?>"
                        <?= (string) $value('current_zone_id') === (string) $z['id'] ? 'selected' : '' ?>>
                        <?= e($z['name']) ?> (<?= e($z['code']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?= $fieldError('current_zone_id') ?>
                </div>
              </div>

              <div class="divider"></div>
              <h4 class="mb-1">The business</h4>
              <div class="grid g3">
                <div class="field">
                  <label for="business_category">What they sell *</label>
                  <select name="business_category" id="business_category" required<?= $cls('business_category') ?>>
                    <option value="">Choose a trade</option>
                    <?php foreach ($categories as $c): ?>
                      <option value="<?= e($c['code']) ?>"
                        <?= $value('business_category') === $c['code'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?= $fieldError('business_category') ?>
                </div>
                <div class="field">
                  <label for="started_year">Year trading began *</label>
                  <input type="number" id="started_year" name="started_year" required min="1950"
                         max="<?= (int) date('Y') ?>"<?= $cls('started_year') ?>
                         value="<?= e($value('started_year', (int) date('Y') - 5)) ?>">
                  <?= $fieldError('started_year') ?>
                </div>
                <div class="field">
                  <label for="trading_days">Trading days</label>
                  <input type="text" id="trading_days" name="trading_days" maxlength="60"<?= $cls('trading_days') ?>
                         value="<?= e($value('trading_days', 'Monday–Saturday')) ?>">
                  <?= $fieldError('trading_days') ?>
                </div>
                <div class="field">
                  <label for="monthly_turnover_ugx">Monthly turnover (UGX)</label>
                  <input type="number" id="monthly_turnover_ugx" name="monthly_turnover_ugx" min="0" step="1000"<?= $cls('monthly_turnover_ugx') ?>
                         value="<?= e($value('monthly_turnover_ugx', 0)) ?>">
                  <?= $fieldError('monthly_turnover_ugx') ?>
                </div>
                <div class="field">
                  <label for="startup_capital_ugx">Starting capital (UGX)</label>
                  <input type="number" id="startup_capital_ugx" name="startup_capital_ugx" min="0" step="1000"<?= $cls('startup_capital_ugx') ?>
                         value="<?= e($value('startup_capital_ugx', 0)) ?>">
                  <?= $fieldError('startup_capital_ugx') ?>
                </div>
                <div class="field">
                  <label for="association">Traders association</label>
                  <input type="text" id="association" name="association" maxlength="160"<?= $cls('association') ?>
                         value="<?= e($value('association')) ?>">
                  <?= $fieldError('association') ?>
                </div>
              </div>

              <div class="divider"></div>
              <h4 class="mb-1">Household and licence</h4>
              <div class="grid g4">
                <div class="field">
                  <label for="household_size">Household size *</label>
                  <input type="number" id="household_size" name="household_size" min="1" max="30" required<?= $cls('household_size') ?>
                         value="<?= e($value('household_size', 1)) ?>">
                  <?= $fieldError('household_size') ?>
                </div>
                <div class="field">
                  <label for="dependants">Dependants</label>
                  <input type="number" id="dependants" name="dependants" min="0" max="40"<?= $cls('dependants') ?>
                         value="<?= e($value('dependants', 0)) ?>">
                  <?= $fieldError('dependants') ?>
                </div>
                <div class="field">
                  <label for="has_trading_licence">Trading licence</label>
                  <label class="row-tight" style="margin-top:8px">
                    <input type="checkbox" name="has_trading_licence" value="1" style="width:auto" <?= $on('has_trading_licence') ? 'checked' : '' ?>>
                    <span class="small">Currently holds one</span>
                  </label>
                </div>
                <div class="field">
                  <label for="licence_class">Licence class</label>
                  <select name="licence_class" id="licence_class">
                    <?php foreach (['unlicensed','street-grade-1','street-grade-2','market-standard'] as $lc): ?>
                      <option value="<?= e($lc) ?>" <?= $value('licence_class', 'unlicensed') === $lc ? 'selected' : '' ?>>
                        <?= e(humanise($lc)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?= $fieldError('licence_class') ?>
                </div>
              </div>

              <div class="field">
                <label>Equity factors</label>
                <span class="hint">
                  These raise the trader’s priority in the relocation engine. Record them accurately —
                  they decide who is offered space first when two traders fit the same market equally well.
                </span>
                <div class="grid g2 mt-1">
                  <?php foreach ([
                    'is_single_parent'           => 'Single parent',
                    'is_person_with_disability' => 'Person with disability',
                    'is_youth'                   => 'Under 30',
                    'is_veteran_ex_enforcee'     => 'Former enforcement agent',
                  ] as $key => $label): ?>
                    <label class="row-tight" style="gap:6px">
                      <input type="checkbox" name="<?= e($key) ?>" value="1" style="width:auto"
                             <?= $on($key) ? 'checked' : '' ?>>
                      <span class="small"><?= e($label) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <?php if ($editing): ?>
                <div class="field">
                  <label for="status">Stage</label>
                  <select name="status" id="status">
                    <?php foreach (['surveyed','notified','offered-space','relocated','declined','deferred','complaint'] as $s): ?>
                      <option value="<?= e($s) ?>" <?= $value('status', 'surveyed') === $s ? 'selected' : '' ?>>
                        <?= e(humanise($s)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
            </div>

            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
              <button type="submit" class="btn btn-primary">
                <?= $editing ? 'Save changes' : 'Register vendor' ?>
              </button>
            </div>
          </form>
        </div>
        </div>
        <script>
        /* The form posts to itself. On success the endpoint answers with
           JSON; on a validation failure it answers with the same form
           again, carrying the messages beside the fields that caused them.
           Swapping the markup in place keeps what the trader already
           typed, which is the whole point of validating field by field. */
        document.addEventListener('submit', function (e) {
          if (!e.target.matches('.modal-form')) return;
          var form = e.target;
          if (!form.querySelector('[name="action"][value="create"], [name="action"][value="update"]')) return;

          e.preventDefault();
          var btn = form.querySelector('[type="submit"]');
          var label = btn ? btn.textContent : '';
          if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

          fetch(KCCA.api('api/form.php'), {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'fetch' }
          })
            .then(function (r) {
              var type = r.headers.get('content-type') || '';
              if (type.indexOf('application/json') !== -1) return r.json();
              /* Re-rendered form: swap it in and scroll the summary into view. */
              var host = document.querySelector('.modal-back');
              return r.text().then(function (html) {
                if (host) {
                  host.innerHTML = html;
                  var alert = host.querySelector('.alert-bad');
                  if (alert) alert.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
                return { reRendered: true };
              });
            })
            .then(function (res) {
              if (res && res.ok) {
                KCCA.closeModal();
                KCCA.flash(res.message, 'ok');
                setTimeout(function () { location.reload(); }, 900);
              } else if (!res || !res.reRendered) {
                KCCA.flash((res && res.error) || 'The vendor could not be saved.', 'bad');
                if (btn) { btn.disabled = false; btn.textContent = label; }
              }
            })
            .catch(function () {
              KCCA.flash('Could not reach the server. Nothing was saved.', 'bad');
              if (btn) { btn.disabled = false; btn.textContent = label; }
            });
        });
        </script>
        <?php
        break;
    }

    default:
        jsonOut(['error' => 'Unknown form'], 400);
}