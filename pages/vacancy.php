<?php
/**
 * Vacant Space Finder — the "most vacant" dashboard.
 *
 * Answers a trader and an officer the same question: where is gazetted
 * space, how big is it, and what does it cost? Official totals sit at the
 * top; the market register beneath reconciles to them exactly.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f   = facts();
$vac = $f['vacancy'];

$category = (string) ($_GET['category'] ?? '');
$division = (string) ($_GET['division'] ?? '');

// A Division Mayor is scoped to their own division.
$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}
$facility = (string) ($_GET['facility'] ?? '');
$q        = (string) ($_GET['q'] ?? '');

/* ---------------- Build the query ---------------- */
$where  = ['m.vacant_spaces > 0'];
$params = [];

if ($category !== '' && in_array($category, ['public', 'private'], true)) {
    $where[] = 'm.category = :cat';
    $params['cat'] = $category;
}
if ($division !== '') {
    $where[] = 'd.code = :div';
    $params['div'] = $division;
}
if ($facility !== '') {
    $where[] = "m.$facility = 1";
}
if ($q !== '') {
    [$likeSql, $likeParams] = orLike('q', ['m.name', 'm.parish', 'm.code'], $q);
    $where[] = $likeSql;
    $params += $likeParams;
}

$sql = "SELECT m.*, d.name AS division_name, d.code AS div_code,
               (SELECT COALESCE(AVG(monthly_rent_ugx), 0) FROM market_spaces s
                 WHERE s.market_id = m.id AND s.status = 'vacant') AS avg_rent
          FROM markets m JOIN divisions d ON d.id = m.division_id
         WHERE " . implode(' AND ', $where) .
        " ORDER BY m.vacant_spaces DESC, m.name";

$rows = Database::all($sql, $params);

foreach ($rows as &$r) {
    $cap     = max(1, (int) $r['capacity']);
    $r['util']      = round((int) $r['registered_vendors'] / $cap * 100, 1);
    $r['vacant_pct'] = round((int) $r['vacant_spaces'] / $cap * 100, 1);
}
unset($r);

/* ---------------- Totals for the filtered set ---------------- */
$filteredVacant = array_sum(array_column($rows, 'vacant_spaces'));
$filteredCap    = array_sum(array_column($rows, 'capacity'));
$publicVacant   = array_sum(array_map(
    static fn($r) => $r['category'] === 'public' ? (int) $r['vacant_spaces'] : 0,
    $rows
));
$privateVacant  = array_sum(array_map(
    static fn($r) => $r['category'] === 'private' ? (int) $r['vacant_spaces'] : 0,
    $rows
));

/* ---------------- Facilities a specific trade needs ---------------- */
$facilities = [
    'water_available'  => ['Water supply',  'Cooks, fresh produce and meat traders cannot work without it.'],
    'power_available'  => ['Electricity',   'Refrigeration, lighting, phone charging and salon equipment.'],
    'toilets'          => ['Toilets',       'A hygiene and public-health condition of trading.'],
    'waste_collection' => ['Waste collection', 'Required of every trader; the Authority collects from the market.'],
    'storage_rooms'    => ['Storage rooms', 'Protects stock from weather and theft.'],
    'cold_chain'       => ['Cold chain',    'Fish, meat and dairy traders lose stock without it.'],
    'accessible_ramps' => ['Accessible ramps', 'Step-free access for traders with a disability.'],
];

/* ---------------- Chart data: vacancy by division ---------------- */
$byDivision = [];
foreach (Database::all(
    "SELECT d.name,
            COALESCE(SUM(CASE WHEN m.category='public'  THEN m.vacant_spaces END),0) AS pub,
            COALESCE(SUM(CASE WHEN m.category='private' THEN m.vacant_spaces END),0) AS pri
       FROM divisions d
       LEFT JOIN markets m ON m.division_id = d.id
      GROUP BY d.id ORDER BY d.name"
) as $r) {
    $byDivision[] = [
        'label'   => $r['name'],
        'public'  => (int) $r['pub'],
        'private' => (int) $r['pri'],
    ];
}

/* The top eight markets by vacancy, for the ranked bar chart. */
$topByVacancy = array_slice($rows, 0, 10);
$topByHeadroom = $rows;
usort($topByHeadroom, static fn($a, $b) => $b['vacant_pct'] <=> $a['vacant_pct']);
$topByHeadroom = array_slice($topByHeadroom, 0, 10);

