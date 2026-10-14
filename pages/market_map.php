<?php
/**
 * Trading Location — full detail for one market, on a map.
 *
 * Click a market anywhere in the system and you land here: the market's
 * own record, every individual pitch plotted at its real position, and
 * which of them are free. A trader can choose a pitch and request it; an
 * officer can see everything and act on it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$f = facts();

/* ---------------- The market ---------------- */
$marketId = (int) ($_GET['market'] ?? $_GET['location'] ?? 0);

$market = $marketId > 0 ? Database::one(
    'SELECT m.*, d.name AS division_name, d.code AS div_code, d.mayor,
            d.population, d.area_sqkm
       FROM markets m
       JOIN divisions d ON d.id = m.division_id
      WHERE m.id = ? LIMIT 1',
    [$marketId]
) : null;

/* ---------------- The market list, when no id was given ---------------- */
$allMarkets = Database::all(
    'SELECT m.id, m.code, m.name, m.category, m.capacity, m.vacant_spaces,
            m.registered_vendors, d.name AS division_name, m.status
       FROM markets m JOIN divisions d ON d.id = m.division_id
      ORDER BY m.category = \'public\' DESC, m.name'
);

if (!$market) {
    $pageTitle = 'Trading Locations';
    require __DIR__ . '/../layouts/header.php';
    ?>
    <div class="page-head">
      <div class="grow">
        <div class="breadcrumb"><a href="dashboard.php">Command</a><span class="sep">/</span>Trading Locations</div>
        <div class="eyebrow">Location &amp; space management</div>
        <h1>Choose a location</h1>
        <p>Open a location to see its pitch plan, the pitches that are free, and everything else
           recorded about it.</p>
      </div>
    </div>

    <div class="card">
      <div class="card-body tight">
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr><th>Location</th><th>Division</th><th>Type</th>
                  <th class="right">Capacity</th><th class="right">Vacant</th>
                  <th class="center">Status</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($allMarkets as $m): ?>
                <tr>
                  <td><strong><?= e($m['name']) ?></strong><div class="xsmall muted"><?= e($m['code']) ?></div></td>
                  <td class="xsmall"><?= e($m['division_name']) ?></td>
                  <td><span class="badge <?= $m['category'] === 'public' ? 'green' : 'gold' ?>"><?= e($m['category']) ?></span></td>
                  <td class="right num"><?= num((int) $m['capacity']) ?></td>
                  <td class="right num strong"><?= num((int) $m['vacant_spaces']) ?></td>
                  <td class="center"><span class="badge <?= e(statusTone($m['status'])) ?>"><?= e(humanise($m['status'])) ?></span></td>
                  <td class="right">
                    <a class="btn btn-ghost btn-sm" href="market_map.php?market=<?= (int) $m['id'] ?>">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
    require __DIR__ . '/../layouts/footer.php';
    exit;
}

/* ---------------- Everything about this market ---------------- */
$footer = $f['authority'];

$isPublic = $market['category'] === 'public';
$capacity = (int) $market['capacity'];

/* Stall counts */
$stallCounts = [];
foreach (Database::all(
    'SELECT status, COUNT(*) AS n FROM market_spaces WHERE market_id = ? GROUP BY status',
    [$marketId]
) as $c) {
    $stallCounts[$c['status']] = (int) $c['n'];
}
$pitches = (int) Database::value('SELECT COUNT(*) FROM market_spaces WHERE market_id = ?', [$marketId]);

/* Footfall and restrictions */
$footfall = ReachModel::footfall($marketId, $market);
$restrictions = Database::all(
    'SELECT * FROM land_use_restrictions WHERE market_id = ? AND is_active = 1 ORDER BY code',
    [$marketId]
);
$tradeMix = Database::all(
    "SELECT mx.business_category, mx.vendor_count, c.name, c.demand_index, c.avg_spend_per_customer_ugx
       FROM market_trade_mix mx
       LEFT JOIN trade_categories c ON c.code = mx.business_category
      WHERE mx.market_id = ? ORDER BY mx.vendor_count DESC",
    [$marketId]
);

