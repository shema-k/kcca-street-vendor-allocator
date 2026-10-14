<?php
/**
 * My market — the single place a signed-in chairman or trader looks to find
 * out what they are attached to.
 *
 * A chairman sees the market they administer and the traders placed in it.
 * A trader sees the market they have been placed in, and the one named
 * person responsible for them there.
 *
 * Both are answered from the same chain rather than from two screens that
 * could disagree: a vendor's record names its chairman, and the chairman
 * names the market. A trader who has not yet been placed is told so plainly,
 * because "no chairman yet" is a fact about their stage rather than an error
 * to be hidden — the traders in the register are in trade zones until the
 * relocation places them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

/* This screen is only meaningful to the two roles that have a "my". Gated on
   the role rather than on 'view', because every role holds 'view' and it
   therefore separates nobody — an officer opening this would be told they
   have no market, which is true but not what the screen is for. */
if (!in_array(Auth::role(), ['chairman', 'vendor'], true)) {
    Auth::requirePermission('view');
    http_response_code(403);
    require __DIR__ . '/../layouts/forbidden.php';
    exit;
}

$mine = chairmanLock();

$me        = Auth::user();
$isTrade   = ($mine === null);
$vendor    = null;
$chairman  = null;
$market    = null;
$allocs    = [];
$requests  = [];
$traders   = [];
$pitches   = [];
$spaces    = [];

/* The workwear, notices and complaints blocks below are read by both halves
   of this screen. Declared here because a trader whose account is not yet
   linked to a vendor record reaches the template with none of them set, and
   an undefined-variable notice on a page a trader reads is worse than an
   empty section. */
$myAttire     = null;
$myIssuer     = null;
$myHandovers  = [];
$myComplaints = [];
$myNotices    = [];

if ($isTrade) {
    $vendorId = (int) ($me['vendor_id'] ?? 0);
    if ($vendorId > 0) {
        $vendor = Database::one(
            'SELECT v.*, z.name AS zone_name, z.code AS zone_code, z.road_or_feature,
                    d.name AS division_name
               FROM vendors v
               LEFT JOIN trade_zones z ON z.id = v.current_zone_id
               LEFT JOIN divisions d ON d.id = v.division_id
              WHERE v.id = ? LIMIT 1',
            [$vendorId]
        );
        if ($vendor) {
            $chairman = chairmanForVendor($vendorId);
            if ($chairman && !empty($chairman['market_id'])) {
                $market = Database::one(
                    'SELECT m.*, d.name AS division_name
                       FROM markets m JOIN divisions d ON d.id = m.division_id
                      WHERE m.id = ? LIMIT 1',
                    [(int) $chairman['market_id']]
                );
            }
            $allocs = Database::all(
                'SELECT a.*, m.name AS market_name, s.space_code, s.monthly_rent_ugx
                   FROM allocations a
                   JOIN markets m ON m.id = a.market_id
                   LEFT JOIN market_spaces s ON s.id = a.market_space_id
                  WHERE a.vendor_id = ? ORDER BY a.created_at DESC',
                [$vendorId]
            );
            $requests = Database::all(
                'SELECT r.*, m.name AS market_name, s.space_code
                   FROM stall_requests r
                   LEFT JOIN markets m ON m.id = r.market_id
                   LEFT JOIN market_spaces s ON s.id = r.space_id
                  WHERE r.vendor_id = ? ORDER BY r.created_at DESC',
                [$vendorId]
            );
        }
    }
    if ($market) {
        $pitches = Database::all(
            "SELECT id, space_code, section, status FROM market_spaces
              WHERE market_id = ? AND status IN ('occupied','reserved')
              ORDER BY space_code LIMIT 12",
            [(int) $market['id']]
        );
    }

    /* A trader's own copy of three things: the workwear prescribed for their
       trade and who supplies it, what the chairman has published to their
       market, and a way to raise a complaint with that chairman. All three are
       the same idea seen from the other end — what the Authority owes them,
       told through the market they are in. */
    if ($vendor) {
        /* One call, not two. The workwear rule and who supplies it are one
           question with one owner, and this PHP build cannot resolve a
           literal TraderSupport::attireFor() call from this file — it reports
           the method present via method_exists() and then throws on the
           call. The same method runs fine from inside the class. */
        $myIssuer    = TraderSupport::issuerFor(
            $vendor['business_category'] ?? null,
            $market['category'] ?? null
        );
        $myAttire    = $myIssuer['rule'] ?? null;
        $myHandovers = TraderSupport::forVendor((int) $vendor['id']);
        $myComplaints = Complaints::forVendor((int) $vendor['id']);

        /* Notices the chairman has actually published here. A notice merely
           sent to the chairman is not shown: the trader's route to it is the
           chairman, and showing them text the chairman has not yet passed on
           would be telling them something they were not told. */
        $myNotices = $chairman ? Notices::forChairman((int) $chairman['id'], 'all') : [];
        $myNotices = array_values(array_filter(
            $myNotices,
            static fn(array $n): bool => !empty($n['forwarded_at'])
        ));
    }
} else {
    $market = Database::one(
        'SELECT m.*, d.name AS division_name
           FROM markets m JOIN divisions d ON d.id = m.division_id
          WHERE m.id = ? LIMIT 1',
        [$mine['market_id']]
    );
    if ($market) {
        $traders = Database::all(
            'SELECT v.id, v.full_name, v.vendor_code, v.business_category, v.status,
                    v.monthly_turnover_ugx, v.phone
               FROM vendors v WHERE v.chairman_id = ? ORDER BY v.full_name LIMIT 50',
            [$mine['id']]
        );
        $spaces = Database::all(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'occupied') AS occupied,
                    SUM(status = 'reserved')  AS reserved,
                    SUM(status = 'vacant')    AS vacant
               FROM market_spaces WHERE market_id = ?",
            [(int) $market['id']]
        )[0] ?? ['total' => 0, 'occupied' => 0, 'reserved' => 0, 'vacant' => 0];

        /* The same three things, seen from the chairman's side: what his
           traders are owed and who is to supply it, what he has still not
           published to them, and what they have raised with him. */
        $myAttire      = null;
        $myIssuer      = null;
        $myHandovers   = [];
        $myComplaints  = Complaints::forChairman((int) $mine['id'], 'open');
        $myNotices     = Notices::forChairman((int) $mine['id'], 'all');
    }
}

