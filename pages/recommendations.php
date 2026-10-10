<?php
/**
 * AI Recommendations — the reach and trade viability engine, with the
 * approval workflow over its output.
 *
 * The screen's job is to make a model output arguable: every number is
 * shown with the factor that produced it, every recommendation carries a
 * confidence figure, and an officer can approve, reject or override it —
 * with the reason recorded either way.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f            = facts();
$status       = (string) ($_GET['status'] ?? 'proposed');
$vendorId     = (int) ($_GET['vendor'] ?? 0);
$locationId   = (int) ($_GET['location'] ?? 0);
$division     = (string) ($_GET['division'] ?? '');

$divisionLock = divisionLock();
if ($divisionLock !== null) {
    $division = $divisionLock['code'];
}

/* ---------------- Recommendation register ---------------- */
$where  = ['1 = 1'];
$params = [];
if ($status !== '') { $where[] = 'r.status = :st'; $params['st'] = $status; }
if ($vendorId > 0) { $where[] = 'r.vendor_id = :vid'; $params['vid'] = $vendorId; }
if ($division !== '') { $where[] = 'd.code = :div'; $params['div'] = $division; }
if ($locationId > 0) { $where[] = 'r.market_id = :mid'; $params['mid'] = $locationId; }

$whereSql = implode(' AND ', $where);

$recs = Database::all(
    "SELECT r.*, v.full_name, v.vendor_code, v.business_category, v.monthly_turnover_ugx,
            d.name AS division_name,
            m.name AS market_name, m.category AS market_category, m.vacant_spaces,
            u.full_name AS decided_by_name
       FROM recommendations r
       JOIN vendors v ON v.id = r.vendor_id
       JOIN markets m ON m.id = r.market_id
       JOIN divisions d ON d.id = v.division_id
       LEFT JOIN users u ON u.id = r.decided_by
      WHERE $whereSql
      ORDER BY FIELD(r.status, 'proposed', 'approved', 'allocated', 'rejected', 'superseded', 'expired'),
               r.reach_score DESC, r.created_at DESC
      LIMIT 120",
    $params
);

$counts = [];
foreach (Database::all('SELECT status, COUNT(*) AS n FROM recommendations GROUP BY status') as $c) {
    $counts[$c['status']] = (int) $c['n'];
}

/* ---------------- What the model would say about one vendor ---------------- */
$liveRanking = null;
if ($vendorId > 0) {
    $vendor = vendorForMatching($vendorId);
    if ($vendor) {
        $markets = RecommendationService::candidateMarkets();
        if ($division !== '') {
            $markets = array_values(array_filter($markets, static fn($m) => $m['div_code'] === $division));
        }
        $limit  = (int) Settings::int('max_recommendations_per_vendor', 5);
        $liveRanking = [
            'vendor' => $vendor,
            'options' => RecommendationService::rank($vendor, $markets, $limit),
        ];
    }
}

/* ---------------- One recommendation in full ---------------- */
$openRec = null;
$openRecId = (int) ($_GET['rec'] ?? 0);
if ($openRecId > 0) {
    $openRec = Database::one(
        'SELECT r.*, v.full_name, v.vendor_code, v.business_category, v.monthly_turnover_ugx,
                d.name AS division_name, m.name AS market_name, m.lat AS market_lat, m.lng AS market_lng,
                m.vacant_spaces, u.full_name AS decided_by_name
           FROM recommendations r
           JOIN vendors v ON v.id = r.vendor_id
           JOIN markets m ON m.id = r.market_id
           JOIN divisions d ON d.id = v.division_id
           LEFT JOIN users u ON u.id = r.decided_by
          WHERE r.id = ? LIMIT 1',
        [$openRecId]
    );
    if ($openRec) {
        $openRec['factors']  = json_decode((string) $openRec['factors_json'], true) ?: [];
        $openRec['warnings'] = json_decode((string) $openRec['warnings_json'], true) ?: [];
        $openRec['spaces']   = Database::all(
            "SELECT id, space_code, section, size_sqm, step_free, has_water, has_power, near_toilet
               FROM market_spaces
              WHERE market_id = ? AND status = 'vacant'
              ORDER BY step_free DESC, has_water DESC, near_toilet DESC, space_code
              LIMIT 12",
            [(int) $openRec['market_id']]
        );
    }
}