/* Recent requests, so an officer sees what is waiting */
$requests = [];
if (Auth::can('allocate')) {
    $requests = Database::all(
        "SELECT sr.*, s.space_code FROM stall_requests sr
           JOIN market_spaces s ON s.id = sr.space_id
          WHERE sr.market_id = ?
          ORDER BY sr.status = 'pending' DESC, sr.created_at DESC
          LIMIT 40",
        [$marketId]
    );
}

/* Can this visitor pick a stall? */
/* Who may ask for a pitch: a member of the public, an officer with authority
   to allocate, or a trader signed in as a vendor. Applying for a stall is the
   vendor's core function, so it must not sit behind a staff permission. */
$canChoose = !Auth::check() || Auth::can('allocate') || Auth::role() === 'vendor';

/* The trader's own record, so the request form is not a blank slate asking
   them to retype what the Authority already holds.

   This was a real bug: the modal rendered every field empty, so a signed-in
   vendor had to type their name, telephone and national ID again, and any
   formatting difference produced "Some details are missing" — for a field
   they were never obliged to fill. They gave these details when they
   registered. A vendor_id is also sent, so the request is matched against
   their record rather than against a phone number they have just typed. */
$meVendor = null;
if (Auth::role() === 'vendor') {
    $meVendorId = (int) (Auth::user()['vendor_id'] ?? 0);
    if ($meVendorId > 0) {
        $meVendor = Database::one(
            'SELECT id, vendor_code, full_name, phone, national_id, business_category,
                    division_id, current_zone_id
               FROM vendors WHERE id = ? LIMIT 1',
            [$meVendorId]
        );
    }
}
$meVendorCanSeek = true;
if ($meVendor) {
    /* A trader already holding space does not need to ask for more. */
    $meVendorCanSeek = (int) Database::value(
        "SELECT COUNT(*) FROM allocations
          WHERE vendor_id = ? AND status IN ('proposed','offered','accepted','occupied')",
        [(int) $meVendor['id']]
    ) === 0;
}

$occupancy = $capacity > 0 ? round((int) $market['registered_vendors'] / $capacity * 100, 1) : 0.0;

/* Whether a trader may pick a pitch. Rendered into the script below as a
   JS literal, so the page and its behaviour come from one value. */
$canChooseJs = $canChoose ? 'true' : 'false';

page_assets('map', 'stall', 'charts');
$pageTitle = $market['name'];
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="market_map.php">Trading Locations</a>
      <span class="sep">/</span>
      <a href="locations.php?division=<?= e($market['div_code']) ?>"><?= e($market['division_name']) ?></a>
    </div>
    <div class="eyebrow">
      <?= e($isPublic ? 'KCCA public market' : 'Privately operated market') ?> ·
      <?= e($market['code']) ?>
    </div>
    <h1><?= e($market['name']) ?></h1>
    <p>
      <?= e($market['parish'] ?: $market['division_name']) ?> parish
      <?php if ($market['opened_year']): ?> · opened <?= (int) $market['opened_year'] ?><?php endif; ?>
      <?php if ($market['transport_stand']): ?> · <?= e($market['transport_stand']) ?><?php endif; ?>
      <?php if ($market['admin_name']): ?> · managed by <?= e($market['admin_name']) ?><?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <?php if (Auth::check()): ?>
      <a class="btn btn-ghost" href="locations.php?location=<?= (int) $market['id'] ?>">Pitch register</a>
    <?php endif; ?>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ======================= MARKET AT A GLANCE ==================== -->
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Capacity</div>
    <div class="value"><?= num($capacity) ?></div>
    <div class="meta">pitches</div>
  </div>
  <div class="stat <?= (int) $market['registered_vendors'] > 0 ? 'info' : '' ?>">
    <div class="label">Registered</div>
    <div class="value"><?= num((int) $market['registered_vendors']) ?></div>
    <div class="meta"><?= pct($occupancy, 1) ?> full</div>
  </div>
  <div class="stat green">
    <div class="label">Vacant</div>
    <div class="value"><?= num((int) $market['vacant_spaces']) ?></div>
    <div class="meta">available now</div>
  </div>
  <div class="stat <?= $footfall['confidence'] >= 0.5 ? 'ok' : 'warn' ?>">
    <div class="label">Footfall</div>
    <div class="value" style="font-size:22px"><?= num($footfall['daily']) ?></div>
    <div class="meta">people a day</div>
  </div>
  <div class="stat gold">
    <div class="label">Rent</div>
    <div class="value" style="font-size:20px">
      <?= (int) Database::value(
            'SELECT COALESCE(ROUND(AVG(monthly_rent_ugx)),0) FROM market_spaces
              WHERE market_id = ? AND status = \'vacant\'',
            [$marketId]) === 0 ? 'Free' : 'Charged' ?>
    </div>
    <div class="meta">on vacant pitches</div>
  </div>
