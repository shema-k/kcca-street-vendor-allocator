<?php
/**
 * Workwear and trader support.
 *
 * What the Authority hands a trader: the prescribed garment for their trade,
 * and everything else — a net, a ration, a dustbin, a scale. Two views of the
 * same idea, and the split is the useful part.
 *
 * The rule is one prescribed set of workwear per trade, with its colour, its
 * price, and a statement of whose hand supplies it. In a public market that is
 * the Authority's; in a private market it is the chairman's, because the
 * Authority has nobody standing in that market to hand anything over. Written
 * once, per trade, rather than decided again at every handover.
 *
 * The evidence is every handover, listed with whether the trader has confirmed
 * it, and a follow-up view of the ones that have not. That distinction is why
 * the table exists: "we distributed" and "they received" are different claims,
 * and a register that cannot tell them apart will report the first and mean
 * the second.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$mine = chairmanLock();
$isChairman = $mine !== null;

if (!$isChairman && !Auth::can('report')) {
    Auth::requirePermission('report');
}

/* A chairman's reach is their own market, applied to the query rather than to
   the rows afterwards, so the counts at the top and the table beneath them can
   never disagree. */
$marketId = $isChairman ? (int) $mine['market_id'] : (int) ($_GET['market'] ?? 0);

$view   = in_array($_GET['view'] ?? '', ['outstanding', 'overdue', 'due'], true) ? (string) $_GET['view'] : 'outstanding';
$counts = TraderSupport::counts($isChairman ? $marketId : null);
$rows   = TraderSupport::outstanding($isChairman ? $marketId : null, $view);

$attire = Database::all(
    'SELECT ta.*, tc.name AS trade_name
       FROM trade_attire ta
  LEFT JOIN trade_categories tc ON tc.code = ta.trade_code
  ORDER BY tc.name'
);

/* Who still has no prescribed workwear. The gap list, because "who has not
   been given their apron" is the question worth answering; "who has" is not
   a question anybody asks. */
$gaps = $marketId > 0 ? TraderSupport::attireGap($marketId) : [];

$markets = $isChairman ? [] : Database::all(
    'SELECT m.id, m.name, d.name AS division_name
       FROM markets m JOIN divisions d ON d.id = m.division_id
      ORDER BY d.name, m.name'
);

$items = Database::all('SELECT * FROM support_items ORDER BY category, name');

/* Traders to hand things to: those with a chairman, since a trader with none
   has no market for the item to belong to. */
$traders = $marketId > 0
    ? Database::all(
        'SELECT v.id, v.vendor_code, v.full_name FROM vendors v
          WHERE v.chairman_id IN (SELECT id FROM market_chairmen WHERE market_id = ?)
          ORDER BY v.full_name LIMIT 400',
        [$marketId]
    )
    : Database::all(
        'SELECT v.id, v.vendor_code, v.full_name FROM vendors v
          WHERE v.chairman_id IS NOT NULL ORDER BY v.full_name LIMIT 400'
    );

$pageTitle      = 'Workwear & Support';
$canEditAttire  = Auth::can('manage');
require __DIR__ . '/../layouts/header.php';
?>

<section class="page-head">
  <div>
    <h1><?= e($pageTitle) ?></h1>
    <p class="sub">
      Workwear is prescribed by trade — a red apron reads as a butcher and a white one as a
      cook, from across a market. The Authority issues it in a public market; the chairman
      issues it in a private one, where the Authority has nobody on site.
    </p>
  </div>
</section>

<section class="rd-stats">
  <div class="rd-stat"><span class="num"><?= num($counts['issued']) ?></span><span>Handed over</span></div>
  <div class="rd-stat"><span class="num"><?= num($counts['received']) ?></span><span>Confirmed received</span></div>
  <div class="rd-stat"><span class="num"><?= num($counts['outstanding']) ?></span><span>Awaiting confirmation</span></div>
  <div class="rd-stat"><span class="num"><?= num($counts['overdue']) ?></span><span>Past follow-up</span></div>
</section>

