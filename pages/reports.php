<?php
/**
 * Reports & Downloads — CSV export of every register, and the official
 * documents behind the system.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('report');

$f = facts();

/* Every export the system offers, described plainly. */
$exports = [
    [
        'key' => 'zones',
        'name' => 'Trade zone register',
        'desc' => 'Every affected site with its measured obstruction, vendor count, duration, complaints and composite congestion score.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM trade_zones'),
        'owner' => 'Physical Planning',
    ],
    [
        'key' => 'vendors',
        'name' => 'Vendor register',
        'desc' => 'Every surveyed trader with trade, turnover, capital, household composition, vulnerability factors and current stage.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM vendors'),
        'owner' => 'Legal Affairs / Market Administration',
    ],
    [
        'key' => 'markets',
        'name' => 'Market register',
        'desc' => 'Every market with capacity, registered vendors, vacant spaces, facilities and figure basis. Totals reconcile to the published KCCA figures.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM markets'),
        'owner' => 'Market Development',
    ],
    [
        'key' => 'allocations',
        'name' => 'Allocation register',
        'desc' => 'Every offer issued, with the score, the distance, the full reasoning and the outcome.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM allocations'),
        'owner' => 'Market Administration',
    ],
    [
        'key' => 'enforcement',
        'name' => 'Enforcement log',
        'desc' => 'Every notice, ultimatum and clear-up operation with authorisation, officers and outcome.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM enforcement_actions'),
        'owner' => 'Legal Affairs',
    ],
    [
        'key' => 'pitches',
        'name' => 'Public market pitch register',
        'desc' => 'Individual pitches in KCCA public markets with section, size, status and current occupant.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM market_spaces'),
        'owner' => 'Market Development',
    ],
    [
        'key' => 'licensing',
        'name' => 'Licensing position',
        'desc' => 'Licence class and year for every vendor, for the Revenue Collection directorate.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM vendors'),
        'owner' => 'Revenue Collection',
    ],
    [
        'key' => 'summary',
        'name' => 'Citywide summary',
        'desc' => 'One line per division: affected sites, vendors, clearance rate, vacant space and utilisation.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM divisions'),
        'owner' => 'Office of the Executive Director',
    ],
    [
        'key' => 'footfall',
        'name' => 'Footfall observations',
        'desc' => 'Every counted movement at every market: date, day type, time band, people in and out, traders present and how it was counted.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM footfall_observations'),
        'owner' => 'Physical Planning',
    ],
    [
        'key' => 'restrictions',
        'name' => 'Land use and trading restrictions',
        'desc' => 'Every statutory or physical constraint on what may be traded where, with the legal basis and the trades it blocks.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM land_use_restrictions'),
        'owner' => 'Legal Affairs',
    ],
    [
        'key' => 'recommendations',
        'name' => 'AI recommendations',
        'desc' => 'Every location the reach model has proposed, with its score, confidence, projected customers and the decision taken on it.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM recommendations'),
        'owner' => 'Office of the Executive Director',
    ],
    [
        'key' => 'stall-requests',
        'name' => 'Stall requests',
        'desc' => 'Every pitch a trader has chosen and asked for, with their details and the decision taken.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM stall_requests'),
        'owner' => 'Market Administration',
    ],
    [
        'key' => 'relocation-history',
        'name' => 'Relocation history',
        'desc' => 'Every state change on every relocation, newest first: who did what, when, and the note recorded against it.',
        'count' => (int) Database::value('SELECT COUNT(*) FROM relocation_events'),
        'owner' => 'Internal Audit',
    ],
];

$documents = [
    ['KCCA Strategic Plan FY2025/26 – FY2029/30', 'https://www.kcca.go.ug/KCCA_STRATEGIC_PLAN_2025.pdf',
     'The five-year plan, valued at ' . $f['strategic_plan']['value'] . ', with the targets this system reports against.'],
    ['About the Authority', 'https://www.kcca.go.ug/about-the-authority',
     'Mandate, governance structure and leadership.'],
    ['Trading licence FAQ', 'https://www.kcca.go.ug/faqs-trading-license',
     'Requirements, assessment and payment under the Trade Licensing Act Cap 101.'],
    ['Trading licence guidance (PDF)', 'https://www.kcca.go.ug/uDocs/Trade_Licence_FAQs.pdf',
     'The Authority’s published trading licence guidance.'],
    ['KCCA news 1078 — vendors rally street traders to relocate', $f['vacancy']['source'],
     'The source of the 2,520 public and 1,980 private vacant space figures.'],
    ['KCCA news 1017 — strategic plan launch', 'https://www.kcca.go.ug/news/1017',
     'Launch of the ' . $f['strategic_plan']['value'] . ' plan, Client Charter and Service Delivery Standards.'],
    ['Ministerial policy statement', 'https://www.kcca.go.ug/media/docs/MPS%202024-25.pdf',
     'Directorate mandates, including Physical Planning’s zoning and demarcation role.'],
    ['Kampala City Nutrition Action Plan', 'https://www.kcca.go.ug/media/docs/KCC-NAP-27012025.pdf',
     'Public health commitments relevant to market food hygiene.'],
];

$pageTitle = 'Reports & Downloads';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Reports &amp; Downloads
    </div>
    <div class="eyebrow">Corporate Affairs · Accountability</div>
    <h1>Reports &amp; Downloads</h1>
    <p>
      Every register in the system, exportable as CSV, plus the official KCCA documents the figures
      are drawn from. Exports carry the source basis for each figure so a reader always knows what is
      published and what is measured.
    </p>
  </div>
  <div class="actions">
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print this page</button>
  </div>
</div>