/* ---------------- Charts ---------------- */
$scoreDistribution = [];
foreach ([['90–100',90,101],['80–89',80,90],['70–79',70,80],['60–69',60,70],['40–59',40,60],['Under 40',0,40]] as $b) {
    $scoreDistribution[] = [
        'label' => $b[0],
        'value' => (int) Database::value(
            'SELECT COUNT(*) FROM recommendations WHERE reach_score >= ? AND reach_score < ?', [$b[1], $b[2]]),
    ];
}

$byMarket = Database::all(
    "SELECT m.name, COUNT(*) AS n, ROUND(AVG(r.reach_score),1) AS avg_score
       FROM recommendations r JOIN markets m ON m.id = r.market_id
      WHERE r.status IN ('proposed','approved','allocated')
      GROUP BY m.id ORDER BY n DESC LIMIT 8"
);

$modelStats = Database::one(
    "SELECT COUNT(*) AS total,
            ROUND(AVG(reach_score),1) AS avg_score,
            ROUND(AVG(confidence)*100,0) AS avg_confidence,
            ROUND(AVG(projected_customers_day),1) AS avg_customers,
            SUM(status = 'approved') AS approved,
            SUM(status = 'rejected') AS rejected
       FROM recommendations"
);

/* Locations the model has excluded on land-use grounds, for transparency. */
$blockedListings = Database::all(
    "SELECT DISTINCT r.market_id, m.name, lr.title, lr.description, lr.excluded_categories
       FROM recommendations r
       JOIN markets m ON m.id = r.market_id
       JOIN land_use_restrictions lr ON lr.market_id = m.id AND lr.is_active = 1
      WHERE r.status = 'rejected' AND lr.excluded_categories LIKE CONCAT('%', r.vendor_id, '%')
      LIMIT 5"
);

$pageTitle = 'AI Recommendations';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>AI Recommendations
    </div>
    <div class="eyebrow">Reach &amp; trade viability model <?= e(ReachModel::VERSION) ?></div>
    <h1>AI Recommendations</h1>
    <p>
      How many customers each trader could reach at every location with space, and how confident the
      model is. Every recommendation can be approved or rejected with a recorded reason.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="locations.php?view=footfall"><?= KCCA_icon('chart', 15) ?> Footfall counts</a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ======================= MODEL CARD =========================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>How the model reaches its answer</h2>
      <p class="desc">
        Eight factors, each scored 0–100 and combined as a weighted average. The weights are policy
        settings, editable in <a href="settings.php">Settings</a>.
      </p>
    </div>
    <span class="badge green">Model <?= e(ReachModel::VERSION) ?></span>
  </div>
  <div class="card-body">
    <div class="grid g4">
      <?php
      $what = [
          'footfall'        => 'How many people pass through the location at all.',
          'facility_fit'    => 'Whether the location can properly support this trade.',
          'category_demand' => 'How reliably this product draws buyers in Kampala.',
          'headroom'        => 'Whether the market has room left to take another trader.',
          'competition'     => 'How many vendors there already trade the same thing.',
          'rent'            => 'Whether the trader can afford the pitch.',
          'accessibility'   => 'How easy the location is to reach, from division density.',
          'land_use'        => 'Free of any statutory restriction on that trade.',
      ];
      foreach (ReachModel::WEIGHTS as $key => $weight): ?>
        <div class="criterion">
          <span class="cl"><?= e(ucwords(str_replace('_', ' ', $key))) ?></span>
          <span class="cv"><span class="badge brand"><?= pct($weight * 100, 0) ?></span></span>
          <span class="cn"><?= e($what[$key] ?? '') ?></span>
          <span class="track"><span class="fill" style="width:<?= (float) round($weight * 100 * 2.5, 0) ?>%"></span></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="grid g4 mt-2">
      <div class="stat">
        <div class="label">Recommendations generated</div>
        <div class="value"><?= num((int) ($modelStats['total'] ?? 0)) ?></div>
      </div>
      <div class="stat info">
        <div class="label">Average reach score</div>
        <div class="value"><?= num((float) ($modelStats['avg_score'] ?? 0), 1) ?></div>
        <div class="meta">out of 100</div>
      </div>
      <div class="stat <?= (float) ($modelStats['avg_confidence'] ?? 0) >= 50 ? 'ok' : 'warn' ?>">
        <div class="label">Average confidence</div>
        <div class="value"><?= num((float) ($modelStats['avg_confidence'] ?? 0), 0) ?>%</div>
      </div>
      <div class="stat gold">
        <div class="label">Projected customers</div>
        <div class="value"><?= num((float) ($modelStats['avg_customers'] ?? 0), 0) ?></div>
        <div class="meta">per stall, per day</div>
      </div>
    </div>

    <div class="source mt-2">
      <span class="lbl">note</span>
      <span>
        This is a transparent analytical model, not a black box: every score decomposes into the eight
        factors above, and the reasoning is stored with the recommendation. It is calibrated on counted
        Kampala footfall and should be read alongside a survey — never instead of one.
      </span>
    </div>
  </div>