<?php if (!$isChairman): ?>
  <form method="get" class="filters">
    <label class="sr-only" for="market">Market</label>
    <select name="market" id="market">
      <option value="">Every market</option>
      <?php foreach ($markets as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $marketId === (int) $m['id'] ? ' selected' : '' ?>>
          <?= e($m['division_name']) ?> — <?= e($m['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost btn-sm">Apply</button>
  </form>
<?php endif; ?>

<div class="grid g2">

  <!-- ============================ FOLLOW-UP ============================ -->
  <section class="card">
    <div class="card-head">
      <h2>Awaiting confirmation</h2>
      <span class="chips">
        <?php foreach (['outstanding' => 'All', 'overdue' => 'Past due', 'due' => 'Still due'] as $k => $label): ?>
          <a class="chip<?= $view === $k ? ' on' : '' ?>" href="<?= e(urlWith(['view' => $k])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </span>
    </div>

    <div class="card-body">
    <?php if ($rows === []): ?>
      <p class="empty"><?= $counts['issued'] === 0
          ? 'Nothing has been handed over yet.'
          : 'Every handover in this scope has been confirmed.' ?></p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Reference</th><th>Item</th><th>Trader</th>
            <th>Given by</th><th>Issued</th><th class="nowrap">Waiting</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr<?= !empty($r['overdue']) ? ' class="row-alert"' : '' ?>>
            <td><code><?= e($r['reference']) ?></code></td>
            <td>
              <?= e($r['item_name']) ?>
              <?php if ((int) $r['quantity'] > 1): ?>
                <span class="muted">×<?= num($r['quantity']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?= e($r['full_name']) ?>
              <div class="xsmall muted"><?= e($r['market_name'] ?? 'Not placed') ?></div>
            </td>
            <td class="xsmall"><?= e($r['chairman_name'] ?? $r['officer_name'] ?? '—') ?></td>
            <td class="xsmall nowrap"><?= e(fmtDate($r['issued_on'])) ?></td>
            <td class="nowrap">
              <?= num((int) $r['days_waiting']) ?>d
              <?php if (!empty($r['overdue'])): ?>
                <div class="xsmall tint-red">past follow-up</div>
              <?php endif; ?>
            </td>
            <td class="right nowrap">
              <button class="btn btn-sm" data-support="confirm" data-id="<?= (int) $r['id'] ?>">
                Mark received
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
    </div>
  </section>

  <!-- ============================ THE RULE ============================ -->
  <section class="card">
    <div class="card-head">
      <h2>Prescribed by trade</h2>
      <span class="xsmall muted"><?= num(count($attire)) ?> trades</span>
    </div>

    <div class="card-body">
      <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Trade</th><th>Garment</th><th>Colour</th><th class="nowrap">Price</th><th>Issued by</th></tr>
        </thead>
        <tbody>
        <?php foreach ($attire as $a): ?>
          <tr>
            <td><?= e($a['trade_name'] ?? $a['trade_code']) ?></td>
            <td class="xsmall"><?= e($a['garment']) ?></td>
            <td class="nowrap">
              <span class="swatch" style="background:<?= e($a['colour_hex']) ?>"></span>
              <?= e($a['colour']) ?>
            </td>
            <td class="nowrap"><?= e(ugxShort((int) $a['unit_price_ugx'])) ?></td>
            <td class="xsmall"><?= $a['issued_by'] === 'kcca'
                ? '<span class="badge">KCCA</span>'
                : '<span class="badge muted">Chairman</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>

    <div class="card-foot">
      <b>KCCA</b> issues in a public market. <b>Chairman</b> issues in a private market,
      where the Authority has nobody on site to hand it over. The market decides which,
      not the trade.
    </div>
  </section>
</div>

<?php if ($gaps !== []): ?>
  <section class="card">
    <div class="card-head">
      <h2>Traders without their prescribed workwear</h2>
      <span class="xsmall muted"><?= num(count($gaps)) ?> here</span>
    </div>
    <div class="card-body">
      <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Trader</th><th>Trade</th><th>Prescribed</th><th class="nowrap">Price</th></tr>
        </thead>
        <tbody>
        <?php foreach ($gaps as $g): ?>
          <tr>
            <td><?= e($g['full_name']) ?> <span class="xsmall muted"><?= e($g['vendor_code']) ?></span></td>
            <td class="xsmall"><?= e($g['trade']) ?></td>
            <td class="xsmall nowrap">
              <span class="swatch" style="background:<?= e($g['colour_hex']) ?>"></span>
              <?= e($g['garment']) ?>
            </td>
            <td class="nowrap"><?= e(ugxShort((int) $g['unit_price_ugx'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- ============================ RECORD A HANDOVER ============================ -->
<section class="card">
  <div class="card-head">
    <h2>Record a handover</h2>
    <span class="xsmall muted"><?= $isChairman ? 'To a trader in your market' : 'On behalf of the Authority' ?></span>
  </div>
  <div class="card-body">
    <form id="support-form">
      <input type="hidden" name="action" value="issue">
      <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

      <div class="grid g4">
        <div class="field">
          <label for="s-vendor">Trader</label>
          <select name="vendor_id" id="s-vendor" required>
            <option value="">Choose a trader</option>
            <?php foreach ($traders as $t): ?>
              <option value="<?= (int) $t['id'] ?>"><?= e($t['full_name']) ?> — <?= e($t['vendor_code']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="s-item">Item</label>
          <select name="item_id" id="s-item" required>
            <option value="">Choose an item</option>
            <?php foreach ($items as $i): ?>
              <option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?> — <?= e(ugxShort((int) $i['unit_price_ugx'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="s-qty">Quantity</label>
          <input type="number" name="quantity" id="s-qty" value="1" min="1" max="999">
        </div>

        <div class="field">
          <label for="s-date">Issued on</label>
          <input type="date" name="issued_on" id="s-date" value="<?= e(date('Y-m-d')) ?>">
        </div>

        <div class="field">
          <label for="s-size">Size</label>
          <input type="text" name="size" id="s-size" placeholder="Optional">
        </div>

        <div class="field">
          <label for="s-follow">Follow up on</label>
          <input type="date" name="follow_up_on" id="s-follow" value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>">
        </div>

        <div class="field g-2-1">
          <label for="s-notes">Note</label>
          <input type="text" name="notes" id="s-notes" placeholder="Optional">
        </div>
      </div>

      <div class="card-foot right">
        <button type="submit" class="btn btn-primary">Record handover</button>
      </div>
    </form>
  </div>
</section>

<?php if ($canEditAttire): ?>
  <section class="card">
    <div class="card-head"><h2>Set the prescribed workwear</h2></div>
    <div class="card-body">
      <form id="attire-form">
        <input type="hidden" name="action" value="save-attire">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

        <div class="grid g4">
          <div class="field">
            <label for="a-trade">Trade</label>
            <select name="trade_code" id="a-trade" required>
              <option value="">Choose a trade</option>
              <?php foreach (Database::all('SELECT code, name FROM trade_categories ORDER BY name') as $tc): ?>
                <option value="<?= e($tc['code']) ?>"><?= e($tc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label for="a-garment">Garment</label>
            <input type="text" name="garment" id="a-garment" required placeholder="Butcher's apron">
          </div>

          <div class="field">
            <label for="a-colour">Colour</label>
            <input type="text" name="colour" id="a-colour" required placeholder="Red">
          </div>

          <div class="field">
            <label for="a-hex">Colour hex</label>
            <input type="text" name="colour_hex" id="a-hex" value="#DC351D"
                   pattern="#[0-9A-Fa-f]{6}" required>
            <span class="xsmall muted">Shown as a swatch beside the trade.</span>
          </div>

          <div class="field">
            <label for="a-price">Price (UGX)</label>
            <input type="number" name="unit_price_ugx" id="a-price" value="0" min="0" step="500">
          </div>

          <div class="field">
            <label for="a-by">Issued by</label>
            <select name="issued_by" id="a-by">
              <option value="kcca">KCCA</option>
              <option value="chairman">The market chairman</option>
            </select>
            <span class="xsmall muted">A private market is the chairman's, whatever this says.</span>
          </div>

          <div class="field">
            <label for="a-sizes">Sizes</label>
            <input type="text" name="sizes" id="a-sizes" placeholder="S–XXL">
          </div>

          <div class="field">
            <label for="a-notes">Note</label>
            <input type="text" name="notes" id="a-notes">
          </div>
        </div>

        <div class="card-foot right">
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </section>
<?php endif; ?>

<script>
/* Both forms reload on success. A handover that appears only after the page has
   come back cannot disagree with the table above it — which is the whole reason
   received_at is the only thing that closes an issue. */
function postSupport(form) {
  return KCCA.post('support_action.php', new FormData(form)).then(function (res) {
    KCCA.flash(res.message || res.error || '', res.ok ? 'ok' : 'bad');
    return res;
  });
}

document.getElementById('support-form')?.addEventListener('submit', function (ev) {
  ev.preventDefault();
  postSupport(ev.target).then(function (res) {
    if (res.ok) setTimeout(function () { location.reload(); }, 700);
  });
});

document.getElementById('attire-form')?.addEventListener('submit', function (ev) {
  ev.preventDefault();
  postSupport(ev.target).then(function (res) {
    if (res.ok) setTimeout(function () { location.reload(); }, 700);
  });
});

document.querySelectorAll('[data-support="confirm"]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    btn.disabled = true;
    KCCA.post('support_action.php', { action: 'confirm', issue_id: btn.dataset.id })
      .then(function (res) {
        KCCA.flash(res.message || res.error || '', res.ok ? 'ok' : 'bad');
        if (res.ok) { setTimeout(function () { location.reload(); }, 700); }
        else { btn.disabled = false; }
      });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>