</div>

<!-- ============================ THE MAP ========================= -->
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Pitch plan</h2>
      <p class="desc">
        Every pitch in this market at its real position. Click a pitch to see what it is and
        whether it is free.
      </p>
    </div>
    <div class="tools">
      <span id="stallmap-status" class="map-status"></span>
    </div>
  </div>

  <div class="card-body tight">
    <div class="filters">
      <div class="field">
        <label for="stall-status">Status</label>
        <select id="stall-status" data-stall-filter>
          <option value="">All statuses</option>
          <option value="vacant">Vacant</option>
          <option value="reserved">Reserved</option>
          <option value="under-maintenance">Under maintenance</option>
          <option value="occupied">Occupied</option>
        </select>
      </div>
      <div class="field">
        <label for="stall-section">Section</label>
        <select id="stall-section" data-stall-filter>
          <option value="">All sections</option>
          <?php foreach (Database::column(
              'SELECT DISTINCT section FROM market_spaces
                WHERE market_id = ? AND section IS NOT NULL ORDER BY section',
              [$marketId]) as $sec): ?>
            <option value="<?= e($sec) ?>"><?= e($sec) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grow"></div>
      <span class="hint">
        <?php if ($isPublic): ?>
          <?= num($pitches) ?> pitches held by the Authority · the
          <?= num((int) $market['vacant_spaces']) ?> vacant ones carry no charge.
        <?php else: ?>
          A private market. The operator keeps their own pitch register; the
          vacancies shown are the ones they have declared.
        <?php endif; ?>
      </span>
    </div>

    <div class="map-wrap">
      <div id="stallmap" class="map tall"></div>
      <div id="stallmap-tools" class="map-tools">
        <button type="button" class="map-btn" data-zoom="in" aria-label="Zoom in">+</button>
        <button type="button" class="map-btn" data-zoom="out" aria-label="Zoom out">&minus;</button>
        <button type="button" class="map-btn wide" data-zoom="reset">Whole market</button>
      </div>
      <div id="stallmap-legend" class="map-legend stall-legend">
        <button type="button" class="lg" data-status-filter="vacant" title="Show vacant only">
          <span class="sw" style="background:#1B7F4D"></span>
          Vacant <b id="lg-vacant"><?= num((int) ($stallCounts['vacant'] ?? 0)) ?></b>
        </button>
        <button type="button" class="lg" data-status-filter="reserved" title="Show reserved only">
          <span class="sw" style="background:#EAA515"></span>
          Reserved <b><?= num((int) ($stallCounts['reserved'] ?? 0)) ?></b>
        </button>
        <button type="button" class="lg" data-status-filter="requested" title="Show requested only">
          <span class="sw" style="background:#7B3FA0"></span>
          Requested <b id="lg-requested"><?= num((int) Database::value(
              "SELECT COUNT(*) FROM stall_requests WHERE market_id = ? AND status IN ('pending','reviewing')",
              [$marketId])) ?></b>
        </button>
        <button type="button" class="lg" data-status-filter="under-maintenance" title="Show maintenance only">
          <span class="sw" style="background:#C0261A"></span>
          Maintenance <b><?= num((int) ($stallCounts['under-maintenance'] ?? 0)) ?></b>
        </button>
        <button type="button" class="lg" data-status-filter="occupied" title="Show occupied only">
          <span class="sw" style="background:#8C9199"></span>
          Occupied <b><?= num((int) ($stallCounts['occupied'] ?? 0)) ?></b>
        </button>
        <button type="button" class="lg" data-status-filter="all">Show all</button>
      </div>
    </div>
  </div>