</div>

<!-- ================== LIVE RANKING FOR ONE VENDOR ================ -->
<?php if ($liveRanking):
    $v = $liveRanking['vendor'];
?>
<div class="card" id="live-ranking">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="recommendations.php">Recommendations</a><span class="sep">/</span><?= e($v['vendor_code']) ?>
      </div>
      <h2><?= e($v['full_name']) ?></h2>
      <p class="desc">
        <?= e(tradeDescription($v['business_category'])) ?> ·
        <?= e($v['division_name']) ?> Division ·
        currently trading at <?= e($v['zone_name'] ?: 'no recorded site') ?> ·
        turnover <?= ugxShort((int) $v['monthly_turnover_ugx']) ?> a month
      </p>
    </div>
    <div class="tools">
      <span class="badge <?= e(statusTone($v['status'])) ?>"><?= e(humanise($v['status'])) ?></span>
      <?php if (Auth::can('allocate')): ?>
        <button class="btn btn-primary btn-sm" data-generate-rec="<?= (int) $v['id'] ?>">
          Generate recommendations
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body">
    <?php if (!$liveRanking['options']): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('alert', 30) ?></div>
        <h3>No location can be recommended</h3>
        <p>Every market is full, or a land-use restriction blocks <?= e(humanise($v['business_category'])) ?>
           everywhere the Authority has space.</p>
      </div>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($liveRanking['options'] as $o):
            $s = $o['score'];
            $warn = $s['warnings'];
        ?>
          <div class="card" style="margin:0">
            <div class="card-head">
              <div class="grow">
                <div class="row-tight">
                  <span class="rank <?= $o['rank'] === 1 ? 'top' : '' ?>"><?= (int) $o['rank'] ?></span>
                  <strong><?= e($o['market']['name']) ?></strong>
                  <span class="badge <?= $o['market']['category'] === 'public' ? 'green' : 'gold' ?>">
                    <?= e($o['market']['category']) ?>
                  </span>
                  <?php if ((int) $o['market']['vacant_spaces'] > 0): ?>
                    <span class="badge muted"><?= num((int) $o['market']['vacant_spaces']) ?> vacant</span>
                  <?php endif; ?>
                </div>
                <p class="desc">
                  <?= e($o['market']['division_name']) ?> Division ·
                  <?= num($o['distance'], 1) ?> km from the current pitch ·
                  reach <b><?= num($s['reach_score'], 1) ?></b> ·
                  viab<b>ility <?= num($s['viability_score'], 1) ?></b> ·
                  confidence <b><?= pct($s['confidence'] * 100, 0) ?></b>
                </p>
              </div>
              <div class="tools">
                <span class="badge <?= $s['reach_score'] >= 75 ? 'ok' : ($s['reach_score'] >= 55 ? 'warn' : 'bad') ?>">
                  <?= num($s['reach_score'], 1) ?> / 100
                </span>
              </div>
            </div>

            <div class="card-body">
              <div class="grid g4 mb-2">
                <div class="stat info">
                  <div class="label">Projected customers</div>
                  <div class="value" style="font-size:19px"><?= num($s['projected_customers_day'], 0) ?></div>
                  <div class="meta">per day</div>
                </div>
                <div class="stat gold">
                  <div class="label">Projected revenue</div>
                  <div class="value" style="font-size:17px"><?= ugxShort((float) $s['projected_revenue_ugx']) ?></div>
                  <div class="meta">a month</div>
                </div>
                <div class="stat <?= $s['confidence'] >= 0.5 ? 'ok' : 'warn' ?>">
                  <div class="label">Confidence</div>
                  <div class="value" style="font-size:19px"><?= pct($s['confidence'] * 100, 0) ?></div>
                  <div class="meta"><?= $s['footfall_profile']['observations'] > 0 ? 'counted' : 'estimated' ?></div>
                </div>
                <div class="stat">
                  <div class="label">Distance</div>
                  <div class="value" style="font-size:19px"><?= num($o['distance'], 1) ?></div>
                  <div class="meta">kilometres</div>
                </div>
              </div>

              <div class="quote" style="margin-bottom:14px">
                <p><?= e($s['explanation']) ?></p>
                <footer>Model <?= e($s['model_version']) ?> · <?= e($s['footfall_profile']['basis']) ?></footer>
              </div>

              <?php if ($warn): ?>
                <div class="alert alert-warn">
                  <?php foreach ($warn as $w): ?>
                    <div><?= e($w) ?></div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <details>
                <summary style="cursor:pointer;font-size:12.5px;font-weight:600;color:var(--kcca-red)">
                  Show all eight factors
                </summary>
                <div class="criteria mt-1">
                  <?php foreach ($s['factors'] as $key => $fac):
                      $tone = $fac['raw'] >= 70 ? 'ok' : ($fac['raw'] >= 45 ? 'warn' : ($fac['raw'] >= 28 ? 'info' : 'bad'));
                  ?>
                    <div class="criterion">
                      <span class="cl"><?= e($fac['label']) ?></span>
                      <span class="cv">
                        <?= num($fac['raw'], 0) ?><span class="muted small"> / 100 · <?= pct(ReachModel::WEIGHTS[$key] * 100, 0) ?></span>
                      </span>
                      <span class="cn"><?= e($fac['note']) ?></span>
                      <span class="track"><span class="fill <?= $tone === 'bad' ? '' : $tone ?>"
                            style="width:<?= (float) clamp($fac['raw'], 1, 100) ?>%;
                                   background:<?= ['ok'=>'#1B7F4D','warn'=>'#EAA515','info'=>'#1D6FA5','bad'=>'#C0261A'][$tone] ?>"></span></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ==================== ONE RECOMMENDATION ======================= -->