$pageTitle = 'Vacant Space Finder';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Vacant Space Finder
    </div>
    <div class="eyebrow">Market development · Gazetteed trading space</div>
    <h1>Vacant Space Finder</h1>
    <p>
      Every market in the register, ranked by the space it has available. This is where street vendors
      go — the Authority has already identified
      <?= num($vac['public_vacant_spaces']) ?> spaces in <?= num($vac['public_markets']) ?> public markets and at
      least <?= num($vac['private_vacant_spaces']) ?> in <?= num($vac['private_markets']) ?> private markets.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="relocation.php"><?= KCCA_icon('swap', 15) ?> Match vendors to space</a>
    <a class="btn btn-ghost" href="city-map.php"><?= KCCA_icon('map', 15) ?> Map</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ===================== OFFICIAL VACANCY ======================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Official vacancy position</h2>
      <p class="desc">Citywide totals as published by the Authority.</p>
    </div>
    <span class="badge green">Official · February 2026</span>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat official green">
        <div class="label">Public markets</div>
        <div class="value"><?= num($vac['public_markets']) ?></div>
        <div class="meta"><b><?= num($vac['public_vacant_spaces']) ?></b> vacant spaces</div>
      </div>
      <div class="stat official gold">
        <div class="label">Private markets</div>
        <div class="value"><?= num($vac['private_markets']) ?></div>
        <div class="meta"><b><?= num($vac['private_vacant_spaces']) ?></b> vacant spaces</div>
      </div>
      <div class="stat official bad">
        <div class="label">Total available</div>
        <div class="value"><?= num($vac['total_vacant']) ?></div>
        <div class="meta">across the whole city</div>
      </div>
      <div class="stat official ok">
        <div class="label">Cost to the trader</div>
        <div class="value" style="font-size:22px">Free</div>
        <div class="meta">on the identified spaces</div>
      </div>
    </div>

    <div class="quote">
      <p>“Usafi Market alone has capacity for 3,000 vendors but only 1,600 are currently registered…
          We can provide space for street vendors and these particular spaces are free.
          KCCA does not charge for them.”</p>
      <footer><b>Moses Musimire</b> — Supervisor, Market Development, KCCA · 17 February 2026</footer>
    </div>

    <div class="grid g3">
      <div class="criterion">
        <span class="cl">Allocation rule</span>
        <span class="cv"><span class="badge brand"><?= e($vac['allocation_rule']) ?></span></span>
        <span class="cn">Spaces are assigned in the order they are applied for.</span>
      </div>
      <div class="criterion">
        <span class="cl">Private market figures</span>
        <span class="cv"><span class="badge warn">At least</span></span>
        <span class="cn">KCCA states <?= num($vac['private_vacant_spaces']) ?> as a floor, not a ceiling.</span>
      </div>
      <div class="criterion">
        <span class="cl">Legal basis</span>
        <span class="cv"><span class="badge green">Markets Act 2023</span></span>
        <span class="cn">Register of markets, s.17; register of vendors, s.18.</span>
      </div>
    </div>

    <div class="source mt-2">
      <span class="lbl">source</span>
      <span>KCCA news 1078 — “Vendors in city markets rally street traders to relocate”, 17 February 2026.</span>
      <a href="<?= e($vac['source']) ?>" target="_blank" rel="noopener" class="right">Open</a>
    </div>
  </div>
</div>

<!-- ============================ FILTERS ========================== -->
<div class="card">
  <form class="filters" method="get">
    <div class="field grow">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>"
             placeholder="Market name, parish or code…" data-search-submit>
    </div>
    <div class="field">
      <label for="category">Category</label>
      <select name="category" id="category" data-autosubmit>
        <option value="">Public and private</option>
        <option value="public"  <?= $category === 'public'  ? 'selected' : '' ?>>Public markets only</option>
        <option value="private" <?= $category === 'private' ? 'selected' : '' ?>>Private markets only</option>
      </select>
    </div>
    <?php renderDivisionFilter('division', $division); ?>
    <div class="field">
      <label for="facility">Has facility</label>
      <select name="facility" id="facility" data-autosubmit>
        <option value="">Any</option>
        <?php foreach ($facilities as $key => [$label, $note]): ?>
          <option value="<?= e($key) ?>" <?= $facility === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a class="btn btn-ghost" href="vacancy.php">Reset</a>
  </form>
