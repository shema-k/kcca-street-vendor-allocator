<?php
/**
 * Dashboard section: gazetted space (Market Administration).
 */

declare(strict_types=1);

$rows = Database::all(
    "SELECT m.id, m.code, m.name, m.category, m.capacity, m.registered_vendors,
            m.vacant_spaces, d.name AS division_name, m.status
       FROM markets m JOIN divisions d ON d.id = m.division_id
      ORDER BY m.vacant_spaces DESC, m.name LIMIT 12"
);

$totals = Database::one(
    "SELECT COALESCE(SUM(capacity),0) AS capacity,
            COALESCE(SUM(registered_vendors),0) AS registered,
            COALESCE(SUM(vacant_spaces),0) AS vacant,
            COALESCE(SUM(CASE WHEN category='public' THEN vacant_spaces END),0) AS public_vacant,
            COALESCE(SUM(CASE WHEN category='private' THEN vacant_spaces END),0) AS private_vacant
       FROM markets"
);
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Gazetted space</h2>
      <p class="desc">
        Capacity, occupancy and vacancy. The public figures reconcile to the Authority's published
        totals exactly.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="vacancy.php">Vacancy finder</a>
  </div>
  <div class="card-body">
    <div class="grid g4 mb-2">
      <div class="stat">
        <div class="label">Total capacity</div>
        <div class="value"><?= num((int) $totals['capacity']) ?></div>
      </div>
      <div class="stat info">
        <div class="label">Registered</div>
        <div class="value"><?= num((int) $totals['registered']) ?></div>
        <div class="meta"><?= $totals['capacity'] > 0 ? pct($totals['registered'] / $totals['capacity'] * 100, 1) . ' full' : '—' ?></div>
      </div>
      <div class="stat green">
        <div class="label">Vacant</div>
        <div class="value"><?= num((int) $totals['vacant']) ?></div>
        <div class="meta"><?= num((int) $totals['public_vacant']) ?> public · <?= num((int) $totals['private_vacant']) ?> private</div>
      </div>
      <div class="stat gold">
        <div class="label">Pitches held</div>
        <div class="value"><?= num((int) Database::value('SELECT COUNT(*) FROM market_spaces')) ?></div>
        <div class="meta">individual trading spaces</div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Market</th><th>Division</th><th class="right">Capacity</th>
              <th class="right">Registered</th><th class="right">Vacant</th>
              <th style="width:130px">Occupancy</th><th class="center">Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $m):
            $cap = max(1, (int) $m['capacity']);
            $occ = round((int) $m['registered_vendors'] / $cap * 100, 1); ?>
            <tr>
              <td>
                <strong><?= e($m['name']) ?></strong>
                <div class="xsmall muted">
                  <span class="badge <?= $m['category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px"><?= e($m['category']) ?></span>
                  <?= e($m['code']) ?>
                </div>
              </td>
              <td class="xsmall"><?= e($m['division_name']) ?></td>
              <td class="right num xsmall"><?= num((int) $m['capacity']) ?></td>
              <td class="right num xsmall"><?= num((int) $m['registered_vendors']) ?></td>
              <td class="right num strong"><?= num((int) $m['vacant_spaces']) ?></td>
              <td>
                <div class="meter">
                  <span class="track"><span class="fill <?= $occ < 70 ? 'warn' : 'ok' ?>"
                        style="width:<?= (float) clamp($occ, 2, 100) ?>%"></span></span>
                  <span class="val"><?= pct($occ, 0) ?></span>
                </div>
              </td>
              <td class="center"><span class="badge <?= e(statusTone($m['status'])) ?>"><?= e(humanise($m['status'])) ?></span></td>
              <td class="right"><a class="btn btn-ghost btn-sm" href="market_map.php?market=<?= (int) $m['id'] ?>">Pitches</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
