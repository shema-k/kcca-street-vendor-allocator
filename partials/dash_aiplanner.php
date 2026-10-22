<?php
/**
 * Dashboard section: the AI Planner (Planner, Mayor, Executive Director,
 * System Administrator). Shown only to the roles that can open the screen.
 */

declare(strict_types=1);

$onVendors = RoleDashboard::scopeSql($scope, 'v');
$onMarkets = RoleDashboard::scopeFor('markets', $scope);

$apWaiting = (int) Database::value(
    "SELECT COUNT(*) FROM vendors v
      WHERE v.status IN ('surveyed','notified') AND v.current_zone_id IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM allocations a WHERE a.vendor_id = v.id
                         AND a.status IN ('proposed','offered','accepted','occupied'))" . $onVendors
);
$apVacant = (int) Database::value(
    "SELECT COALESCE(SUM(vacant_spaces),0) FROM markets
      WHERE status IN ('operational','upgrading')" . $onMarkets
);
$apProposed = (int) Database::value(
    "SELECT COUNT(*) FROM recommendations r
      WHERE r.status = 'proposed' AND r.explanation LIKE '[AI plan %'"
    . (isset($scope['division_id'])
        ? ' AND r.vendor_id IN (SELECT id FROM vendors WHERE division_id = ' . (int) $scope['division_id'] . ')'
        : '')
);
$apShort = $apWaiting > $apVacant;
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>AI Planner</h2>
      <p class="desc">Places a whole batch of traders at once, so no pitch is promised twice.</p>
    </div>
    <a class="btn btn-primary btn-sm" href="ai_planner.php">Draw up a plan</a>
  </div>
  <div class="card-body">
    <div class="grid g3">
      <div>
        <div class="xsmall muted">Traders waiting to be placed</div>
        <div style="font-size:24px;font-weight:800;color:var(--ink)"><?= num($apWaiting) ?></div>
      </div>
      <div>
        <div class="xsmall muted">Vacant pitches</div>
        <div style="font-size:24px;font-weight:800;color:var(--ink)"><?= num($apVacant) ?></div>
      </div>
      <div>
        <div class="xsmall muted">AI proposals awaiting approval</div>
        <div style="font-size:24px;font-weight:800;color:var(--ink)"><?= num($apProposed) ?></div>
      </div>
    </div>
    <?php if ($apShort): ?>
      <p class="small muted" style="margin:12px 0 0">
        There are more traders than pitches, so the plan places the most pressing cases first and
        leaves the rest for the next batch.
      </p>
    <?php endif; ?>
  </div>
</div>
