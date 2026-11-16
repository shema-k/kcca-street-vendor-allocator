<?php
/**
 * Shared page shell: doctype, head, top bar, sidebar, open of <main>.
 *
 * Every page includes this and then finishes with footer.php.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$f     = facts();
$stats = headlineStats();
/* Page files use underscores (my_market.php) and navigation ids use hyphens
   (my-market), so those two screens were never highlighted in the rail. */
$navPageId = str_replace('_', '-', currentPage());
$me    = Auth::check() ? Auth::user() : [];
$meId  = Auth::check() ? Auth::id() : 0;
/* Namespaced: a page (profile.php) already uses $myDiv for the
   division row it fetched, and this line would silently replace it
   with an integer. */
$layoutDivisionId = (int) ($me['division_id'] ?? 0);
/* Offers that have lapsed are expired and their pitches returned, at most every ten minutes. */
if ($meId > 0) {
    OfferSweeper::sweepIfDue();
}
/* This account's own appearance (glass, motion, guidance), applied before first paint. */
$layoutPrefs = $meId > 0 ? Preferences::get($meId) : Preferences::DEFAULTS;
?>
<!doctype html>
<html lang="<?= e(Lang::code()) ?>" data-glass="<?= e($layoutPrefs['glass']) ?>"<?= $layoutPrefs['motion'] === 'off' ? ' data-motion="off"' : '' ?><?= $layoutPrefs['guide'] === 'on' ? ' data-guide="on"' : '' ?> data-density="<?= e($layoutPrefs['density']) ?>" data-notify="<?= e($layoutPrefs['notify']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
<meta name="kcca-csrf" content="<?= e(Auth::csrfToken()) ?>">
<meta name="kcca-root" content="<?= e(APP_REL) ?>">
<meta name="description" content="KCCA Vendor Relocation and Space Allocation System — trade order, gazetted market space and vendor relocation across Kampala Capital City.">
<meta name="author" content="Kampala Capital City Authority">
<meta name="theme-color" content="#0a6339">
<title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e(APP_SHORT) ?></title>
<link rel="icon" type="image/png" href="../assets/brand/kcca-favicon.png">
<!-- The text layer cannot be laid out without the face, so it is fetched
     in parallel with the stylesheets rather than discovered after they parse. -->
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="<?= e(APP_REL) ?>assets/fonts/inter-400.woff2">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= e(APP_REL) ?>assets/fonts/inter-600.woff2">

<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/fonts.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/kcca.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/activities.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/civic.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/alive.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/dark.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/professional.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/backoffice.css?v=<?= filemtime(__DIR__ . '/../assets/css/backoffice.css') ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/typography.css?v=<?= filemtime(__DIR__ . '/../assets/css/typography.css') ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/page-loader.css?v=<?= filemtime(__DIR__ . '/../assets/css/page-loader.css') ?>">
<script>
/* No-JavaScript readers see the real page; a failed asset also fails open. */
document.documentElement.classList.add('page-loading');
window.kccaLoadingFallback = setTimeout(function () {
  document.documentElement.classList.remove('page-loading');
}, 8000);
</script>
<script defer src="<?= e(APP_REL) ?>assets/js/page-loader.js?v=<?= filemtime(__DIR__ . '/../assets/js/page-loader.js') ?>"></script>
</head>
<body>
<script>
/* Applied before first paint: a reader who chose dark should never see a
   white flash while the stylesheets settle. */
