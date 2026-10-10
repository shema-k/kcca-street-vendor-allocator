<?php
/**
 * Dashboard section: public statements (Legal Affairs).
 */

declare(strict_types=1);

$rows = Database::all('SELECT * FROM notices ORDER BY published_on DESC LIMIT 6');
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>What the Authority has said publicly</h2>
      <p class="desc">The statements the enforcement chain rests on.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="https://www.kcca.go.ug" target="_blank" rel="noopener">KCCA news</a>
  </div>
  <div class="card-body">
    <div class="stack">
      <?php foreach ($rows as $n): ?>
        <div class="criterion">
          <span class="cl"><?= e($n['headline']) ?></span>
          <span class="cv"><span class="badge muted"><?= e(fmtDate($n['published_on'])) ?></span></span>
          <span class="cn"><?= e(truncateText($n['body'], 180)) ?></span>
          <?php if ($n['source_url']): ?>
            <span class="cw"><a href="<?= e($n['source_url']) ?>" target="_blank" rel="noopener">Source</a></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