</div>

<div class="grid g-3-2">
  <!-- ==================== THE STALL LIST ==================== -->
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Individual trading spaces</h2>
        <p class="desc" id="stall-count">Loading…</p>
      </div>
    </div>
    <div class="card-body tight">
      <div class="table-wrap scroll" id="stall-table" style="max-height:520px;overflow-y:auto">
        <table class="data compact">
          <thead>
            <tr>
              <th>Pitch</th><th>Section</th><th class="right">Size</th>
              <th class="center">Status</th><th class="center">Facilities</th><th></th>
            </tr>
          </thead>
          <tbody id="stall-rows"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ================= THE SELECTED STALL =================== -->
  <div>
    <div class="card" id="stall-detail">
      <div class="card-head"><div class="grow"><h2>Selected pitch</h2></div></div>
      <div class="card-body" id="stall-detail-body">
        <div class="empty">
          <div class="big"><?= KCCA_icon('store', 28) ?></div>
          <h3>No pitch selected</h3>
          <p>Click a pitch on the map, or one in the list, to see its details.</p>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Facilities</h2></div></div>
      <div class="card-body">
        <?php
        $facilities = [
            'water_available'   => 'Water supply',
            'power_available'   => 'Electricity',
            'toilets'           => 'Toilets',
            'waste_collection'  => 'Waste collection',
            'storage_rooms'     => 'Storage rooms',
            'cold_chain'        => 'Cold chain',
            'accessible_ramps'  => 'Accessible ramps',
        ];
        $have = 0;
        foreach ($facilities as $col => $label) {
            if (!empty($market[$col])) { $have++; }
        }
        ?>
        <div class="stack-s">
          <?php foreach ($facilities as $col => $label): ?>
            <div class="row-tight">
              <span class="badge <?= !empty($market[$col]) ? 'ok' : 'muted' ?>">
                <?= !empty($market[$col]) ? '&#10003;' : '&#8211;' ?>
              </span>
              <span class="<?= !empty($market[$col]) ? 'strong' : 'muted' ?>"><?= e($label) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="xsmall muted mt-1" style="margin-bottom:0">
          <?= $have ?> of 7 facilities recorded at this market.
          <?= $have >= 6 ? 'A well-served market — most trades can be carried on here.'
             : ($have >= 4 ? 'Adequate for most trades; some will need to adapt.'
                           : 'Thin facilities: check what your trade needs before choosing a pitch.') ?>
        </p>
      </div>
    </div>

    <?php if ($restrictions): ?>
      <div class="card">
        <div class="card-head"><div class="grow"><h2>Restrictions</h2></div></div>
        <div class="card-body">
          <?php foreach ($restrictions as $r): ?>
            <div class="criterion">
              <span class="cl"><?= e($r['title']) ?></span>
              <span class="cn"><?= e($r['description']) ?></span>
              <span class="cw"><?= e($r['legal_basis'] ?: 'Recorded by the Authority') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ==================== WHAT TRADES ARE HERE =================== -->
<?php if ($tradeMix): ?>
<div class="card mt-2">
  <div class="card-head">
    <div class="grow">
      <h2>What is already trading here</h2>
      <p class="desc">The mix of trades an occupier joins. Red is where demand is strongest.</p>
    </div>
  </div>
  <div class="card-body">
    <svg class="chart" data-chart='<?= ejs([
      'type' => 'hbar',
      'opts' => [
        'labelW' => 168, 'rowH' => 26,
        'items' => array_map(static fn(array $r): array => [
          'label' => $r['name'] ?? humanise($r['business_category']),
          'value' => (int) $r['vendor_count'],
          'color' => (int) ($r['demand_index'] ?? 0) >= 70 ? '#C0261A' : '#0A6339',
        ], $tradeMix),
      ],
    ]) ?>' aria-label="Trade mix at this market"></svg>
    <p class="xsmall muted mt-1" style="margin-bottom:0">
      Bars in red are trades with a demand index of 70 or more — the most contested pitches, and the
      hardest to keep a foothold in if a newcomer arrives with the same goods.
    </p>
  </div>