(function(){try{var c=document.cookie.match(/(^| )kcca-theme=([^;]+)/),t=c?c[2]:localStorage.getItem('kcca-theme');
if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<a class="skip-link" href="#main">Skip to main content</a>

<div class="app">

  <!-- ============================ TOP BAR ============================ -->
  <header class="topbar">
    <button type="button" class="icon-link rail-toggle" data-rail-toggle aria-label="Toggle navigation menu" aria-controls="main-navigation" aria-expanded="true">
      <?= KCCA_icon('menu', 18) ?>
    </button>

    <div class="brand">
      <span class="brand-mark">
        <img class="kcca-brand-logo" src="../assets/brand/kcca-logo.png" alt="Kampala Capital City Authority" width="252" height="58">
      </span>
      <span class="sep" aria-hidden="true"></span>
      <span class="sys">
        <b>Vendor Relocation &amp; Space Allocation</b>
        <span>Trade Order Command System</span>
      </span>
    </div>

    <span class="spacer"></span>

    <?php if (ChangeFeed::allowed()): ?>
    <div class="feed" data-feed data-uid="<?= (int) $meId ?>">
      <button type="button" class="icon-link feed-bell" data-feed-bell aria-expanded="false" aria-haspopup="true"
              title="What colleagues have just changed" aria-label="Recent changes by colleagues">
        <?= KCCA_icon('bell', 17) ?><span class="feed-badge" data-feed-count hidden>0</span>
      </button>
      <div class="feed-panel" data-feed-panel hidden role="region" aria-label="Recent changes">
        <div class="feed-head"><b>Recent changes</b><span class="muted xsmall">by your colleagues</span></div>
        <ul class="feed-list" data-feed-list><li class="feed-empty">Loading…</li></ul>
        <?php if (Auth::can('audit')): ?><a class="feed-foot" href="audit.php">Open the full audit trail</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if (Auth::check() && in_array(Auth::role(), ['vendor', 'chairman'], true)): ?>
    <div class="feed" data-alerts data-role="<?= e(Auth::role()) ?>">
      <button type="button" class="icon-link feed-bell" data-alerts-bell aria-expanded="false" aria-haspopup="true"
              title="Alerts" aria-label="Alerts">
        <?= KCCA_icon('bell', 17) ?><span class="feed-badge" data-alerts-count hidden>0</span>
      </button>
      <div class="feed-panel" data-alerts-panel hidden role="region" aria-label="Alerts">
        <div class="feed-head"><b>Alerts</b><span class="spacer"></span>
          <button type="button" class="btn btn-quiet btn-sm" data-alerts-readall>Mark all read</button></div>
        <ul class="feed-list" data-alerts-list><li class="feed-empty">Loading…</li></ul>
      </div>
    </div>
    <?php endif; ?>
    <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false"
            aria-label="Switch to dark mode" title="Switch to dark mode">
      <span class="theme-icon theme-moon" aria-hidden="true"><?= KCCA_icon('moon', 18) ?></span>
      <span class="theme-icon theme-sun" aria-hidden="true"><?= KCCA_icon('sun', 18) ?></span>
    </button>

    <?php if (Auth::check()): ?>
      <?php
      /* Account menu. Clicking the name or role opens this; clicking
         anywhere else, or pressing Escape, closes it. Signing out is in
         here, and also reachable by keyboard. */
      $acctDivision = $layoutDivisionId > 0
          ? (string) Database::value('SELECT name FROM divisions WHERE id = ?', [$layoutDivisionId])
          : null;
      $acctRank     = Auth::rank();
      $acctRemit    = Auth::remit();
      ?>
      <div class="acct" id="acct">
        <button class="userchip acct-toggle" type="button"
                aria-haspopup="true" aria-expanded="false" aria-controls="acct-menu"
                title="<?= e($me['full_name'] ?? '') ?> — account and sign out">
          <span class="avatar"><?= e(initials((string) ($me['full_name'] ?? '?'))) ?></span>
          <span class="who">
            <b><?= e(shortName((string) ($me['full_name'] ?? 'User'))) ?></b>
            <span><?= e($acctRemit['label']) ?></span>
          </span>
          <?= KCCA_icon('chevron', 13) ?>
        </button>

        <div class="acct-menu" id="acct-menu" role="menu">
          <div class="acct-head">
            <span class="avatar lg"><?= e(initials((string) ($me['full_name'] ?? '?'))) ?></span>
            <div class="grow">
              <strong><?= e($me['full_name'] ?? '') ?></strong>
              <div class="xsmall muted"><?= e($me['title'] ?: $acctRemit['label']) ?></div>
            </div>
          </div>

          <dl class="kv">
            <dt>Signed in as</dt><dd><?= e($me['username'] ?? '') ?></dd>
            <dt>Role</dt><dd>
              <?= e($acctRemit['label']) ?>
              <span class="badge <?= $acctRank >= 6 ? 'bad' : ($acctRank >= 5 ? 'warn' : 'muted') ?>">
                level <?= $acctRank ?>
              </span>
            </dd>
            <?php if (Auth::role() === 'vendor'): ?>
              <dt>Trading at</dt><dd><?= e(Auth::user()['title'] ?: 'Not recorded') ?></dd>
            <?php else: ?>
              <dt>Directorate</dt><dd><?= e($me['directorate'] ? humanise($me['directorate']) : 'Office of the Authority') ?></dd>
            <?php endif; ?>
            <dt>Division</dt><dd><?= e($acctDivision ?: 'All five') ?></dd>
          </dl>

          <?php if (Auth::canManageUser($me)): ?>
            <a class="acct-item" href="users.php" role="menuitem">
              <?= KCCA_icon('people', 15) ?> Manage accounts
            </a>
          <?php endif; ?>
          <a class="acct-item" href="profile.php" role="menuitem">
            <?= KCCA_icon('user', 15) ?> My account
          </a>
          <button type="button" class="acct-item" data-roledash-toggle aria-controls="roledash" aria-expanded="false" role="menuitem">
            <?= KCCA_icon('gauge', 15) ?> My role dashboard
          </button>
          <a class="acct-item" href="portal.php" role="menuitem">
            <?= KCCA_icon('store', 15) ?> Trader portal
          </a>
          <a class="acct-item" href="audit.php?actor=<?= urlencode((string) ($me['full_name'] ?? '')) ?>" role="menuitem">
            <?= KCCA_icon('log', 15) ?> My audit trail
          </a>

          <div class="account-preferences">
            <span class="account-section-label">Preferences</span>
            <button type="button" class="acct-item" data-guide-toggle aria-pressed="false">
              <?= KCCA_icon('info', 15) ?> Page guidance
            </button>
            <button type="button" class="live-chip" title="Live updates"><i></i> Live updates</button>
            <?php require_once __DIR__ . '/../partials/lang_switcher.php'; ?>
          </div>

          <form method="post" action="../auth/logout.php" class="acct-form">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button type="submit" class="acct-item danger" role="menuitem">
              <?= KCCA_icon('external', 15) ?> Sign out
            </button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <a class="btn btn-primary btn-sm" href="../auth/login.php">Sign in</a>
    <?php endif; ?>
  </header>

  <!-- ================== ROLE DASHBOARD DRAWER ==================== -->
  <?php if (Auth::check()): ?>
  <div class="roledash" id="roledash" hidden aria-labelledby="roledash-title">
    <div class="roledash-sheet">
      <div class="roledash-head">
        <div class="grow">
          <div class="eyebrow" id="roledash-title"><?= e(Auth::remit()['label']) ?></div>
          <strong><?= e($me['full_name'] ?? '') ?></strong>
        </div>
        <button type="button" class="icon-link" data-roledash-close aria-label="Close the dashboard panel">
          &times;
        </button>
      </div>
      <div class="roledash-body" id="roledash-body">
        <div class="empty">
          <div class="big"><?= KCCA_icon('gauge', 28) ?></div>
          <h3>Loading your dashboard…</h3>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ============================ RAIL ============================= -->
  <button type="button" class="nav-backdrop" data-nav-dismiss aria-label="Close navigation" hidden></button>
  <nav class="rail" id="main-navigation" aria-label="Main navigation">

    <?php
    /* The rail is drawn from navigationFor(): the screens this officer's
       role needs, and nothing else. A separate permission check is not
       needed and would be wrong — the audience declared on each screen is
       the decision, and a page still guards itself when it is opened. */
    $navGroups = Auth::check() ? navigationFor(Auth::role()) : [];
    foreach ($navGroups as $navGroup):
      $navVisible = $navGroup['items'];
      if (!$navVisible) { continue; }
    ?>
      <div class="rail-group">
        <h4><button type="button" class="nav-group-toggle" aria-expanded="true"><?= e($navGroup['group']) ?><?= KCCA_icon('chevron', 12) ?></button></h4>
        <?php foreach ($navVisible as $navItem):
            $navId    = $navItem['id'];
            $navHref  = $navItem['file'];
            $navIsActive = ($navPageId === $navId);
            $isPortal = $navId === 'portal';
            // Items that have something waiting on a decision badge in the rail.
            $pill = null;
            if (Auth::check()) {
                $pill = match ($navId) {
                    'enforcement'      => (int) Database::value(
                        "SELECT COUNT(*) FROM enforcement_actions WHERE status IN ('planned','in-progress')"),
                    'recommendations'  => (int) Database::value(
                        "SELECT COUNT(*) FROM recommendations WHERE status = 'proposed'"),
                    'allocations'      => (int) Database::value(
                        "SELECT COUNT(*) FROM allocations WHERE status IN ('offered','proposed')"),
                    'locations'        => (int) Database::value(
                        "SELECT COUNT(*) FROM markets m WHERE m.vacant_spaces > 0
                           AND NOT EXISTS (SELECT 1 FROM footfall_observations o WHERE o.market_id = m.id)"),
                    'relocation'       => (int) Database::value(
                        "SELECT COUNT(*) FROM vendors WHERE status IN ('surveyed','notified')
                           AND current_zone_id IS NOT NULL"),
                    default            => 0,
                };
                if ($pill === 0) {
                    $pill = null;
                }
            }
        ?>
          <?php
             /* The rail's file paths are written relative to the site root
                ("pages/vendors.php"), which is correct from a page at the
                root but not from a page inside pages/ — where the browser
                would resolve it to /pages/pages/vendors.php and 404. APP_REL
                walks back from wherever this page happens to live, so one
                navigation list works from every depth. */
             $navHref = APP_REL . ltrim((string) $navItem['file'], '/');
          ?>
          <a href="<?= e($navHref) ?>" data-nav="<?= e($navId) ?>" class="<?= $navIsActive ? 'active' : '' ?>"
             <?= $navIsActive ? 'aria-current="page"' : '' ?><?= $isPortal ? ' target="_blank" rel="noopener"' : '' ?>>
            <?= KCCA_icon($navItem['icon'], 17) ?>
            <span><?= e($navItem['label']) ?></span>
            <?php if ($pill): ?><span class="pill"><?= $pill > 999 ? '999+' : $pill ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="rail-foot">
      <a class="site-link" href="https://www.kcca.go.ug" target="_blank" rel="noopener">
        <?= KCCA_icon('link', 13) ?> kcca.go.ug
      </a>
      <p class="xsmall muted" style="margin:9px 0 0">
        <?= e(APP_SHORT) ?> v<?= e(APP_VERSION) ?>
      </p>
    </div>
  </nav>

  <!-- ============================ MAIN ============================= -->
  <main class="main" id="main">
    <?php require __DIR__ . '/../partials/page_skeleton.php'; ?>
    <div class="content">
