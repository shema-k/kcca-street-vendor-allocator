<?php
/**
 * Shared page shell: doctype, head, top bar, sidebar, open of <main>.
 *
 * Every page includes this and then finishes with footer.php.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$f     = facts();
$stats = headlineStats();
$navPageId = currentPage();
$me    = Auth::check() ? Auth::user() : [];
$meId  = Auth::check() ? Auth::id() : 0;
$myDiv = (int) ($me['division_id'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
<meta name="kcca-csrf" content="<?= e(Auth::csrfToken()) ?>">
<meta name="kcca-root" content="<?= e(APP_REL) ?>">
<meta name="description" content="KCCA Vendor Relocation and Space Allocation System — trade order, gazetted market space and vendor relocation across Kampala Capital City.">
<meta name="author" content="Kampala Capital City Authority">
<meta name="theme-color" content="#DC351D">
<title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e(APP_SHORT) ?></title>
<link rel="icon" type="image/png" href="../assets/brand/kcca-favicon.png">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/kcca.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<div class="app">

  <!-- ============================ TOP BAR ============================ -->
  <header class="topbar">
    <button class="icon-link rail-toggle" data-rail-toggle aria-label="Toggle navigation menu" aria-expanded="false">
      <?= KCCA_icon('menu', 18) ?>
    </button>

    <div class="brand">
      <img src="../assets/brand/kcca-logo-light.png" alt="Kampala Capital City Authority logo">
      <span class="sep" aria-hidden="true"></span>
      <span class="sys">
        <b>Vendor Relocation &amp; Space Allocation</b>
        <span>Trade Order Command System</span>
      </span>
    </div>

    <span class="spacer"></span>

    <span class="tagline"><?= e($f['authority']['tagline']) ?></span>

    <a class="icon-link" href="https://www.kcca.go.ug" target="_blank" rel="noopener"
       title="Open the KCCA website" aria-label="Open the KCCA website">
      <?= KCCA_icon('external', 15) ?>
    </a>
    <a class="icon-link" href="../pages/portal.php" title="Open the public trader portal" aria-label="Public trader portal">
      <?= KCCA_icon('user', 15) ?>
    </a>

    <?php if (Auth::check()): ?>
      <?php
      /* Account menu. Clicking the name or role opens this; clicking
         anywhere else, or pressing Escape, closes it. Signing out is in
         here, and also reachable by keyboard. */
      $acctDivision = $myDiv > 0
          ? (string) Database::value('SELECT name FROM divisions WHERE id = ?', [$myDiv])
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
            <dt>Directorate</dt><dd><?= e($me['directorate'] ?: '—') ?></dd>
            <dt>Division</dt><dd><?= e($acctDivision ?: 'All five') ?></dd>
            <dt>Contact</dt>
            <dd class="xsmall" style="max-width:190px">
              <?= e($me['email'] ?: 'no email recorded') ?>
              <?php if (!empty($me['phone'])): ?><br><?= e($me['phone']) ?><?php endif; ?>
            </dd>
            <dt>Last sign-in</dt>
            <dd class="xsmall"><?= !empty($me['last_login_at']) ? e(fmtDate($me['last_login_at'], 'd M Y H:i')) : 'first sign-in' ?></dd>
          </dl>

          <div class="acct-scope">
            <span class="xsmall muted">What this account can do</span>
            <p class="small" style="margin:2px 0 0"><?= e($acctRemit['blurb']) ?></p>
          </div>

          <?php if (Auth::canManageUser($me)): ?>
            <a class="acct-item" href="users.php" role="menuitem">
              <?= KCCA_icon('people', 15) ?> Manage accounts
            </a>
          <?php endif; ?>
          <a class="acct-item" href="profile.php" role="menuitem">
            <?= KCCA_icon('user', 15) ?> My account
          </a>
          <a class="acct-item" href="audit.php?actor=<?= urlencode((string) ($me['full_name'] ?? '')) ?>" role="menuitem">
            <?= KCCA_icon('log', 15) ?> My audit trail
          </a>

          <form method="post" action="../auth/logout.php" class="acct-form">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button type="submit" class="acct-item danger" role="menuitem">
              <?= KCCA_icon('external', 15) ?> Sign out
            </button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <a class="btn btn-ghost btn-sm" href="../auth/login.php" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3)">Sign in</a>
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
  <nav class="rail" aria-label="Main navigation">    <?php foreach (navigation() as $navGroup): ?>
      <?php
      // Hide groups the signed-in role cannot reach at all.
      /* Show an item when the role permits it. The public portal has no
         permission requirement, so it is always listed. */
      $navVisible = array_filter($navGroup['items'], static function (array $item): bool {
          return $item['permission'] === null || (Auth::check() && Auth::can($item['permission']));
      });
      if (!$navVisible) {
          continue;
      }
      ?>
      <div class="rail-group">
        <h4><?= e($navGroup['group']) ?></h4>
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
          <a href="<?= e($navHref) ?>" class="<?= $navIsActive ? 'active' : '' ?>"
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
    <div class="content">