<?php if ($openRec):
    $factors  = $openRec['factors'];
    $warns    = $openRec['warnings'];
    $decidable = $openRec['status'] === 'proposed';
?>
<div class="card" id="rec-detail">
  <div class="card-head">
    <div class="grow">
      <div class="breadcrumb" style="margin-bottom:4px">
        <a href="recommendations.php">Recommendations</a><span class="sep">/</span><?= e($openRec['reference']) ?>
      </div>
      <h2><?= e($openRec['full_name']) ?> → <?= e($openRec['market_name']) ?></h2>
      <p class="desc">
        <?= e(humanise($openRec['business_category'])) ?> ·
        rank <?= (int) $openRec['rank'] ?> ·
        <?= num((float) $openRec['distance_km'], 1) ?> km ·
        generated <?= e(fmtDate($openRec['created_at'])) ?> ·
        <?= e($openRec['model_version']) ?>
      </p>
    </div>
    <div class="tools">
      <span class="badge <?= e(statusTone($openRec['status'])) ?>"><?= e(humanise($openRec['status'])) ?></span>
      <a class="btn btn-quiet btn-sm" href="recommendations.php?status=<?= e($status) ?>">Close</a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($openRec['status'] !== 'proposed'): ?>
      <div class="alert alert-info">
        <strong><?= e(humanise($openRec['status'])) ?> by
        <?= e($openRec['decided_by_name'] ?: 'the Authority') ?>.</strong>
        <?= e(fmtDate($openRec['decided_at'])) ?>
        <?php if ($openRec['decision_note']): ?>
          <div class="mt-1">Reason: <?= e($openRec['decision_note']) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="grid g-2-1">
      <div>
        <div class="grid g4 mb-2">
          <div class="stat <?= (float) $openRec['reach_score'] >= 75 ? 'ok' : 'warn' ?>">
            <div class="label">Reach score</div>
            <div class="value"><?= num((float) $openRec['reach_score'], 1) ?></div>
            <div class="meta">out of 100</div>
          </div>
          <div class="stat info">
            <div class="label">Viability</div>
            <div class="value"><?= num((float) $openRec['viability_score'], 1) ?></div>
            <div class="meta">can this be a living?</div>
          </div>
          <div class="stat gold">
            <div class="label">Projected</div>
            <div class="value" style="font-size:18px"><?= num((float) $openRec['projected_customers_day'], 0) ?></div>
            <div class="meta">customers a day</div>
          </div>
          <div class="stat <?= (float) $openRec['confidence'] >= 0.5 ? 'ok' : 'warn' ?>">
            <div class="label">Confidence</div>
            <div class="value"><?= pct((float) $openRec['confidence'] * 100, 0) ?></div>
            <div class="meta">in the estimate</div>
          </div>
        </div>

        <div class="quote mb-2">
          <p><?= e($openRec['explanation']) ?></p>
          <footer>
            <b><?= e(ReachModel::VERSION) ?></b> ·
            <?= num((int) $openRec['projected_customers_day'], 0) ?> customers a day ·
            <?= ugxShort((float) $openRec['projected_revenue_ugx']) ?> a month projected
          </footer>
        </div>

        <?php if ($warns): ?>
          <div class="alert alert-warn">
            <?php foreach ($warns as $w): ?><div><?= e($w) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <h3 class="mb-1">Every factor, with its weight</h3>
        <div class="criteria">
          <?php foreach ($factors as $key => $fac):
              $tone = $fac['raw'] >= 70 ? 'ok' : ($fac['raw'] >= 45 ? 'warn' : ($fac['raw'] >= 28 ? 'info' : 'bad'));
          ?>
            <div class="criterion">
              <span class="cl"><?= e($fac['label']) ?></span>
              <span class="cv">
                <?= num($fac['raw'], 0) ?><span class="muted small"> / 100 · weight <?= pct(ReachModel::WEIGHTS[$key] * 100, 0) ?></span>
              </span>
              <span class="cn"><?= e($fac['note']) ?></span>
              <span class="track"><span class="fill <?= $tone === 'bad' ? '' : $tone ?>"
                    style="width:<?= (float) clamp($fac['raw'], 1, 100) ?>%;
                           background:<?= ['ok'=>'#1B7F4D','warn'=>'#EAA515','info'=>'#1D6FA5','bad'=>'#C0261A'][$tone] ?>"></span></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <?php if ($decidable && Auth::can('allocate')): ?>
          <div class="card" style="margin:0">
            <div class="card-head">
              <div class="grow"><h3>Decision</h3></div>
            </div>
            <div class="card-body">
              <div class="field">
                <label for="space">Pitch to allocate</label>
                <?php if ($openRec['spaces']): ?>
                  <select id="space" data-approve-space>
                    <option value="">Assign on arrival — no specific pitch</option>
                    <?php foreach ($openRec['spaces'] as $s): ?>
                      <option value="<?= (int) $s['id'] ?>">
                        <?= e($s['space_code']) ?> · <?= e($s['section']) ?>
                        <?php if ($s['step_free']): ?> · step-free<?php endif; ?>
                        <?php if ($s['has_water']): ?> · water<?php endif; ?>
                        <?php if ($s['has_power']): ?> · power<?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="hint">
                    <?= num(count($openRec['spaces'])) ?> pitches shown.
                    A pitch can only be allocated once — the database refuses a second booking.
                  </span>
                <?php else: ?>
                  <input type="hidden" data-approve-space value="">
                  <span class="hint">No individual pitches are registered here.</span>
                <?php endif; ?>
              </div>

              <div class="field">
                <label for="note">Note</label>
                <textarea id="note" rows="2" data-approve-note
                          placeholder="Recorded against the approval"></textarea>
              </div>

              <div class="row-tight">
                <button class="btn btn-green btn-sm" data-approve="<?= (int) $openRec['id'] ?>">
                  <?= KCCA_icon('check', 13) ?> Approve
                </button>
                <button class="btn btn-ghost btn-sm" data-reject="<?= (int) $openRec['id'] ?>">Reject</button>
              </div>
            </div>
          </div>
        <?php elseif (!$decidable): ?>
          <div class="card" style="margin:0">
            <div class="card-head"><div class="grow"><h3>History</h3></div></div>
            <div class="card-body">
              <?php
              $history = Database::all(
                  'SELECT e.*, m.name AS market_name FROM relocation_events e
                     LEFT JOIN markets m ON m.id = e.market_id
                    WHERE e.vendor_id = ? ORDER BY e.occurred_at DESC, e.id DESC LIMIT 12',
                  [(int) $openRec['vendor_id']]
              );
              ?>
              <?php if (!$history): ?>
                <p class="muted small mb-0">No relocation events recorded for this trader yet.</p>
              <?php else: ?>
                <ul class="timeline">
                  <?php foreach ($history as $h): ?>
                    <li class="<?= strtotime($h['occurred_at']) < time() ? 'past' : '' ?>">
                      <span class="when"><?= e(fmtDate($h['occurred_at'], 'd M Y H:i')) ?></span>
                      <strong><?= e(humanise($h['event_type'])) ?></strong>
                      <?php if ($h['note']): ?> — <?= e(truncateText($h['note'], 70)) ?><?php endif; ?>
                      <div class="xsmall muted"><?= e($h['actor']) ?></div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="card" style="margin-top:14px">
          <div class="card-head"><div class="grow"><h3>The location</h3></div></div>
          <div class="card-body">
            <dl class="kv">
              <dt>Market</dt><dd style="text-align:right;max-width:170px"><?= e($openRec['market_name']) ?></dd>
              <dt>Division</dt><dd><?= e($openRec['division_name']) ?></dd>
              <dt>Vacant pitches</dt><dd><?= num((int) $openRec['vacant_spaces']) ?></dd>
              <dt>Trader’s turnover</dt><dd><?= ugxShort((int) $openRec['monthly_turnover_ugx']) ?></dd>
              <dt>Distance</dt><dd><?= num((float) $openRec['distance_km'], 1) ?> km</dd>
              <dt>Expires</dt><dd><?= e(fmtDate($openRec['expires_on'])) ?></dd>
              <dt>Generated</dt><dd><?= e(fmtDate($openRec['created_at'])) ?></dd>
            </dl>
            <a class="btn btn-ghost btn-sm btn-block mt-1"
               href="locations.php?location=<?= (int) $openRec['market_id'] ?>">Open the location record</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ==================== REGISTER + CHARTS ======================== -->
