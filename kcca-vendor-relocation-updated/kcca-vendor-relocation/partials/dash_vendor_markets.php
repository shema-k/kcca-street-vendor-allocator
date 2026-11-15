<?php
/**
 * Dashboard section: markets with space (vendor).
 *
 * A trader's first question. Only locations that actually have a free pitch
 * are listed, and the number of them is stated before anything else,
 * because "how much space is there" is the answer they came for.
 */

declare(strict_types=1);

$markets = Database::all(
    "SELECT m.id, m.name, m.code, m.category, m.capacity, m.vacant_spaces,
            m.registered_vendors, m.water_available, m.power_available, m.toilets,
            m.transport_stand, d.name AS division_name
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE m.vacant_spaces > 0 AND m.status IN ('operational','upgrading')
      ORDER BY m.vacant_spaces DESC
      LIMIT 30"
);

$totalFree = (int) Database::value('SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE vacant_spaces > 0');
$publicFree = (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE vacant_spaces > 0 AND category='public'");
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Markets with space</h2>
      <p class="desc">
        <?= num($totalFree) ?> pitches free across the city —
        <?= num($publicFree) ?> of them in KCCA's own markets, which carry no rent.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="market_map.php">Open the stall map</a>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data compact">
        <thead>
          <tr><th>Market</th><th>Division</th><th class="right">Free pitches</th>
              <th class="center">Water</th><th class="center">Power</th>
              <th class="center">Toilets</th><th class="center">Transport</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($markets as $mk): ?>
            <tr>
              <td>
                <strong><?= e($mk['name']) ?></strong>
                <div class="xsmall muted">
                  <span class="badge <?= $mk['category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px">
                    <?= $mk['category'] === 'public' ? 'KCCA market' : 'Private' ?>
                  </span>
                  <?= e($mk['code']) ?>
                </div>
              </td>
              <td class="xsmall"><?= e($mk['division_name']) ?></td>
              <td class="right num strong"><?= num((int) $mk['vacant_spaces']) ?></td>
              <td class="center"><?= $mk['water_available'] ? '<span class="badge ok">Yes</span>' : '<span class="badge muted">No</span>' ?></td>
              <td class="center"><?= $mk['power_available'] ? '<span class="badge ok">Yes</span>' : '<span class="badge muted">No</span>' ?></td>
              <td class="center"><?= $mk['toilets'] ? '<span class="badge ok">Yes</span>' : '<span class="badge muted">No</span>' ?></td>
              <td class="center xsmall muted"><?= e($mk['transport_stand'] ?: '—') ?></td>
              <td class="right">
                <a class="btn btn-ghost btn-sm" href="market_map.php?market=<?= (int) $mk['id'] ?>">See the pitches</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$markets): ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('store', 28) ?></div>
        <h3>No space free at the moment</h3>
        <p>Space is added as markets are completed. Check with your division office.</p>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    KCCA does not charge for the pits it has identified. A private market's operator sets their own
    terms.
  </div>
</div>