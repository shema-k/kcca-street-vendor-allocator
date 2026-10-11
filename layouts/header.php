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
/* Namespaced: a page (profile.php) already uses $myDiv for the
   division row it fetched, and this line would silently replace it
   with an integer. */
$layoutDivisionId = (int) ($me['division_id'] ?? 0);
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
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/activities.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(APP_REL) ?>assets/css/dark.css?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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

    <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false"
            aria-label="Switch to dark mode" title="Switch to dark mode">
      <?= KCCA_icon('moon', 14) ?><span class="moon-text" aria-hidden="true">Dark</span>
      <?= KCCA_icon('sun', 14) ?><span class="sun-text" aria-hidden="true">Light</span>
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
            <dt>Contact</dt>
            <dd class="xsmall" style="max-width:190px">
              <?= e($me['email'] ?: 'no email recorded') ?>
              <?php if (!empty($me['phone'])): ?><br><?= e($me['phone']) ?><?php endif; ?>
            </dd>
            <dt>Last sign-in</dt>
            <dd class="xsmall"><?= !empty($me['last_login_at']) ? e(fmtDate($me['last_login_at'], 'd M Y H:i')) : 'first sign-in' ?></dd>
          </dl>

          <?php
          /* The audit trail attributes every action to a name, so a reader
             is entitled to know whether that name is published by KCCA or
             is an illustrative officer standing in for one. */
          $nameBasis = (string) ($me['name_basis'] ?? 'illustrative');
          ?>
          <p class="xsmall muted" style="margin:0 0 4px">
            <?php if ($nameBasis === 'published'): ?>
              Name and position published by KCCA.
            <?php else: ?>
              Illustrative officer. KCCA publishes this
              <?= e($me['directorate'] ? humanise($me['directorate']) : 'directorate') ?>
              but not the name of the person who heads it.
            <?php endif; ?>
          </p>

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
  <nav class="rail" aria-label="Main navigation">
    <?php if (Auth::check()): ?>
      <?php
      /* The authority level is stated at the top of the rail because it is
         the reason the rail looks the way it does: each role sees the
         screens its span of control covers. Stating it once answers the
         question before it is asked. */
      $railRole   = Auth::remit()['label'];
      $railRank   = Auth::rank();
      $railScope  = Auth::remit()['scope'];
      $railScopeText = match ($railScope) {
          'system'      => 'Whole system',
          'citywide'    => 'All five divisions',
          'division'    => $layoutDivisionId > 0 ? (string) Database::value('SELECT name FROM divisions WHERE id = ?', [$layoutDivisionId]) . ' Division' : 'Own division',
          'directorate' => Auth::user()['directorate']
                            ? humanise((string) Auth::user()['directorate'])
                            : ucfirst(Auth::role()),
          'vendor'      => 'Trader',
          default       => 'Read only',
      };
      ?>
      <div class="rail-level">
        <span class="lvl" title="Level <?= $railRank ?> of 7 in the order of authority"><?= $railRank ?></span>
        <span class="meta">
          <b><?= e($railRole) ?></b>
          <span><?= e($railScopeText) ?></span>
        </span>
      </div>
    <?php endif; ?>
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