</div>
<?php endif; ?>

<!-- ==================== FOOTFALL ============================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Footfall</h2>
      <p class="desc"><?= e($footfall['basis']) ?></p>
    </div>
  </div>
  <div class="card-body">
    <?php if ($footfall['observations'] > 0): ?>
      <div class="grid g4">
        <div class="stat"><div class="label">Daily</div>
          <div class="value" style="font-size:20px"><?= num($footfall['daily']) ?></div>
          <div class="meta">movements in and out</div></div>
        <div class="stat info"><div class="label">Busiest</div>
          <div class="value" style="font-size:15px;line-height:1.4"><?= e(ReachModel::hourBandLabel($footfall['peak_hour_band'])) ?></div>
          <div class="meta">on <?= e($footfall['peak_day']) ?>s</div></div>
        <div class="stat"><div class="label">Days counted</div>
          <div class="value" style="font-size:20px"><?= num($footfall['days_covered']) ?></div>
          <div class="meta"><?= num($footfall['observations']) ?> observations</div></div>
        <div class="stat <?= $footfall['confidence'] >= 0.5 ? 'ok' : 'warn' ?>"><div class="label">Confidence</div>
          <div class="value" style="font-size:20px"><?= pct($footfall['confidence'] * 100, 0) ?></div>
          <div class="meta">in the figure</div></div>
      </div>
    <?php else: ?>
      <div class="alert alert-warn">
        <strong>No footfall has been counted here yet.</strong> The figure is estimated from the
        division's population and the trading this market does. Counting footfall would raise
        confidence for every recommendation made for it.
        <?php if (Auth::can('report')): ?>
          <div class="mt-1"><a class="btn btn-ghost btn-sm" href="locations.php?location=<?= (int) $market['id'] ?>&view=footfall">Record a count</a></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============== PENDING REQUESTS, FOR OFFICERS =============== -->