<div class="grid g-3-2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Recommendation register</h2>
        <p class="desc"><?= num(count($recs)) ?> record<?= count($recs) === 1 ? '' : 's' ?> in this view.</p>
      </div>
    </div>

    <form class="filters" method="get">
      <div class="field grow">
        <label for="status">Status</label>
        <select name="status" id="status" data-autosubmit>
          <option value="">All statuses</option>
          <?php foreach (['proposed','approved','allocated','rejected','superseded','expired'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
              <?= e(humanise($s)) ?> (<?= num($counts[$s] ?? 0) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php renderDivisionFilter('division', $division); ?>
      <a class="btn btn-ghost" href="recommendations.php">Reset</a>
    </form>

    <div class="card-body tight">
      <div class="table-wrap" style="max-height:560px;overflow-y:auto">
        <table class="data compact">
          <thead>
            <tr>
              <th>Reference</th><th>Trader</th><th>Location</th>
              <th class="right">Reach</th><th class="right">Viability</th>
              <th class="center">Confidence</th><th class="right">Cust/day</th>
              <th class="center">Status</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recs as $r): ?>
              <tr>
                <td class="mono xsmall"><?= e($r['reference']) ?></td>
                <td>
                  <a href="recommendations.php?rec=<?= (int) $r['id'] ?>&status=<?= e($status) ?>">
                    <strong><?= e($r['full_name']) ?></strong>
                  </a>
                  <div class="xsmall muted"><?= e(humanise($r['business_category'])) ?></div>
                </td>
                <td class="xsmall"><?= e($r['market_name']) ?></td>
                <td class="right num strong"><?= num((float) $r['reach_score'], 1) ?></td>
                <td class="right num xsmall"><?= num((float) $r['viability_score'], 1) ?></td>
                <td class="center">
                  <span class="badge <?= (float) $r['confidence'] >= 0.5 ? 'ok' : 'warn' ?>">
                    <?= pct((float) $r['confidence'] * 100, 0) ?>
                  </span>
                </td>
                <td class="right num xsmall"><?= num((float) $r['projected_customers_day'], 0) ?></td>
                <td class="center"><span class="badge <?= e(statusTone($r['status'])) ?>"><?= e(humanise($r['status'])) ?></span></td>
                <td class="right">
                  <a class="btn btn-ghost btn-sm"
                     href="recommendations.php?rec=<?= (int) $r['id'] ?>&status=<?= e($status) ?>">Open</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if (!$recs): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('chart', 30) ?></div>
        <h3>No recommendations here yet</h3>
        <p>Open a vendor from the
           <a href="relocation.php">relocation engine</a> and generate recommendations.</p>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Score distribution</h2></div></div>
      <div class="card-body">
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'bar',
          'opts' => [
            'height' => 200,
            'labels' => array_column($scoreDistribution, 'label'),
            'series' => [['name' => 'Recommendations', 'color' => '#0A6339',
                          'values' => array_column($scoreDistribution, 'value')]],
            'showValues' => true,
            'yFormat' => 'num',
          ],
        ]) ?>' aria-label="Recommendation score distribution"></svg>
      </div>
      <div class="card-foot">
        A healthy spread means the model is discriminating between locations rather than rating
        everything highly.
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Most recommended</h2></div></div>
      <div class="card-body">
        <?php if ($byMarket): ?>
          <svg class="chart" data-chart='<?= ejs([
            'type' => 'hbar',
            'opts' => [
              'labelW' => 160, 'rowH' => 26,
              'items' => array_map(static fn(array $r): array => [
                'label' => $r['name'],
                'value' => (int) $r['n'],
                'color' => '#0A6339',
              ], $byMarket),
            ],
          ]) ?>' aria-label="Most recommended markets"></svg>
        <?php else: ?>
          <p class="muted small mb-0">Nothing has been recommended yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Approvals</h2></div></div>
      <div class="card-body">
        <?php
        $approved = (int) ($modelStats['approved'] ?? 0);
        $rejected = (int) ($modelStats['rejected'] ?? 0);
        $decided  = $approved + $rejected;
        $rate     = $decided > 0 ? round($approved / $decided * 100) : 0;
        ?>
        <div class="grid g2 mb-1">
          <div class="stat ok">
            <div class="label">Approved</div>
            <div class="value"><?= num($approved) ?></div>
          </div>
          <div class="stat bad">
            <div class="label">Rejected</div>
            <div class="value"><?= num($rejected) ?></div>
          </div>
        </div>
        <?php if ($decided > 0): ?>
          <div class="meter">
            <span class="track">
              <span class="fill <?= $rate >= 50 ? 'ok' : 'warn' ?>" style="width:<?= $rate ?>%"></span>
            </span>
            <span class="val"><?= $rate ?>%</span>
          </div>
          <p class="xsmall muted mt-1" style="margin-bottom:0">
            <?= $rate >= 70
                ? 'Officers broadly accept the model. Keep checking the rejected reasons — a pattern there means the weights need retuning.'
                : 'A low acceptance rate usually means the weights are wrong, not that the officers are wrong. Review the rejection reasons.' ?>
          </p>
        <?php else: ?>
          <p class="muted small mb-0">No recommendation has been approved or rejected yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Where the model refuses</h2></div></div>
      <div class="card-body">
        <p class="small muted">
          Land use is a gate, not a weight. Where a restriction applies, the location is excluded from
          the ranking entirely rather than scored down.
        </p>
        <a class="btn btn-ghost btn-sm btn-block" href="locations.php?view=restrictions">
          See the restriction register
        </a>
      </div>
    </div>
  </div>