<!-- =========================== EXPORTS =========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Register exports</h2>
      <p class="desc">Comma-separated values, UTF-8, one header row. Opens directly in a spreadsheet.</p>
    </div>
    <span class="badge info">CSV</span>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Report</th><th>What it contains</th><th class="right">Rows</th><th>Responsible directorate</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($exports as $x): ?>
            <tr>
              <td><strong><?= e($x['name']) ?></strong></td>
              <td class="small muted"><?= e($x['desc']) ?></td>
              <td class="right num"><?= num($x['count']) ?></td>
              <td class="xsmall"><?= e($x['owner']) ?></td>
              <td class="right">
                <a class="btn btn-ghost btn-sm"
                   href="../api/export.php?report=<?= e($x['key']) ?>">
                  <?= KCCA_icon('download', 13) ?> Download
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-foot">
    Every export is logged in the audit trail with the requesting officer's name, so downloads of the
    vendor register can be accounted for.
  </div>
</div>

<!-- ====================== OFFICIAL DOCUMENTS ===================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Official KCCA documents and sources</h2>
      <p class="desc">Everything this system asserts about the city traces back to one of these.</p>
    </div>
  </div>
  <div class="card-body">
    <div class="grid g2">
      <?php foreach ($documents as $d): ?>
        <a class="criterion" href="<?= e($d[1]) ?>" target="_blank" rel="noopener"
           style="text-decoration:none;display:block">
          <span class="cl"><?= e($d[0]) ?></span>
          <span class="cv"><?= KCCA_icon('external', 14) ?></span>
          <span class="cn"><?= e($d[2]) ?></span>
          <span class="cw"><?= e(parse_url($d[1], PHP_URL_HOST)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ========================= PROVENANCE ========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>How to read the figures in this system</h2>
      <p class="desc">Being explicit about provenance is part of the point.</p>
    </div>
    <span class="badge green">Explainable by design</span>
  </div>
  <div class="card-body">
    <div class="grid g3">
      <div class="criterion">
        <span class="cl">Official</span>
        <span class="cv"><span class="badge green">Published</span></span>
        <span class="cn">
          Citywide vacancy totals, market counts, Usafi Market's capacity and registration, the
          Strategic Plan targets, leadership and law. Reproduced with the source, and reconciled exactly.
        </span>
      </div>
      <div class="criterion">
        <span class="cl">Derived</span>
        <span class="cv"><span class="badge gold">Computed</span></span>
        <span class="cn">
          Figures computed from official totals so that the register adds up — for example the
          remaining public markets' vacancy, which reconciles to 2,520.
        </span>
      </div>
      <div class="criterion">
        <span class="cl">Estimated</span>
        <span class="cv"><span class="badge muted">Survey layer</span></span>
        <span class="cn">
          Individual trade zones, vendor records, private market pitches and enforcement outcomes.
          These are the operational layer of the system, generated at realistic Kampala scale and
          labelled as such wherever they appear.
        </span>
      </div>
    </div>

    <div class="divider"></div>

    <h4 class="mb-1">Reconciliation check</h4>
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Figure</th><th class="right">This system</th><th class="right">KCCA published</th><th class="center">Status</th></tr>
        </thead>
        <tbody>
          <?php
          $checks = [
              ['Public markets',            (int) Database::value("SELECT COUNT(*) FROM markets WHERE category='public'"),  $f['vacancy']['public_markets']],
              ['Vacant spaces, public',     (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='public'"),  $f['vacancy']['public_vacant_spaces']],
              ['Private markets',           (int) Database::value("SELECT COUNT(*) FROM markets WHERE category='private'"), $f['vacancy']['private_markets']],
              ['Vacant spaces, private',    (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='private'"), $f['vacancy']['private_vacant_spaces']],
              ['Usafi Market capacity',     (int) Database::value("SELECT capacity FROM markets WHERE code='USF-01'"), 3000],
              ['Usafi registered vendors',  (int) Database::value("SELECT registered_vendors FROM markets WHERE code='USF-01'"), 1600],
              ['Divisions',                 (int) Database::value('SELECT COUNT(*) FROM divisions'), 5],
              ['City population',           (int) Database::value('SELECT COALESCE(SUM(population),0) FROM divisions'), $f['city']['population']],
              ['Parishes',                  (int) Database::value('SELECT COALESCE(SUM(parishes),0) FROM divisions'), $f['city']['parishes']],
              ['LC1 wards',                 (int) Database::value('SELECT COALESCE(SUM(lc1_wards),0) FROM divisions'), $f['city']['lc1_wards']],
              ['City area (km²)',           (float) Database::value('SELECT COALESCE(SUM(area_sqkm),0) FROM divisions'), $f['city']['area_sqkm']],
          ];
          foreach ($checks as [$label, $actual, $expected]):
              $ok = abs((float) $actual - (float) $expected) < 0.001;
          ?>
            <tr>
              <td><?= e($label) ?></td>
              <td class="right num strong"><?= num($actual) ?></td>
              <td class="right num"><?= num($expected) ?></td>
              <td class="center">
                <span class="badge <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '&#10003; Reconciles' : 'Mismatch' ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="xsmall muted mt-1" style="margin-bottom:0">
      Source: KCCA news 1078 (17 February 2026) for vacancy; KCCA Strategic Plan FY2025/26–FY2029/30
      Table 1.1 for the city and division figures; KCCA news 1078 for Usafi Market.
    </p>
  </div>
</div>

<!-- ========================== STATEMENTS ========================= -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Official statements on trade order</h2>
      <p class="desc">Quoted and attributed, as published.</p>
    </div>
  </div>
  <div class="card-body">
    <?php foreach ($f['quotations'] as $q): ?>
      <div class="quote">
        <p><?= e($q['text']) ?></p>
        <footer><b><?= e($q['speaker']) ?></b> — <?= e($q['role']) ?> · <?= e(fmtDate($q['date'])) ?></footer>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';