$pageTitle = 'My market';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="page-head-actions">
    <?php require __DIR__ . '/../partials/live_watch.php'; ?>
  </div>

  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>My market
    </div>
    <div class="eyebrow">Markets Act 2023, s.21 · Market administration</div>
    <?php if ($isTrade): ?>
      <h1><?= $market ? e($market['name']) : 'Your trading' ?></h1>
      <p>
        <?php if ($chairman && $market): ?>
          Your market, your chairman, and where your application has got to.
        <?php else: ?>
          You are not yet in a market, so no chairman follows you yet. Here is
          where you are trading and what you can do next.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <h1><?= $market ? e($market['name']) : 'Your market' ?></h1>
      <p>The market you administer, and the traders placed in it who follow you.</p>
    <?php endif; ?>
  </div>
</div>

<?php if ($isTrade && !$vendor): ?>
  <!-- No vendor record: nothing can be shown, so say so plainly. -->
  <div class="card">
    <div class="empty">
      <div class="big"><?= KCCA_icon('people', 28) ?></div>
      <h3>This account is not linked to a trader record</h3>
      <p>
        A trader account has to point at the record that holds it, otherwise it
        cannot know which market or which chairman it belongs to. The System
        Administrator sets that on the account.
      </p>
      <a class="btn btn-primary" href="portal.php">Go to the trader portal</a>
    </div>
  </div>

