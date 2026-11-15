<?php
/**
 * Dashboard section: my applications (vendor).
 *
 * Every stall this trader has asked for, and what the Authority decided.
 * Requests are matched to the trader by their telephone number, which is
 * what the request form collects — so this works whether the trader is
 * signed in as a registered vendor or applied from the public portal.
 */

declare(strict_types=1);

$phone = preg_replace('/[^0-9+]/', '', (string) (Auth::user()['phone'] ?? ''));

$requests = $phone === '' ? [] : Database::all(
    "SELECT sr.reference, sr.status, sr.reason, sr.decision_note, sr.created_at,
            sr.decided_at, s.space_code, s.section, m.name AS market_name,
            v.vendor_code
       FROM stall_requests sr
       JOIN market_spaces s ON s.id = sr.space_id
       JOIN markets m ON m.id = sr.market_id
       LEFT JOIN vendors v ON v.id = sr.vendor_id
      WHERE sr.phone = ?
      ORDER BY sr.created_at DESC LIMIT 20",
    [$phone]
);

$counts = ['pending' => 0, 'reviewing' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($requests as $r) {
    $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
}
?>
<div class="card mb-2">
  <div class="card-head">
    <div class="grow">
      <h2>My applications</h2>
      <p class="desc">
        <?php if ($phone === ''): ?>
          Add a telephone number to <a href="profile.php">your account</a> and the Authority can match
          your applications to you.
        <?php else: ?>
          Matched to <?= e($phone) ?>. A request holds the pitch while the Authority decides.
        <?php endif; ?>
      </p>
    </div>
    <a class="btn btn-ghost btn-sm" href="market_map.php">Apply for another</a>
  </div>
  <div class="card-body">
    <?php if ($requests): ?>
      <div class="grid g4 mb-2">
        <div class="stat <?= $counts['pending'] > 0 ? 'warn' : '' ?>">
          <div class="label">Pending</div>
          <div class="value" style="font-size:21px"><?= num($counts['pending']) ?></div>
        </div>
        <div class="stat">
          <div class="label">Under review</div>
          <div class="value" style="font-size:21px"><?= num($counts['reviewing']) ?></div>
        </div>
        <div class="stat green">
          <div class="label">Approved</div>
          <div class="value" style="font-size:21px"><?= num($counts['approved']) ?></div>
        </div>
        <div class="stat bad">
          <div class="label">Not approved</div>
          <div class="value" style="font-size:21px"><?= num($counts['rejected']) ?></div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data compact">
          <thead>
            <tr><th>Reference</th><th>Pitch</th><th>Market</th><th>Asked on</th>
                <th class="center">Decision</th><th>What the Authority said</th></tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
              <tr>
                <td class="mono xsmall"><?= e($r['reference']) ?></td>
                <td class="mono xsmall"><?= e($r['space_code']) ?><div class="xsmall muted"><?= e($r['section']) ?></div></td>
                <td class="xsmall"><?= e($r['market_name']) ?></td>
                <td class="xsmall"><?= e(fmtDate($r['created_at'], 'd M Y')) ?></td>
                <td class="center"><span class="badge <?= e(statusTone($r['status'])) ?>"><?= e(humanise($r['status'])) ?></span></td>
                <td class="xsmall muted" style="max-width:230px">
                  <?= e($r['decision_note'] ?: ($r['reason'] ?: '—')) ?>
                  <?php if ($r['vendor_code']): ?>
                    <div class="xsmall">Vendor record: <?= e($r['vendor_code']) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty">
        <div class="big"><?= KCCA_icon('list', 28) ?></div>
        <h3>No applications yet</h3>
        <p>Open a market's stall map, pick a free pitch, and ask for it.</p>
        <a class="btn btn-primary btn-sm" href="market_map.php">Find a stall</a>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    Asking for a pitch does not take it. The Authority checks the request against the vendor register
    and confirms. If it is not approved, the pitch goes back and the reason is recorded.
  </div>
</div>