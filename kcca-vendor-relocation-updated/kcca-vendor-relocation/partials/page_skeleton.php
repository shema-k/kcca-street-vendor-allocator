<?php
/** Shared loading surface, outside the real page's content and forms. */
$skeletonLayout = match ($navPageId) {
    'dashboard', 'analytics' => 'dashboard',
    'profile', 'settings', 'market-edit', 'ai-planner' => 'form',
    'city-map', 'market-map' => 'map',
    default => 'table',
};
?>
<div class="page-skeleton" data-page-skeleton data-layout="<?= e($skeletonLayout) ?>" aria-hidden="true">
  <div class="skeleton-heading"><div><i class="sk sk-crumb"></i><i class="sk sk-title"></i><i class="sk sk-subtitle"></i></div><i class="sk sk-action"></i></div>
  <div class="skeleton-stats">
    <?php for ($skeletonIndex = 0; $skeletonIndex < 4; $skeletonIndex++): ?>
      <div class="skeleton-panel"><i class="sk sk-label"></i><i class="sk sk-number"></i><i class="sk sk-caption"></i></div>
    <?php endfor; ?>
  </div>
  <div class="skeleton-filter skeleton-panel"><i class="sk"></i><i class="sk"></i><i class="sk"></i><i class="sk sk-action"></i></div>
  <div class="skeleton-table skeleton-panel"><div class="skeleton-section"><i class="sk sk-label"></i></div>
    <?php for ($skeletonIndex = 0; $skeletonIndex < 6; $skeletonIndex++): ?>
      <div class="skeleton-row"><i class="sk"></i><i class="sk"></i><i class="sk"></i><i class="sk"></i><i class="sk"></i></div>
    <?php endfor; ?>
  </div>
  <div class="skeleton-charts">
    <div class="skeleton-panel"><i class="sk sk-label"></i><div class="skeleton-bars"><?php for ($skeletonIndex = 0; $skeletonIndex < 8; $skeletonIndex++): ?><i class="sk"></i><?php endfor; ?></div><i class="sk sk-caption"></i></div>
    <div class="skeleton-panel"><i class="sk sk-label"></i><div class="skeleton-donut"></div><i class="sk sk-subtitle"></i></div>
  </div>
  <div class="skeleton-form skeleton-panel"><i class="sk sk-label"></i><div class="skeleton-fields"><?php for ($skeletonIndex = 0; $skeletonIndex < 6; $skeletonIndex++): ?><div><i class="sk sk-caption"></i><i class="sk sk-input"></i></div><?php endfor; ?></div><i class="sk sk-action"></i></div>
  <div class="skeleton-map skeleton-panel"><i class="sk sk-label"></i><div class="sk skeleton-map-surface"></div></div>
</div>
<span class="page-loading-status" role="status" aria-live="polite"></span>