</div>

<script>
/* ---- Generate recommendations ---- */
document.querySelectorAll('[data-generate-rec]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var vid = btn.getAttribute('data-generate-rec');
    btn.disabled = true;
    btn.textContent = 'Modelling…';
    KCCA.postAction(KCCA.api('api/recommendation_action.php'),
      { action: 'generate', vendor_id: vid },
      { reloadDelay: 1100 });
  });
});

/* ---- Approve ---- */
document.querySelectorAll('[data-approve]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id   = btn.getAttribute('data-approve');
    var sel  = document.querySelector('[data-approve-space]');
    var note = document.querySelector('[data-approve-note]');
    KCCA.postAction(KCCA.api('api/recommendation_action.php'), {
      action: 'recommendation_id' === '' ? '' : 'approve',
      recommendation_id: id,
      market_space_id: sel ? sel.value : '',
      note: note ? note.value : ''
    }, {
      button: btn, busyLabel: 'Allocating…',
      confirm: 'Approve this recommendation and allocate the pitch? The trader will be offered this space.'
    });
  });
});

/* ---- Reject ---- */
document.querySelectorAll('[data-reject]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-reject');
    KCCA.ask(
      'Reject this recommendation',
      'Why is it rejected? The reason is recorded and shown to the trader and to Internal Audit.',
      {
        button: btn, busyLabel: 'Recording…',
        label: 'Reason', maxLength: 500,
        placeholder: 'e.g. this market has no water and the trader sells cooked food'
      }
    ).then(function (reason) {
      if (reason === null) return;
      KCCA.postAction(KCCA.api('api/recommendation_action.php'), {
        action: 'reject', recommendation_id: id, reason: reason
      }, { button: btn, busyLabel: 'Recording…' });
    });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php';