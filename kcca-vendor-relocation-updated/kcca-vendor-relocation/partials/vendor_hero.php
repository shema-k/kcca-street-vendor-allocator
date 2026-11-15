<?php
/**
 * The welcome: a photo slider, a live ticker of markets with room, and three
 * tiles that count up. Shown at the top of EVERY account's dashboard, so a
 * trader, a planner and the Executive Director all open the system onto the
 * same living picture. Only the buttons differ, by what the role may open.
 *
 * Everything here is the public picture (markets, pitches). Nothing is about
 * any individual trader, so it is safe to draw for any signed-in account.
 *
 * Expects: $me (the signed-in user).
 */

declare(strict_types=1);

$vhSlides = [
    ['proudlyswazi-36498408',                    'Clear streets, better trade',  'Orderly streets mean safer footpaths and more customers for everyone who trades lawfully.'],
    ['phinley-sperrer-2163513679-39554462',      'Space is waiting',             'Gazetted pitches are being opened across Kampala’s markets. See what is free near you.'],
    ['nasiru-othman-447186959-40082918',         'Trade with a licence',         'A licensed pitch means no more fines and no more running when the officers come.'],
    ['ana-kenk-2159501753-36470055',             'Markets built for you',        'Markets are being built and rebuilt with traders in mind: water, power, shelter.'],
    ['the-chongaiman-ace-1620694153-39916941',   'Fair for everyone',            'Placement weighs need and fairness, not only who asked first.'],
    ['dokunayano-38968324',                      'No more queues',               'Check what is available and reach the Authority without a day lost at the counter.'],
];

$vhFirst = explode(' ', trim((string) ($me['full_name'] ?? 'there')))[0];

/* The main button is the first screen this role is meant to use. */
$vhPrimary = ['label' => 'Find a stall', 'href' => 'market_map.php'];
foreach ([
    ['ai-planner', 'AI Planner', 'ai_planner.php'],
    ['relocation', 'Match a trader', 'relocation.php'],
    ['vendors', 'Vendor register', 'vendors.php'],
    ['licensing', 'Licensing', 'licensing.php'],
    ['my-market', 'My market', 'my_market.php'],
] as [$vhId, $vhLabel, $vhHref]) {
    if (Auth::screenAllows($vhId) && Auth::role() !== 'vendor') {
        $vhPrimary = ['label' => $vhLabel, 'href' => $vhHref];
        break;
    }
}
$vhSecondary = Auth::screenAllows('market-map')
    ? ['label' => 'Stall map', 'href' => 'market_map.php']
    : ['label' => 'Trader portal', 'href' => 'portal.php'];
if ($vhPrimary['href'] === $vhSecondary['href']) {
    $vhSecondary = ['label' => 'Trader portal', 'href' => 'portal.php'];
}

$vhMarkets = Database::all(
    "SELECT name, vacant_spaces FROM markets
      WHERE vacant_spaces > 0 AND status IN ('operational','upgrading')
      ORDER BY vacant_spaces DESC, name LIMIT 14"
);
$vhOpen    = (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE status IN ('operational','upgrading')");
$vhMarketN = (int) Database::value("SELECT COUNT(*) FROM markets WHERE vacant_spaces > 0 AND status IN ('operational','upgrading')");
$vhPublic  = (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category = 'public' AND status IN ('operational','upgrading')");
?>
<section class="vhero" data-vhero data-interval="5500" aria-roledescription="carousel" aria-label="Trading in Kampala" tabindex="0">
  <?php foreach ($vhSlides as $i => [$img, $title, $text]): ?>
    <div class="vslide <?= $i === 0 ? 'on' : '' ?>" aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
      <picture>
        <source srcset="../images/pexels-<?= e($img) ?>.webp" type="image/webp">
        <img src="../images/pexels-<?= e($img) ?>.jpg" alt="" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
      </picture>
    </div>
  <?php endforeach; ?>

  <div class="vcopy">
    <div class="hello">Welcome back, <?= e($vhFirst) ?></div>
    <div class="vcap-wrap">
      <?php foreach ($vhSlides as $i => [$img, $title, $text]): ?>
        <div class="vcap" <?= $i === 0 ? '' : 'hidden' ?>>
          <h2><?= e($title) ?></h2>
          <p><?= e($text) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="vcta">
      <a class="btn btn-primary" href="<?= e($vhPrimary['href']) ?>"><?= e($vhPrimary['label']) ?></a>
      <a class="btn btn-ghost" href="<?= e($vhSecondary['href']) ?>"><?= e($vhSecondary['label']) ?></a>
    </div>
  </div>

  <div class="vdots" role="tablist" aria-label="Choose a photo">
    <?php foreach ($vhSlides as $i => [$img, $title, $text]): ?>
      <button type="button" role="tab" class="<?= $i === 0 ? 'on' : '' ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
              aria-label="<?= e($title) ?>"></button>
    <?php endforeach; ?>
  </div>

  <?php if ($vhMarkets): ?>
    <div class="vticker" aria-label="Markets with space today">
      <div class="vticker-track">
        <?php for ($pass = 0; $pass < 2; $pass++): ?>
          <?php foreach ($vhMarkets as $m): ?>
            <span><i></i><?= e($m['name']) ?> · <b><?= num((int) $m['vacant_spaces']) ?></b> pitches free</span>
          <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
  <div class="vbar" aria-hidden="true"><i></i></div>
</section>

<div class="vtiles">
  <a class="vtile" href="market_map.php">
    <span class="vi"><?= KCCA_icon('store', 22) ?></span>
    <span><b><?= num($vhOpen) ?></b><span>pitches free across the city</span></span>
  </a>
  <a class="vtile" href="portal.php">
    <span class="vi"><?= KCCA_icon('map', 22) ?></span>
    <span><b><?= num($vhMarketN) ?></b><span>markets with space today</span></span>
  </a>
  <a class="vtile" href="portal.php">
    <span class="vi"><?= KCCA_icon('shield', 22) ?></span>
    <span><b><?= num($vhPublic) ?></b><span>in public markets, the Authority’s own</span></span>
  </a>
</div>