</div>

<!-- ======================= FILTER RESULT ========================= -->
<div class="grid g4 mb-2">
  <div class="stat gold">
    <div class="label">Vacant in this view</div>
    <div class="value"><?= num($filteredVacant) ?></div>
    <div class="meta">of <?= num($vac['total_vacant']) ?> citywide</div>
  </div>
  <div class="stat green">
    <div class="label">Public</div>
    <div class="value"><?= num($publicVacant) ?></div>
    <div class="meta">KCCA-managed markets</div>
  </div>
  <div class="stat warn">
    <div class="label">Private</div>
    <div class="value"><?= num($privateVacant) ?></div>
    <div class="meta">operator-managed markets</div>
  </div>
  <div class="stat info">
    <div class="label">Markets matched</div>
    <div class="value"><?= num(count($rows)) ?></div>
    <div class="meta"><?= pct($filteredCap > 0 ? $filteredVacant / $filteredCap * 100 : 0, 1) ?> of their combined capacity</div>
  </div>
</div>

<!-- ============================ CHARTS =========================== -->
<div class="grid g2 mb-2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Vacancy by division</h2>
        <p class="desc">Where the space is, against where the vendors are.</p>
      </div>
    </div>
    <div class="card-body">
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'bar',
        'opts' => [
          'height' => 240,
          'stacked' => true,
          'labels' => array_column($byDivision, 'label'),
          'series' => [
            ['name' => 'Public',  'color' => '#0A6339', 'values' => array_map('intval', array_column($byDivision, 'public'))],
            ['name' => 'Private', 'color' => '#EAA515', 'values' => array_map('intval', array_column($byDivision, 'private'))],
          ],
          'yFormat' => 'num',
        ],
      ]) ?>' aria-label="Vacant spaces by division"></svg>
      <div class="legend">
        <span class="item"><span class="sw" style="background:#0A6339"></span> Public markets</span>
        <span class="item"><span class="sw" style="background:#EAA515"></span> Private markets</span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Most headroom</h2>
        <p class="desc">Vacant share of total capacity — the markets with the most room to absorb vendors.</p>
      </div>
    </div>
    <div class="card-body">
      <svg class="chart" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => [
          'labelW' => 182,
          'rowH' => 24,
          'max'   => 100,
          'format' => 'pct',
          'items' => array_map(static fn($r): array => [
            'label' => $r['name'],
            'value' => $r['vacant_pct'],
            'color' => $r['vacant_pct'] >= 30 ? '#1B7F4D' : ($r['vacant_pct'] >= 12 ? '#EAA515' : '#6B7280'),
          ], $topByHeadroom),
        ],
      ]) ?>' aria-label="Markets ranked by vacant share"></svg>
    </div>
  </div>
</div>

