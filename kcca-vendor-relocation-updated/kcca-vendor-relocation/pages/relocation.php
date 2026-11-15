<?php
/**
 * Relocation Engine — the multi-reasoning matching dashboard.
 *
 * Pick a vendor; the engine ranks every market with space against six
 * weighted criteria and explains each ranking in plain language. An
 * officer can then reserve a specific pitch and issue the offer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('allocate');

$f   = facts();
$vac = $f['vacancy'];

$vendorId = (int) ($_GET['vendor'] ?? 0);
$division = (string) ($_GET['division'] ?? '');

// A Division Mayor is scoped to their own division.
$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}
$status   = (string) ($_GET['status'] ?? '');
$zoneId   = (int) ($_GET['zone'] ?? 0);
$q        = (string) ($_GET['q'] ?? '');

/* ---------------- Candidate vendor list ---------------- */
$where  = ['1 = 1'];
$params = [];
if ($division !== '') {
    $where[] = 'd.code = :div';
    $params['div'] = $division;
}
if ($status !== '') {
    $where[] = 'v.status = :st';
    $params['st'] = $status;
}
if ($zoneId > 0) {
    $where[] = 'v.current_zone_id = :zid';
    $params['zid'] = $zoneId;
}
if ($q !== '') {
    [$likeSql, $likeParams] = orLike('q',
        ['v.full_name', 'v.vendor_code', 'v.phone', 'v.national_id'], $q);
    $where[] = $likeSql;
    $params += $likeParams;
}

$vendorList = Database::all(
    "SELECT v.id, v.vendor_code, v.full_name, v.business_category, v.status, v.gender,
            v.dependants, v.is_single_parent, v.is_person_with_disability, v.has_trading_licence,
            v.monthly_turnover_ugx, d.name AS division_name, d.code AS div_code,
            z.name AS zone_name
       FROM vendors v
       JOIN divisions d ON d.id = v.division_id
       LEFT JOIN trade_zones z ON z.id = v.current_zone_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY v.full_name LIMIT 300",
    $params
);

$statusCounts = vendorStatusCounts();

/* ---------------- Engine output for the selected vendor ---------------- */
$options  = null;
$topSpaces = [];
$existing = [];
$vendor   = null;

if ($vendorId > 0) {
    $vendor = vendorForMatching($vendorId);

    if ($vendor) {
        $markets = candidateMarkets(400);
        if ($division !== '') {
            $markets = array_values(array_filter($markets, static fn($m) => $m['div_code'] === $division));
        }

        $ranked = RelocationEngine::rankForVendor($vendor, $markets, 6);
        $options = array_map(static fn(array $o): array => [
            'rank'     => $o['rank'],
            'score'    => $o['score'],
            'distance' => round($o['distance'], 2),
            'market'   => $o['market'],
            'criteria' => $o['criteria'],
            'reasons'  => $o['reasons'],
        ], $ranked);

        if ($options !== []) {
            $topSpaces = Database::all(
                "SELECT id, space_code, section, size_sqm, monthly_rent_ugx,
                        has_water, has_power, near_toilet, step_free
                   FROM market_spaces
                  WHERE market_id = ? AND status = 'vacant'
                  ORDER BY step_free DESC, has_water DESC, near_toilet DESC, space_code
                  LIMIT 10",
                [(int) $options[0]['market']['id']]
            );
        }

        $existing = Database::all(
            "SELECT a.*, m.name AS market_name, s.space_code
               FROM allocations a
               JOIN markets m ON m.id = a.market_id
               LEFT JOIN market_spaces s ON s.id = a.market_space_id
              WHERE a.vendor_id = ? ORDER BY a.created_at DESC LIMIT 6",
            [$vendorId]
        );
    }
}