<?php elseif ($isTrade): ?>
  <!-- ======================= TRADER ======================= -->
  <div class="grid g4 mb-2">
    <div class="stat">
      <div class="label">Your stage</div>
      <div class="value" style="font-size:17px"><span class="badge <?= e(statusTone((string) $vendor['status'])) ?>"><?= e(humanise((string) $vendor['status'])) ?></span></div>
      <div class="meta"><?= e($vendor['vendor_code']) ?></div>
    </div>
    <div class="stat info">
      <div class="label">Trading at</div>
      <div class="value" style="font-size:17px"><?= e($vendor['zone_name'] ?: 'Not recorded') ?></div>
      <div class="meta"><?= e($vendor['division_name'] ?: '—') ?></div>
    </div>
    <div class="stat green">
      <div class="label">Vacant pitches</div>
      <div class="value"><?= num($market ? (int) $market['vacant_spaces'] : 0) ?></div>
      <div class="meta"><?= $market ? 'at your market' : 'place yourself in a market first' ?></div>
    </div>
    <div class="stat gold">
      <div class="label">Open requests</div>
      <div class="value"><?= num(count(array_filter($requests, static fn(array $r): bool => in_array($r['status'], ['pending', 'reviewing'], true)))) ?></div>
      <div class="meta">of <?= num(count($requests)) ?> made</div>
    </div>
  </div>

  <div class="grid g-2-1">
    <div>
      <!-- Your chairman -->
      <div class="card mb-2">
        <div class="card-head">
          <div class="grow">
            <h2>Your chairman</h2>
            <p class="desc">The one person at your market responsible for you.</p>
          </div>
        </div>
        <div class="card-body">
          <?php if ($chairman): ?>
            <div class="stack-s">
              <div class="row-tight">
                <span class="badge ok">Active</span>
                <span class="strong"><?= e($chairman['full_name']) ?></span>
              </div>
              <div class="row-tight">
                <span class="badge muted">Call</span>
                <span><?= e($chairman['phone']) ?></span>
              </div>
              <?php if (!empty($chairman['market_name'])): ?>
                <div class="row-tight">
                  <span class="badge muted">Market</span>
                  <span><a href="market_map.php?market=<?= (int) $market['id'] ?>"><?= e($chairman['market_name']) ?></a></span>
                </div>
              <?php endif; ?>
            </div>
            <p class="xsmall muted mt-1" style="margin-bottom:0">
              Your chairman follows you through the register, your allocation and
              every report about your case.
            </p>
          <?php else: ?>
            <div class="empty" style="padding:18px 0">
              <h3>No chairman yet</h3>
              <p>
                You have not been placed in a market, so no chairman is responsible
                for you. A trader is in a market — and therefore under a chairman —
                once a space is allocated to them.
              </p>
              <a class="btn btn-primary" href="portal.php">Find a space</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Your allocation -->
      <div class="card">
        <div class="card-head">
          <div class="grow">
            <h2>Your applications</h2>
            <p class="desc">Every offer and every pitch you asked for.</p>
          </div>
        </div>
        <div class="card-body tight">
          <div class="table-wrap">
            <table class="data compact">
              <thead>
                <tr><th>Reference</th><th>Market</th><th>Pitch</th><th>Score</th><th class="center">Stage</th></tr>
              </thead>
              <tbody>
                <?php foreach (array_merge($allocs, $requests) as $a): ?>
                  <tr>
                    <td class="xsmall"><?= e($a['reference'] ?? '—') ?></td>
                    <td class="xsmall"><?= e($a['market_name'] ?? '—') ?></td>
                    <td class="xsmall"><?= e($a['space_code'] ?? '—') ?></td>
                    <td class="xsmall num"><?= isset($a['total_score']) ? num((float) $a['total_score'], 1) : '—' ?></td>
                    <td class="center xsmall"><span class="badge <?= e(statusTone((string) $a['status'])) ?>"><?= e(humanise((string) $a['status'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (!$allocs && !$requests): ?>
            <div class="empty">
              <p>You have not asked for a pitch yet.</p>
              <a class="btn btn-primary" href="portal.php">Find a space</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div>
      <!-- Your market -->
      <div class="card mb-2">
        <div class="card-head"><div class="grow"><h2>Your market</h2></div></div>
        <div class="card-body">
          <?php if ($market): ?>
            <div class="stack-s">
              <div class="row-tight"><span class="cl">Category</span><span><?= e(humanise((string) $market['category'])) ?></span></div>
              <div class="row-tight"><span class="cl">Division</span><span><?= e($market['division_name']) ?></span></div>
              <div class="row-tight"><span class="cl">Parish</span><span><?= e($market['parish'] ?: '—') ?></span></div>
              <div class="row-tight"><span class="cl">Capacity</span><span class="num"><?= num((int) $market['capacity']) ?></span></div>
              <div class="row-tight"><span class="cl">Trading in it</span><span class="num"><?= num((int) $market['registered_vendors']) ?></span></div>
              <div class="row-tight"><span class="cl">Vacant</span><span class="num"><?= num((int) $market['vacant_spaces']) ?></span></div>
            </div>
            <a class="btn btn-ghost btn-sm mt-1" href="market_map.php?market=<?= (int) $market['id'] ?>">
              See the pitch plan
            </a>
          <?php else: ?>
            <p class="muted" style="margin:0">No market yet — you are trading in a zone.</p>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($pitches): ?>
        <div class="card">
          <div class="card-head"><div class="grow"><h2>Pitches in use</h2></div></div>
          <div class="card-body tight">
            <div class="table-wrap">
              <table class="data compact">
                <thead><tr><th>Pitch</th><th>Section</th><th class="center">State</th></tr></thead>
                <tbody>
                  <?php foreach ($pitches as $sp): ?>
                    <tr>
                      <td class="xsmall"><?= e($sp['space_code']) ?></td>
                      <td class="xsmall"><?= e($sp['section'] ?: '—') ?></td>
                      <td class="center xsmall"><span class="badge muted"><?= e(humanise((string) $sp['status'])) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============ WORKWEAR, NOTICES AND COMPLAINTS — THE TRADER'S OWN ============
       The same three things the officer screens manage, seen from here: what
       workwear this trade is prescribed and who supplies it, what the chairman
       has published to the market, and a complaint that goes to that chairman
       rather than to the Authority. -->
  <div class="grid g2">

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Your workwear</h2></div></div>
      <div class="card-body">
        <?php if (!$myAttire): ?>
          <p class="empty">No workwear is prescribed for your trade yet.</p>
        <?php else: ?>
          <div class="attire">
            <span class="attire-chip" style="background:<?= e($myAttire['colour_hex']) ?>"></span>
            <div>
              <div class="strong"><?= e($myAttire['garment']) ?></div>
              <div class="xsmall muted">
                <?= e($myAttire['colour']) ?>
                · <?= e(ugx((int) $myAttire['unit_price_ugx'])) ?>
                <?php if ($myAttire['sizes']): ?> · <?= e($myAttire['sizes']) ?><?php endif; ?>
              </div>
            </div>
          </div>

          <div class="notice-meta xsmall muted" style="margin-top:10px">
            <?= KCCA_icon('info', 13) ?> <?= e($myIssuer['reason']) ?>
          </div>

          <?php if ($myHandovers === []): ?>
            <p class="empty" style="margin-top:12px">Nothing has been issued to you yet.</p>
          <?php else: ?>
            <div class="table-wrap" style="margin-top:12px">
              <table class="data compact">
                <thead><tr><th>Item</th><th>Given</th><th class="center">State</th></tr></thead>
                <tbody>
                <?php foreach ($myHandovers as $h): ?>
                  <tr>
                    <td class="xsmall">
                      <?= e($h['item_name']) ?>
                      <?php if ((int) $h['quantity'] > 1): ?><span class="muted">×<?= num((int) $h['quantity']) ?></span><?php endif; ?>
                    </td>
                    <td class="xsmall nowrap"><?= e(fmtDate($h['issued_on'])) ?></td>
                    <td class="center xsmall">
                      <?php if ($h['received_at']): ?>
                        <span class="badge ok">Received</span>
                      <?php else: ?>
                        <span class="badge warn">Not confirmed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php
            /* Confirming is the trader's own act, and it is the only thing
               that closes a handover. Until they do, the Authority cannot
               distinguish "we gave it out" from "they got it". */
            foreach ($myHandovers as $h): if (!$h['received_at']) { ?>
              <div class="card-foot right">
                <button class="btn btn-sm" data-support="confirm" data-id="<?= (int) $h['id'] ?>">
                  I have received <?= e($h['item_name']) ?>
                </button>
              </div>
            <?php break; } endforeach; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="grow"><h2>Notices for your market</h2></div>
        <span class="xsmall muted"><?= num(count($myNotices)) ?></span>
      </div>
      <div class="card-body">
        <?php if ($myNotices === []): ?>
          <p class="empty">There are no notices at the moment.</p>
        <?php else: ?>
          <?php foreach (array_slice($myNotices, 0, 4) as $n): ?>
            <div class="notice-mini">
              <div class="strong"><?= e($n['subject']) ?></div>
              <div class="xsmall muted">
                <?= e(Notices::CATEGORIES[$n['category']] ?? 'General') ?>
                · <?= e(fmtDate($n['issued_at'])) ?>
              </div>
              <p class="xsmall"><?= e(truncateText($n['body'], 180)) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card mb-2">
    <div class="card-head"><div class="grow"><h2>Raise a complaint</h2></div></div>
    <div class="card-body">
      <p class="xsmall muted" style="margin-bottom:12px">
        Your complaint goes to <?= $chairman ? e($chairman['full_name']) . ', your chairman' : 'your chairman' ?> first,
        because they are the one in the market who can answer it. They can pass it
        to the Authority if it is not theirs to settle.
      </p>

      <form id="complaint-form">
        <input type="hidden" name="action" value="raise">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

        <div class="grid g2">
          <div class="field">
            <label for="c-cat">About</label>
            <select name="category" id="c-cat">
              <?php foreach (Complaints::CATEGORIES as $k => $label): ?>
                <option value="<?= e($k) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="c-subject">Subject</label>
            <input type="text" name="subject" id="c-subject" required maxlength="200">
          </div>
        </div>

        <div class="field">
          <label for="c-body">What happened</label>
          <textarea name="body" id="c-body" rows="3" required
                    placeholder="A few lines is enough."></textarea>
        </div>

        <div class="card-foot right">
          <button type="submit" class="btn btn-primary">Send to my chairman</button>
        </div>
      </form>

      <?php if ($myComplaints !== []): ?>
        <div class="table-wrap" style="margin-top:14px">
          <table class="data compact">
            <thead><tr><th>Reference</th><th>Subject</th><th class="center">Stage</th></tr></thead>
            <tbody>
            <?php foreach ($myComplaints as $c): ?>
              <tr>
                <td><code class="xsmall"><?= e($c['reference']) ?></code></td>
                <td class="xsmall"><?= e($c['subject']) ?></td>
                <td class="center">
                  <span class="badge <?= complaintTone($c['status']) ?>"><?= e(Complaints::STATUSES[$c['status']] ?? $c['status']) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <!-- ======================= CHAIRMAN ======================= -->
  <div class="grid g4 mb-2">
    <div class="stat info">
      <div class="label">Traders under you</div>
      <div class="value"><?= num(count($traders)) ?></div>
      <div class="meta">placed in this market</div>
    </div>
    <div class="stat green">
      <div class="label">Pitches vacant</div>
      <div class="value"><?= num((int) ($spaces['vacant'] ?? 0)) ?></div>
      <div class="meta">of <?= num((int) ($spaces['total'] ?? 0)) ?> pitches</div>
    </div>
    <div class="stat">
      <div class="label">Pitches occupied</div>
      <div class="value"><?= num((int) ($spaces['occupied'] ?? 0)) ?></div>
      <div class="meta"><?= num((int) ($spaces['reserved'] ?? 0)) ?> held by an offer</div>
    </div>
    <div class="stat gold">
      <div class="label">Capacity</div>
      <div class="value"><?= num($market ? (int) $market['capacity'] : 0) ?></div>
      <div class="meta"><?= num($market ? (int) $market['registered_vendors'] : 0) ?> trading in it</div>
    </div>
  </div>

  <div class="grid g-2-1">
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>The traders who follow you</h2>
          <p class="desc">Placed into <?= $market ? e($market['name']) : 'your market' ?>, alphabetically.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="vendors.php">Full register</a>
      </div>
      <div class="card-body tight">
        <div class="table-wrap">
          <table class="data compact">
            <thead>
              <tr><th>Trader</th><th>Trade</th><th class="right">Turnover</th><th>Telephone</th><th class="center">Stage</th></tr>
            </thead>
            <tbody>
              <?php foreach ($traders as $t): ?>
                <tr>
                  <td>
                    <a href="vendors.php?id=<?= (int) $t['id'] ?>"><strong><?= e($t['full_name']) ?></strong></a>
                    <div class="xsmall muted"><?= e($t['vendor_code']) ?></div>
                  </td>
                  <td class="xsmall"><?= e(humanise((string) $t['business_category'])) ?></td>
                  <td class="right num xsmall"><?= ugxShort((int) $t['monthly_turnover_ugx']) ?></td>
                  <td class="xsmall"><?= e($t['phone'] ?: '—') ?></td>
                  <td class="center xsmall"><span class="badge <?= e(statusTone((string) $t['status'])) ?>"><?= e(humanise((string) $t['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (!$traders): ?>
          <div class="empty">
            <div class="big"><?= KCCA_icon('people', 28) ?></div>
            <h3>No traders placed in your market yet</h3>
            <p>
              A trader follows you once a space is allocated to them here. The
              Authority will notify you when that happens.
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="card mb-2">
        <div class="card-head"><div class="grow"><h2>Your record</h2></div></div>
        <div class="card-body">
          <div class="stack-s">
            <div class="row-tight"><span class="cl">Name</span><span class="strong"><?= e($mine['full_name']) ?></span></div>
            <div class="row-tight"><span class="cl">Telephone</span><span><?= e($mine['phone'] ?: '—') ?></span></div>
            <div class="row-tight"><span class="cl">Status</span><span><?= e(humanise($mine['status'])) ?></span></div>
            <div class="row-tight"><span class="cl">Division</span><span><?= e($mine['division']) ?></span></div>
          </div>
          <a class="btn btn-ghost btn-sm mt-1" href="chairmen.php">Edit my details</a>
        </div>
      </div>

      <?php if ($market): ?>
        <div class="card">
          <div class="card-head"><div class="grow"><h2>Your market</h2></div></div>
          <div class="card-body">
            <div class="stack-s">
              <div class="row-tight"><span class="cl">Category</span><span><?= e(humanise((string) $market['category'])) ?></span></div>
              <div class="row-tight"><span class="cl">Parish</span><span><?= e($market['parish'] ?: '—') ?></span></div>
              <div class="row-tight"><span class="cl">Condition</span><span><?= $market['condition_score'] ? (int) $market['condition_score'] . ' of 5' : 'not rated' ?></span></div>
              <div class="row-tight"><span class="cl">Water</span><span><?= $market['water_available'] ? 'yes' : 'no' ?></span></div>
              <div class="row-tight"><span class="cl">Toilets</span><span><?= $market['toilets'] ? 'yes' : 'no' ?></span></div>
            </div>
            <a class="btn btn-ghost btn-sm mt-1" href="market_map.php?market=<?= (int) $market['id'] ?>">
              See the pitch plan
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<script>
/* Both forms are the trader's own act: confirming they received something, and
   raising a complaint with their chairman. Both reload on success, so what the
   trader sees afterwards comes from the server rather than from the form. */
document.getElementById('complaint-form')?.addEventListener('submit', function (ev) {
  ev.preventDefault();
  KCCA.post('../api/complaint_action.php', new FormData(ev.target)).then(function (res) {
    KCCA.flash(res.message || res.error || '', res.ok ? 'ok' : 'bad');
    if (res.ok) setTimeout(function () { location.reload(); }, 800);
  });
});

document.querySelectorAll('[data-support="confirm"]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    btn.disabled = true;
    KCCA.post('../api/support_action.php', { action: 'confirm', issue_id: btn.dataset.id })
      .then(function (res) {
        KCCA.flash(res.message || res.error || '', res.ok ? 'ok' : 'bad');
        if (res.ok) { setTimeout(function () { location.reload(); }, 700); }
        else { btn.disabled = false; }
      });
  });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>