<?php
/**
 * Dashboard section: allocation and approval changes (Internal Audit).
 *
 * The decisions that moved people and space, as a timeline rather than a
 * table of current state — which is what an auditor needs and what the
 * other roles' screens cannot give them.
 */

declare(strict_types=1);

$events = Database::all(
    "SELECT e.*, v.full_name, m.name AS market_name, a.reference AS allocation_reference
       FROM relocation_events e
       LEFT JOIN vendors v ON v.id = e.vendor_id
       LEFT JOIN markets m ON m.id = e.market_id
       LEFT JOIN allocations a ON a.id = e.allocation_id
      ORDER BY e.occurred_at DESC, e.id DESC LIMIT 18"
);

$byType = [];
foreach (Database::all('SELECT event_type, COUNT(*) AS n FROM relocation_events GROUP BY event_type') as $r) {
    $byType[$r['event_type']] = (int) $r['n'];
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>Allocation and approval changes</h2>
      <p class="desc">
        Every state change in the relocation history, newest first — the trail behind the current
        position.
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="allocations.php">Allocation register</a>
  </div>
  <div class="card-body">
    <?php if ($byType): ?>
      <svg class="chart mb-2" data-chart='<?= ejs([
        'type' => 'hbar',
        'opts' => [
          'labelW' => 130, 'rowH' => 26,
          'items' => array_map(static fn($k, $n): array => [
            'label' => humanise($k),
            'value' => $n,
            'color' => in_array($k, ['declined', 'withdrawn', 'cancelled'], true) ? '#C0261A' : '#0A6339',
          ], array_keys($byType), array_values($byType)),
        ],
      ]) ?>' aria-label="Relocation events by type"></svg>
    <?php endif; ?>

    <?php if ($events): ?>
      <ul class="timeline">
        <?php foreach ($events as $e): ?>
          <li>
            <span class="when"><?= e(fmtDate($e['occurred_at'], 'd M Y H:i')) ?></span>
            <strong><?= e(humanise($e['event_type'])) ?></strong>
            <?php if ($e['from_status']): ?>
              <span class="muted small"><?= e(humanise($e['from_status'])) ?> → <?= e(humanise((string) $e['to_status'])) ?></span>
            <?php else: ?>
              <span class="muted small">→ <?= e(humanise((string) $e['to_status'])) ?></span>
            <?php endif; ?>
            <?php if ($e['full_name']): ?>
              <div class="small"><?= e($e['full_name']) ?><?php if ($e['market_name']): ?> → <?= e($e['market_name']) ?><?php endif; ?></div>
            <?php endif; ?>
            <?php if ($e['note']): ?>
              <div class="xsmall muted"><?= e(truncateText($e['note'], 90)) ?></div>
            <?php endif; ?>
            <div class="xsmall muted"><?= e($e['actor']) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('log', 28) ?></div>
        <h3>No relocation events yet</h3>
        <p>Generating a recommendation or issuing an offer starts the history.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