$pageTitle = 'Relocation Engine';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Relocation Engine
    </div>
    <div class="eyebrow">Physical Planning · Multi-criteria matching</div>
    <h1>Relocation Engine</h1>
    <p>
      Vendor matched to gazetted space on six weighted criteria, with the reasoning behind every score.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="allocations.php"><?= KCCA_icon('list', 15) ?> Allocation register</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ===================== THE SIX CRITERIA ======================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>How a vendor is matched to a market</h2>
      <p class="desc">Six criteria, each scored 0–100, combined as a weighted average. Weights are policy settings in <code>config/config.php</code>.</p>
    </div>
  </div>
  <div class="card-body">
    <div class="grid g3">
      <?php
      $explain = [
        'proximity' => [
          'Proximity to the current pitch',
          'A trader who loses their customers overnight does not survive a relocation. Full marks at 400m or less; the score collapses past 10km, because past that it is a new business, not a relocation.',
        ],
        'trade_fit' => [
          'Market suits the trade',
          'Food cannot be sold from a hardware pitch. Scored on whether the market’s sections advertise that trade and whether the required facilities exist.',
        ],
        'equity' => [
          'Vulnerability and equity',
          'Low capital, dependants, single parenthood, disability and years in trade all raise the priority. This is the criterion that decides who is offered space first when two vendors fit the same market equally well.',
        ],
        'viability' => [
          'Headroom left in the destination',
          'Moving vendors into a market that is 98% full simply moves the congestion indoors and leaves no room for the next enforcement wave.',
        ],
        'affordability' => [
          'Affordability against turnover',
          'Rent as a share of monthly turnover. The Authority does not charge for the identified vacant spaces, so this normally scores full marks — but the test remains for private markets that do charge.',
        ],
        'facilities' => [
          'Facilities the trade requires',
          'A cook with no water at the pitch is not allocated a pitch in a market with no water. Each trade has a stated facility requirement.',
        ],
      ];
      foreach (RELOCATION_WEIGHTS as $key => $weight):
          [$title, $note] = $explain[$key];
      ?>
        <div class="criterion">
          <span class="cl"><?= e($title) ?></span>
          <span class="cv"><span class="badge brand"><?= pct($weight * 100, 0) ?></span></span>
          <span class="cn"><?= e($note) ?></span>
          <span class="track"><span class="fill" style="width:<?= (float) round($weight * 100 * 2.5, 0) ?>%"></span></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="source mt-2">
      <span class="lbl">note</span>
      <span>Scores are evidence for a human decision, not a substitute for one. An officer can always
      offer different space and record why.</span>
    </div>
  </div>
</div>

<!-- ========================= VENDOR PICKER ======================= -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Select a vendor</h2>
      <p class="desc"><?= num(count($vendorList)) ?> vendors match the current filter.</p>
    </div>
    <?php if ($vendorId > 0): ?>
      <a class="btn btn-ghost btn-sm" href="relocation.php">Choose a different vendor</a>
    <?php endif; ?>
  </div>

  <form class="filters" method="get">
    <div class="field grow">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>"
             placeholder="Name, vendor code, phone or national ID…" data-search-submit>
    </div>
    <?php renderDivisionFilter('division', $division); ?>
    <div class="field">
      <label for="status">Stage</label>
      <select name="status" id="status" data-autosubmit>
        <option value="">All stages</option>
        <?php foreach ($statusCounts as $st => $n): ?>
          <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(humanise($st)) ?> (<?= num($n) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <a class="btn btn-ghost" href="relocation.php">Reset</a>
  </form>

  <div class="card-body tight">
    <div class="table-wrap" style="max-height:420px;overflow-y:auto">
      <table class="data compact">
        <thead>
          <tr>
            <th>Vendor</th><th>Trade</th><th>Division</th><th>Current site</th>
            <th class="right">Turnover</th><th class="center">Factors</th><th class="center">Stage</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vendorList as $v): ?>
            <tr<?= (int) $v['id'] === $vendorId ? ' style="background:var(--kcca-red-soft)"' : '' ?>>
              <td>
                <strong><?= e($v['full_name']) ?></strong>
                <div class="xsmall muted">
                  <?= e($v['vendor_code']) ?> · <?= e($v['division_name']) ?>
                  <?php if ($v['is_single_parent']): ?> · single parent<?php endif; ?>
                  <?php if ($v['is_person_with_disability']): ?> · PWD<?php endif; ?>
                </div>
              </td>
              <td class="xsmall"><?= e(humanise($v['business_category'])) ?></td>
              <td class="xsmall"><?= e($v['division_name']) ?></td>
              <td class="xsmall"><?= e($v['zone_name'] ?: '—') ?></td>
              <td class="right num xsmall"><?= ugxShort((int) $v['monthly_turnover_ugx']) ?></td>
              <td class="center">
                <?php
                $flags = [];
                if ($v['is_single_parent']) $flags[] = 'SP';
                if ($v['is_person_with_disability']) $flags[] = 'PWD';
                if ($v['dependants'] > 0) $flags[] = $v['dependants'] . ' dep';
                if (!$v['has_trading_licence']) $flags[] = 'unlic';
                ?>
                <?php foreach ($flags as $fl): ?><span class="tag" style="margin-right:3px"><?= e($fl) ?></span><?php endforeach; ?>
                <?php if (!$flags): ?><span class="muted xsmall">—</span><?php endif; ?>
              </td>
              <td class="center"><span class="badge <?= e(statusTone($v['status'])) ?>"><?= e(humanise($v['status'])) ?></span></td>
              <td class="right">
                <a class="btn btn-sm <?= (int) $v['id'] === $vendorId ? 'btn-primary' : 'btn-ghost' ?>"
                   href="relocation.php?vendor=<?= (int) $v['id'] ?><?= $division !== '' ? '&division=' . e($division) : '' ?>">
                  Match
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$vendorList): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('people', 32) ?></div>
        <h3>No vendor matches this filter</h3>
        <p>Clear the filters to search the whole register.</p>
        <a class="btn btn-ghost" href="relocation.php">Reset</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($vendor && $options !== null): ?>