<?php if ($requests): ?>
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Requests for pitches here</h2>
      <p class="desc">Traders who have chosen a specific pitch. A request is not an allocation —
        the Authority decides.</p>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Reference</th><th>Pitch</th><th>Trader</th><th>Telephone</th>
              <th>Trade</th><th>Reason given</th><th class="center">Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $rq): ?>
            <tr>
              <td class="mono xsmall"><?= e($rq['reference']) ?></td>
              <td class="mono xsmall"><?= e($rq['space_code']) ?></td>
              <td class="xsmall"><?= e($rq['full_name']) ?></td>
              <td class="xsmall"><?= e($rq['phone']) ?></td>
              <td class="xsmall"><?= e(humanise($rq['business_category'] ?? '')) ?></td>
              <td class="xsmall muted" style="max-width:240px"><?= e(truncateText($rq['reason'] ?? '', 80)) ?></td>
              <td class="center"><span class="badge <?= e(statusTone($rq['status'])) ?>"><?= e(humanise($rq['status'])) ?></span></td>
              <td class="right xsmall"><?= e(fmtDate($rq['created_at'], 'd M')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ================= CONTACT / SOURCE ========================== -->
<div class="card">
  <div class="card-body">
    <div class="row">
      <div class="grow">
        <strong>Questions about this market</strong>
        <div class="xsmall muted">
          <?= e($footer['legal_name']) ?> ·
          <?= e($market['admin_name'] ?: 'Market Administration') ?><?= $market['admin_phone'] ? ' · ' . e($market['admin_phone']) : '' ?><br>
          Tel <?= e($footer['telephone']) ?> · Toll free <?= e($footer['toll_free']) ?> ·
          <a href="mailto:<?= e($footer['email']) ?>"><?= e($footer['email']) ?></a>
        </div>
      </div>
      <div class="right">
        <a class="btn btn-ghost btn-sm" href="https://kcca.go.ug" target="_blank" rel="noopener">
          <?= KCCA_icon('external', 13) ?> kcca.go.ug
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =============== THE STALL REQUEST FORM ====================== -->
<div class="modal-back" id="request-back" hidden>
  <div class="modal">
    <form method="post" class="modal-form" id="request-form">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
      <input type="hidden" name="action" value="request">
      <input type="hidden" name="space_id" id="req-space-id" value="">
      <?php if ($meVendor): ?>
        <input type="hidden" name="vendor_id" id="req-vendor-id" value="<?= (int) $meVendor['id'] ?>">
      <?php endif; ?>
      <div class="modal-head">
        <h3 id="req-title">Request this pitch</h3>
        <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info" id="req-summary"></div>

        <?php if ($meVendor && !$meVendorCanSeek): ?>
          <div class="alert alert-warn">
            You are recorded as holding space already. If you want to move, speak to the market
            administrator first so the current allocation can be closed.
          </div>
        <?php endif; ?>

        <?php if ($meVendor): ?>
          <?php /* Already supplied at registration, so they are shown as a
                   record rather than asked for again. */ ?>
          <div class="kv">
            <div class="row-tight"><span class="cl">Requesting as</span>
              <span class="strong"><?= e($meVendor['full_name']) ?></span></div>
            <div class="row-tight"><span class="cl">Vendor code</span>
              <span><?= e($meVendor['vendor_code']) ?></span></div>
            <div class="row-tight"><span class="cl">Telephone</span>
              <span><?= e($meVendor['phone']) ?></span></div>
            <div class="row-tight"><span class="cl">On the register</span>
              <span><?= e($meVendor['national_id'] ?: 'not recorded') ?></span></div>
          </div>
          <p class="xsmall muted">
            These are the details you gave when you registered. If any of them are wrong,
            <a href="profile.php">correct them on your record</a> before you send this.
          </p>
        <?php endif; ?>

        <div class="grid g2">
          <?php if (!$meVendor): ?>
            <div class="field">
              <label for="req-name">Full name <span class="req">*</span></label>
              <input type="text" id="req-name" name="full_name" required maxlength="140">
            </div>
            <div class="field">
              <label for="req-phone">Telephone <span class="req">*</span></label>
              <input type="text" id="req-phone" name="phone" required placeholder="07…">
              <span class="hint">We will only use this to tell you about your request.</span>
            </div>
            <div class="field">
              <label for="req-nid">National ID number</label>
              <input type="text" id="req-nid" name="national_id" maxlength="20">
              <span class="hint">Optional. We use it to match you to your record.</span>
            </div>
            <div class="field">
              <label for="req-trade">What do you sell?</label>
              <select id="req-trade" name="business_category">
                <option value="">Choose a trade</option>
                <?php foreach (Database::all('SELECT code, name FROM trade_categories ORDER BY demand_index DESC') as $c): ?>
                  <option value="<?= e($c['code']) ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          <div class="field" style="grid-column:1/-1">
            <label for="req-detail">Describe your goods</label>
            <input type="text" id="req-detail" name="trade_detail" maxlength="200"
                   placeholder="e.g. tomatoes, onions, dodo">
          </div>
          <div class="field" style="grid-column:1/-1">
            <label for="req-reason">Why this pitch?</label>
            <textarea id="req-reason" name="reason" rows="3" maxlength="500"
              placeholder="e.g. I trade near the main gate, I need a step-free pitch, I have traded in Section B for nine years."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"
          <?= $meVendor && !$meVendorCanSeek ? 'disabled title="You already hold space"' : '' ?>>
          Send request
        </button>
      </div>
    </form>
  </div>
</div>


/* Rendered by the footer, after kcca.js and stall-map.js have loaded, so
   KCCA.api and KCCAStallMap are both defined when this runs. */
<?php
$pageScripts = <<<HTML
<script>
(function () {
  var state = { stalls: [], market: null, selected: null };
  var fmtUGX = function (n) {
    if (!n) return '—';
    if (n >= 1e9) return (n / 1e9).toFixed(1) + 'bn UGX';
    if (n >= 1e6) return (n / 1e6).toFixed(1) + 'm UGX';
    if (n >= 1e3) return Math.round(n / 1e3) + 'k UGX';
    return n + ' UGX';
  };

  function loadStalls() {
    var status = document.getElementById('stall-status').value;
    var section = document.getElementById('stall-section').value;
    var url = KCCA.api('api/market_stalls.php?market={$market['id']}&limit=3000')
      + (status ? '&status=' + encodeURIComponent(status) : '')
      + (section ? '&section=' + encodeURIComponent(section) : '');

    return fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        state.stalls = data.stalls;
        state.market = data.market;
        renderTable(data);
        return data;
      });
  }

  function renderTable(data) {
    var rows = data.stalls.slice(0, 400);
    var html = rows.map(function (s) {
      var fac = [];
      if (s.has_water) fac.push('W');
      if (s.has_power) fac.push('P');
      if (s.near_toilet) fac.push('T');
      if (s.step_free) fac.push('S');
      return '<tr>' +
        '<td class="mono xsmall">' + esc(s.space_code) + '</td>' +
        '<td class="xsmall">' + esc(s.section) + '</td>' +
        '<td class="right num xsmall">' + s.size_sqm.toFixed(0) + ' m²</td>' +
        '<td class="center"><span class="badge ' + tone(s.status) + '">' + label(s.status) + '</span></td>' +
        '<td class="center xsmall muted">' + (fac.length ? fac.join(' ') : '—') + '</td>' +
        '<td class="right"><button type="button" class="btn btn-ghost btn-sm" data-pick="' + s.id + '">Select</button></td>' +
        '</tr>';
    }).join('');

    document.getElementById('stall-rows').innerHTML = html;
    document.getElementById('stall-count').textContent =
      (data.stalls.length === 1 ? '1 pitch' : data.stalls.length.toLocaleString() + ' pitches')
      + (data.stalls.length > 400 ? ' — showing the first 400. Filter by status or section to narrow it.' : '');

    document.querySelectorAll('[data-pick]').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = parseInt(b.getAttribute('data-pick'), 10);
        var s = data.stalls.find(function (x) { return x.id === id; });
        if (s) selectStall(s);
      });
    });
  }

  function selectStall(s) {
    state.selected = s;
    KCCAStallMap.focus(s.lat, s.lng, 19);

    var reserved = s.requested > 0;
    var body = document.getElementById('stall-detail-body');

    body.innerHTML =
      '<div class="row-tight mb-1">' +
        '<span class="mono strong" style="font-size:15px">' + esc(s.space_code) + '</span>' +
        '<span class="badge ' + tone(s.status) + '">' + label(s.status) + '</span>' +
        (reserved ? '<span class="badge warn">' + s.requested + ' request' + (s.requested === 1 ? '' : 's') + ' waiting</span>' : '') +
      '</div>' +
      '<dl class="kv">' +
        '<dt>Section</dt><dd style="text-align:right">' + esc(s.section) + '</dd>' +
        '<dt>Size</dt><dd>' + s.size_sqm.toFixed(1) + ' m²</dd>' +
        '<dt>Rent</dt><dd>' + (s.monthly_rent_ugx ? fmtUGX(s.monthly_rent_ugx) : 'No charge') + '</dd>' +
        '<dt>Water</dt><dd>' + (s.has_water ? 'Yes' : 'No') + '</dd>' +
        '<dt>Power</dt><dd>' + (s.has_power ? 'Yes' : 'No') + '</dd>' +
        '<dt>Near a toilet</dt><dd>' + (s.near_toilet ? 'Yes' : 'No') + '</dd>' +
        '<dt>Step free</dt><dd>' + (s.step_free ? 'Yes' : 'No') + '</dd>' +
        '<dt>Position</dt><dd class="xsmall mono">' + s.lat.toFixed(5) + ', ' + s.lng.toFixed(5) + '</dd>' +
      '</dl>' +
      (s.status === 'vacant' && {$canChooseJs}
        ? '<button type="button" class="btn btn-primary btn-block mt-1" id="take-this">Take this pitch</button>'
           + '<span class="xsmall muted" style="display:block;margin-top:8px">Sending a request does not reserve the pitch. '
           + 'The Authority checks it against your record and confirms.</span>'
        : '') +
      (s.status !== 'vacant'
        ? '<p class="small muted" style="margin-bottom:0">This pitch is ' + label(s.status).toLowerCase()
           + ', so it cannot be requested. Pick a vacant one.</p>'
        : '');
  }

  function tone(s) {
    return { vacant: 'ok', reserved: 'warn', occupied: 'muted', 'under-maintenance': 'bad' }[s] || 'muted';
  }
  function label(s) {
    return { vacant: 'Vacant', reserved: 'Reserved', occupied: 'Occupied', 'under-maintenance': 'Maintenance' }[s] || s;
  }
  function esc(t) {
    return String(t === null || t === undefined ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ---- request form ---- */
  document.getElementById('stall-detail-body').addEventListener('click', function (e) {
    if (e.target.id !== 'take-this') return;
    var s = state.selected;
    if (!s) return;
    document.getElementById('req-space-id').value = s.id;
    document.getElementById('req-title').textContent = 'Request ' + s.space_code;
    document.getElementById('req-summary').innerHTML =
      '<strong>' + esc(s.space_code) + '</strong> — ' + esc(s.section)
      + ' · ' + s.size_sqm.toFixed(1) + ' m²'
      + (s.has_water ? ' · water' : '') + (s.step_free ? ' · step free' : '')
      + '. At ' + esc(state.market.name) + ', ' + esc(state.market.division) + ' Division.';
    var back = document.getElementById('request-back');
    back.hidden = false;
    document.body.style.overflow = 'hidden';
  });

  document.querySelectorAll('#request-back [data-modal-close]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('request-back').hidden = true;
      document.body.style.overflow = '';
    });
  });

  document.getElementById('request-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = this.querySelector('[type=submit]');
    btn.disabled = true; btn.textContent = 'Sending…';

    /* Read the form through a helper that returns '' for a field the form
       does not have. A signed-in trader is shown their record instead of
       the identity inputs, so those ids do not exist — and reading .value
       off a missing id throws, which takes the whole submit with it and
       leaves the button spinning with nothing sent. That is exactly the
       "some details are missing" a trader meets when the form has silently
       sent none of them. */
    var val = function (id) {
      var el = document.getElementById(id);
      return el ? el.value : '';
    };

    KCCA.post(KCCA.api('api/stall_request.php'), {
      action: 'request',
      space_id: val('req-space-id'),
      /* Only sent when the form actually asks for it. A vendor's identity
         travels as vendor_id, so the request is matched to their existing
         record rather than to a phone number just typed by hand. */
      vendor_id: val('req-vendor-id'),
      full_name: val('req-name'),
      phone: val('req-phone'),
      national_id: val('req-nid'),
      business_category: val('req-trade'),
      trade_detail: val('req-detail'),
      reason: val('req-reason')
    }).then(function (res) {
      btn.disabled = false; btn.textContent = 'Send request';
      if (res && res.ok) {
        document.getElementById('request-back').hidden = true;
        document.body.style.overflow = '';
        KCCA.flash(res.message, 'ok', 9000);
        loadStalls();
      } else if (res && res.errors) {
        /* Name the field, not just "details are missing". A trader told
           which line to look at can fix it; one told a category cannot. */
        var list = Object.keys(res.errors).map(function (k) {
          return res.errors[k];
        });
        KCCA.flash(res.error + ' ' + list.join(' '), 'bad', 9000);
        Object.keys(res.errors).forEach(function (k) {
          var el = document.querySelector('[name=' + k + ']');
          if (el) el.classList.add('invalid');
        });
      } else {
        KCCA.flash((res && res.error) || 'The request could not be sent.', 'bad', 9000);
      }
    });
  });

  /* ---- filters ---- */
  document.querySelectorAll('[data-stall-filter]').forEach(function (el) {
    el.addEventListener('change', function () { loadStalls(); });
  });

  /* ---- map ---- */
  loadStalls().then(function (data) {
    KCCAStallMap.init('stallmap', { data: { stalls: data.stalls, market: data.market.name } });
  });

  document.addEventListener('stall:selected', function (e) { selectStall(e.detail); });
})();
</script>
HTML;
?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>