<!-- ========================= MARKET TABLE ======================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Market register</h2>
      <p class="desc">
        <?= num(count($rows)) ?> market<?= count($rows) === 1 ? '' : 's' ?> with space available,
        ranked by vacant count. Under the Markets Act 2023 the Authority keeps the register of markets
        (s.17) and, for public markets, the register of vendors (s.18); a private market operator keeps
        their own vendor register (s.17(2)).
      </p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Market</th><th>Division</th><th>Parish</th>
            <th class="right">Capacity</th><th class="right">Registered</th>
            <th class="right">Vacant</th>
            <th style="width:160px">Occupancy</th>
            <th class="right">Rent</th>
            <th class="center">Facilities</th>
            <th class="center">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $facCount = 0;
              foreach (array_keys($facilities) as $fk) {
                  if (!empty($r[$fk])) $facCount++;
              }
              $official = $r['data_basis'] === 'official';
          ?>
            <tr>
              <td>
                <strong><?= e($r['name']) ?></strong>
                <div class="xsmall muted">
                  <span class="badge <?= $r['category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px">
                    <?= e($r['category']) ?>
                  </span>
                  <?= e($r['code']) ?>
                  <?php if ($official): ?>
                    <span class="badge green" style="font-size:10px">official</span>
                  <?php else: ?>
                    <span class="xsmall muted">· <?= e($r['data_basis']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="xsmall"><?= e($r['division_name']) ?></td>
              <td class="xsmall"><?= e($r['parish'] ?: '—') ?></td>
              <td class="right num"><?= num((int) $r['capacity']) ?></td>
              <td class="right num"><?= num((int) $r['registered_vendors']) ?></td>
              <td class="right num strong"><?= num((int) $r['vacant_spaces']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $r['util'] < 70 ? 'warn' : 'ok' ?>"
                        style="width:<?= (float) clamp($r['util'], 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($r['util'], 0) ?></span>
                </div>
              </td>
              <td class="right xsmall"><?= (int) $r['avg_rent'] === 0 ? '<span class="badge ok">Free</span>' : ugx((int) $r['avg_rent']) ?></td>
              <td class="center">
                <span class="badge <?= $facCount >= 5 ? 'ok' : ($facCount >= 3 ? 'info' : 'warn') ?>"><?= $facCount ?>/7</span>
              </td>
              <td class="center"><span class="badge <?= e(statusTone($r['status'])) ?>"><?= e(humanise($r['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if (!$rows): ?>
    <div class="empty">
      <div class="big"><?= KCCA_icon('store', 32) ?></div>
      <h3>No market matches these filters</h3>
      <p>Try widening the search, or clear the facility requirement.</p>
      <a class="btn btn-ghost" href="vacancy.php">Reset filters</a>
    </div>
  <?php endif; ?>
  <div class="card-foot">
    Public market totals reconcile to the published figures of
    <?= num($vac['public_markets']) ?> markets holding <?= num($vac['public_vacant_spaces']) ?> vacant spaces.
    Private market registers are kept by their operators under the Markets Act 2023, so their individual
    figures are modelled; only the aggregate of <?= num($vac['private_markets']) ?> markets and
    <?= num($vac['private_vacant_spaces']) ?> spaces is published.
  </div>
</div>

<!-- ===================== FACILITY GUIDE ========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Choosing a market by the trade you do</h2>
      <p class="desc">The relocation engine scores facility fit as one of its six criteria. This is the same logic, stated plainly.</p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Trade</th><th>Facilities needed</th><th>Why it matters</th></tr></thead>
        <tbody>
          <?php
          $tradeNeeds = [
            'food-cooking'        => ['Water', 'Toilets', 'Waste collection', 'Power'],
            'meat-fish'           => ['Water', 'Waste collection', 'Cold chain', 'Toilets'],
            'fresh-produce'       => ['Waste collection', 'Storage', 'Accessible ramps'],
            'beverages'           => ['Water', 'Power', 'Waste collection'],
            'crops-grains'        => ['Storage', 'Waste collection'],
            'livestock'           => ['Waste collection', 'Water'],
            'electronics-repairs' => ['Power'],
            'salon-services'      => ['Power', 'Water'],
            'everything else'     => ['Waste collection'],
          ];
          $why = [
            'food-cooking'   => 'Prepared food sold without water is a public health risk, and KCCA has raised hygiene concerns where goods are sold directly from the ground.',
            'meat-fish'      => 'Fresh meat and fish spoil without cold chain, and the waste they generate must be collected daily.',
            'fresh-produce'  => 'Produce needs ground-level access and somewhere to store unsold stock overnight.',
            'beverages'      => 'Cold drinks need power for refrigeration.',
            'crops-grains'   => 'Dry goods spoil slowly but need protection from damp and theft.',
            'livestock'      => 'Waste and water are both in constant demand.',
            'electronics-repairs' => 'Repairs need a power connection.',
            'salon-services' => 'Hair and beauty work needs water and power.',
            'everything else'=> 'Every trader generates waste, and managing it is a shared responsibility.',
          ];
          ?>
          <?php foreach ($tradeNeeds as $trade => $needs): ?>
            <tr>
              <td><strong><?= e(humanise(str_replace('-', '_', $trade))) ?></strong></td>
              <td>
                <?php foreach ($needs as $n): ?><span class="badge info" style="margin-right:4px"><?= e($n) ?></span><?php endforeach; ?>
              </td>
              <td class="small muted"><?= e($why[$trade]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    “Paying a trading licence or taxes does not mean you don’t have a responsibility to manage waste.”
    <b>Benon Kigenyi</b>, Deputy Executive Director, KCCA, 29 September 2026.
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';