<!-- ====================== ENGINE RESULT ========================== -->
<div class="card" id="engine-result">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="relocation.php">Relocation engine</a><span class="sep">/</span><?= e($vendor['vendor_code']) ?>
      </div>
      <h2><?= e($vendor['full_name']) ?></h2>
      <p class="desc">
        <?= e(tradeDescription($vendor['business_category'])) ?> ·
        <?= e($vendor['division_name']) ?> Division ·
        currently trading at <?= e($vendor['zone_name'] ?: 'no recorded site') ?> ·
        trading since <?= (int) $vendor['started_year'] ?>
      </p>
    </div>
    <div class="tools">
      <span class="badge <?= e(statusTone($vendor['status'])) ?>"><?= e(humanise($vendor['status'])) ?></span>
      <a class="btn btn-ghost btn-sm" href="vendors.php?search=<?= e($vendor['vendor_code']) ?>">Full record</a>
    </div>
  </div>

  <div class="card-body">
    <div class="grid g-3-2 mb-2">
      <!-- Vendor profile -->
      <div>
        <h3 class="mb-1">The trader</h3>
        <div class="grid g3 mb-2">
          <div class="stat info">
            <div class="label">Monthly turnover</div>
            <div class="value" style="font-size:19px"><?= ugxShort((int) $vendor['monthly_turnover_ugx']) ?></div>
            <div class="meta">startup capital <?= ugxShort((int) $vendor['startup_capital_ugx']) ?></div>
          </div>
          <div class="stat">
            <div class="label">Household</div>
            <div class="value"><?= num((int) $vendor['household_size']) ?></div>
            <div class="meta"><?= num((int) $vendor['dependants']) ?> dependants</div>
          </div>
          <div class="stat <?= $vendor['has_trading_licence'] ? 'ok' : 'warn' ?>">
            <div class="label">Licence</div>
            <div class="value" style="font-size:19px"><?= $vendor['has_trading_licence'] ? 'Held' : 'None' ?></div>
            <div class="meta"><?= e(humanise($vendor['licence_class'])) ?></div>
          </div>
        </div>

        <div class="row mb-2">
          <?php if ($vendor['is_single_parent']): ?><span class="badge brand">Single parent</span><?php endif; ?>
          <?php if ($vendor['is_person_with_disability']): ?><span class="badge brand">Person with disability</span><?php endif; ?>
          <?php if ($vendor['is_youth']): ?><span class="badge info">Youth</span><?php endif; ?>
          <?php if ($vendor['is_veteran_ex_enforcee']): ?><span class="badge info">Former enforcement agent</span><?php endif; ?>
          <span class="badge <?= $vendor['gender'] === 'female' ? 'gold' : 'muted' ?>"><?= e(ucfirst($vendor['gender'])) ?></span>
          <?php if ($vendor['association']): ?><span class="badge muted"><?= e($vendor['association']) ?></span><?php endif; ?>
        </div>

        <?php if ($existing): ?>
          <h4 class="mb-1">Allocation history</h4>
          <div class="stack-s">
            <?php foreach ($existing as $a): ?>
              <div class="offer <?= e($a['status']) ?>">
                <div class="grow">
                  <h4><?= e($a['market_name']) ?><?= $a['space_code'] ? ' · ' . e($a['space_code']) : '' ?></h4>
                  <span class="m">
                    <?= e($a['reference']) ?> · score <?= num((float) $a['total_score'], 1) ?> ·
                    <?= num((float) $a['distance_km'], 1) ?> km ·
                    offered <?= e(fmtDate($a['created_at'], 'd M Y')) ?>
                  </span>
                </div>
                <span class="badge <?= e(statusTone($a['status'])) ?>"><?= e(humanise($a['status'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Headline recommendation -->
      <div>
        <?php $best = $options[0]; ?>
        <div class="card" style="margin:0;border-color:var(--kcca-red)">
          <div class="card-head">
            <div class="grow">
              <h3>Top recommendation</h3>
              <p class="desc"><?= e($best['market']['name']) ?></p>
            </div>
          </div>
          <div class="card-body">
            <div class="score-ring mb-2">
              <span class="ring-wrap">
                <svg width="78" height="78" viewBox="0 0 78 78">
                  <circle cx="39" cy="39" r="33" fill="none" stroke="#F1F3F6" stroke-width="8"/>
                  <circle cx="39" cy="39" r="33" fill="none"
                          stroke="<?= $best['score'] >= 70 ? '#1B7F4D' : ($best['score'] >= 50 ? '#EAA515' : '#E8730C') ?>"
                          stroke-width="8" stroke-linecap="round"
                          stroke-dasharray="<?= num(2 * M_PI * 33, 2) ?>"
                          stroke-dashoffset="<?= num(2 * M_PI * 33 * (1 - $best['score'] / 100), 2) ?>"/>
                </svg>
                <span class="num"><?= num($best['score'], 0) ?></span>
              </span>
              <span class="meta">
                <b><?= e($best['market']['name']) ?></b>
                <span><?= e($best['market']['division_name']) ?> Division ·
                  <?= num($best['distance'], 1) ?> km away</span>
              </span>
            </div>

            <ul class="reasons">
              <?php foreach (array_slice($best['reasons'], 0, 3) as $r): ?>
                <li><?= e($r) ?></li>
              <?php endforeach; ?>
            </ul>

            <?php if (Auth::can('allocate')): ?>
              <div class="divider"></div>
              <form method="post" action="../api/allocation_action.php" class="stack-s"
                    data-offer-form data-vendor="<?= (int) $vendor['id'] ?>"
                    data-market="<?= (int) $best['market']['id'] ?>">
                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="offer">
                <input type="hidden" name="vendor_id" value="<?= (int) $vendor['id'] ?>">
                <input type="hidden" name="market_id" value="<?= (int) $best['market']['id'] ?>">

                <div class="field mb-0">
                  <label for="space">Pitch to reserve</label>
                  <?php if ($topSpaces): ?>
                    <select name="market_space_id" id="space">
                      <option value="">Assign on arrival (no specific pitch)</option>
                      <?php foreach ($topSpaces as $s): ?>
                        <option value="<?= (int) $s['id'] ?>">
                          <?= e($s['space_code']) ?> · <?= e($s['section']) ?> ·
                          <?= num((float) $s['size_sqm'], 0) ?> m²
                          <?= $s['step_free'] ? '· step-free' : '' ?>
                          <?= $s['has_water'] ? '· water' : '' ?>
                          <?= $s['has_power'] ? '· power' : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <span class="hint">
                      <?= num(count($topSpaces)) ?> vacant pitches shown.
                      KCCA does not charge for the identified vacant spaces.
                    </span>
                  <?php else: ?>
                    <input type="hidden" name="market_space_id" value="">
                    <span class="hint">No individual pitches are registered for this market.</span>
                  <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                  Offer this space to <?= e(explode(' ', $vendor['full_name'])[0]) ?>
                </button>
              </form>
            <?php else: ?>
              <div class="alert alert-info" style="margin:12px 0 0">
                Your role can view this recommendation but cannot issue an offer.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Ranked alternatives -->
    <h3 class="mb-1">Ranked alternatives</h3>
    <div class="stack">
      <?php foreach ($options as $o):
          $m = $o['market'];
          $isOfficial = $m['data_basis'] === 'official';
      ?>
        <div class="card" style="margin:0">
          <div class="card-head">
            <div class="grow">
              <div class="row-tight">
                <span class="rank <?= $o['rank'] === 1 ? 'top' : '' ?>"><?= $o['rank'] ?></span>
                <strong><?= e($m['name']) ?></strong>
                <span class="badge <?= $m['category'] === 'public' ? 'green' : 'gold' ?>"><?= e($m['category']) ?></span>
                <?php if ($isOfficial): ?><span class="badge green">official figures</span><?php endif; ?>
              </div>
              <p class="desc">
                <?= e($m['division_name']) ?> Division ·
                <?= num($o['distance'], 1) ?> km away ·
                <b><?= num((int) $m['vacant_spaces']) ?></b> vacant spaces ·
                <?= pct($m['capacity'] > 0 ? (int) $m['registered_vendors'] / (int) $m['capacity'] * 100 : 0, 0) ?> occupied
              </p>
            </div>
            <div class="tools">
              <span class="badge <?= $o['score'] >= 70 ? 'ok' : ($o['score'] >= 50 ? 'warn' : 'bad') ?>">
                <?= num($o['score'], 1) ?> / 100
              </span>
            </div>
          </div>
          <div class="card-body">
            <div class="criteria">
              <?php foreach ($o['criteria'] as $c):
                  $tone = $c['raw'] >= 70 ? 'ok' : ($c['raw'] >= 45 ? 'warn' : ($c['raw'] >= 28 ? 'info' : 'bad'));
              ?>
                <div class="criterion">
                  <span class="cl"><?= e($c['label']) ?></span>
                  <span class="cv"><?= num($c['raw'], 0) ?><span class="muted small"> / 100 · <?= pct($c['weight'] * 100, 0) ?></span></span>
                  <span class="cn"><?= e($c['note']) ?></span>
                  <span class="track"><span class="fill <?= $tone === 'bad' ? '' : $tone ?>"
                        style="width:<?= (float) clamp($c['raw'], 1, 100) ?>%;
                               background:<?= ['ok'=>'#1B7F4D','warn'=>'#EAA515','info'=>'#1D6FA5','bad'=>'#C0261A'][$tone] ?>"></span></span>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if (Auth::can('allocate')): ?>
              <form method="post" action="../api/allocation_action.php" class="mt-2"
                    data-offer-form data-vendor="<?= (int) $vendor['id'] ?>" data-market="<?= (int) $m['id'] ?>">
                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="offer">
                <input type="hidden" name="vendor_id" value="<?= (int) $vendor['id'] ?>">
                <input type="hidden" name="market_id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="market_space_id" value="">
                <button type="submit" class="btn btn-ghost btn-sm">
                  Offer <?= e($m['name']) ?> instead
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$options): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('store', 32) ?></div>
        <h3>No market currently has space for this vendor</h3>
        <p>
          <?= num(count(candidateMarkets(400))) ?> markets were considered. All are full, or none
          satisfy the trade and facility requirements.
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($vendorId > 0): ?>
  <div class="card">
    <div class="empty">
      <div class="big"><?= KCCA_icon('alert', 32) ?></div>
      <h3>No vendor with that reference</h3>
      <p>The vendor is not in the register.</p>
      <a class="btn btn-ghost" href="relocation.php">Choose another vendor</a>
    </div>
  </div>
<?php endif; ?>

<script>
/* Offer forms post through the JSON endpoint and reload on success. */
document.querySelectorAll('[data-offer-form]').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = form.querySelector('button[type=submit]');
    var label = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Reserving…'; }

    KCCA.post('../api/allocation_action.php', {
      action: form.querySelector('[name=action]').value,
      vendor_id: form.querySelector('[name=vendor_id]').value,
      market_id: form.querySelector('[name=market_id]').value,
      market_space_id: form.querySelector('[name=market_space_id]').value
    })
      .then(function (res) {
        if (btn) { btn.textContent = label; }
        if (res && res.ok) {
          KCCA.flash(res.message + (res.reference ? ' (' + res.reference + ')' : ''), 'ok');
          setTimeout(function () { location.reload(); }, 900);
        } else {
          if (btn) btn.disabled = false;
          KCCA.flash((res && res.error) || 'The offer could not be issued.', 'bad');
        }
      })
      .catch(function () {
        if (btn) { btn.disabled = false; btn.textContent = label; }
        KCCA.flash('Could not reach the server.', 'bad');
      });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php';