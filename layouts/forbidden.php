<?php
/**
 * 403 — the signed-in role does not permit what they asked for.
 * Rendered by Auth::requirePermission(); it is a full standalone page so it
 * does not depend on the requesting page's own state.
 */

$f      = facts();
$me     = Auth::user();
$role   = Auth::role();
$a      = $f['authority'];
$pageTitle = 'Access not permitted';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Access not permitted · <?= e(APP_SHORT) ?></title>
<link rel="icon" type="image/png" href="../assets/brand/kcca-favicon.png">
<link rel="stylesheet" href="../assets/css/fonts.css">
<link rel="stylesheet" href="../assets/css/kcca.css">
</head>
<body>
<main class="login-main" style="min-height:100vh">
  <div class="login-card" style="max-width:520px">
    <img class="login-logo" src="../assets/brand/kcca-logo.png" alt="Kampala Capital City Authority">

    <div class="alert alert-bad" style="margin-bottom:20px">
      <strong>Access not permitted.</strong> Your role does not carry this permission.
    </div>

    <h2>Signed in as <?= e($me['full_name'] ?? 'unknown') ?></h2>
    <p class="sub">
      Role: <strong><?= e(humanise($role)) ?></strong><?php
        if (!empty($me['directorate'])): ?> · <?= e($me['directorate']) ?><?php endif; ?>
    </p>

    <p class="small">
      This is a deliberate control, not a fault. The system grants permissions by role so that a
      decision affecting a trader’s livelihood is taken by someone accountable for it. If you need
      this screen, ask the System Administrator to review your role.
    </p>

    <div class="row mt-2">
      <a class="btn btn-primary" href="../pages/executive.php">Back to the dashboard</a>
      <a class="btn btn-ghost" href="../pages/portal.php">Who to contact</a>
      <a class="btn btn-quiet" href="../auth/logout.php">Sign out</a>
    </div>

    <p class="xsmall muted mt-3" style="margin-bottom:0">
      <?= e($a['legal_name']) ?> · <?= e($a['telephone']) ?> ·
      <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a>
    </p>
  </div>
</main>
</body>
</html>