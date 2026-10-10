<?php
/**
 * Trade Licensing — the register's licence position and the legal basis.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f = facts();
$a = $f['authority'];

$licStats = Database::one(
    "SELECT COUNT(*) AS total,
            SUM(has_trading_licence) AS licensed,
            SUM(licence_class = 'street-grade-1') AS grade1,
            SUM(licence_class = 'street-grade-2') AS grade2,
            SUM(licence_class = 'market-standard') AS standard,
            SUM(licence_class = 'unlicensed') AS unlicensed,
            MIN(licence_year) AS oldest,
            MAX(licence_year) AS newest
       FROM vendors"
);

/* Where licences are already held, by division. */
$byDivision = Database::all(
    "SELECT d.name, d.code,
            COUNT(v.id) AS total,
            SUM(v.has_trading_licence) AS licensed
       FROM divisions d LEFT JOIN vendors v ON v.division_id = d.id
      GROUP BY d.id ORDER BY d.name"
);

$unlicensed = Database::all(
    "SELECT v.*, d.name AS division_name, z.name AS zone_name
       FROM vendors v
       JOIN divisions d ON d.id = v.division_id
       LEFT JOIN trade_zones z ON z.id = v.current_zone_id
      WHERE v.has_trading_licence = 0
      ORDER BY v.monthly_turnover_ugx DESC LIMIT 40"
);

$total = (int) ($licStats['total'] ?? 0);
$licensed = (int) ($licStats['licensed'] ?? 0);

$pageTitle = 'Trade Licensing';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>Trade Licensing
    </div>
    <div class="eyebrow">Revenue Collection · Trade Licensing Act Cap 101</div>
    <h1>Trade Licensing</h1>
    <p>
      The licence position across the vendor register, and what the Authority requires.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="https://ecitie.kcca.go.ug" target="_blank" rel="noopener">
      Pay online <?= KCCA_icon('external', 13) ?>
    </a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ========================= LICENCE POSITION =================== -->
<div class="grid g5 mb-2">
  <div class="stat">
    <div class="label">Vendors surveyed</div>
    <div class="value"><?= num($total) ?></div>
    <div class="meta">in the register</div>
  </div>
  <div class="stat ok">
    <div class="label">Licensed</div>
    <div class="value"><?= num($licensed) ?></div>
    <div class="meta"><?= pct($total > 0 ? $licensed / $total * 100 : 0) ?> hold a current licence</div>
  </div>
  <div class="stat bad">
    <div class="label">Unlicensed</div>
    <div class="value"><?= num((int) ($licStats['unlicensed'] ?? 0)) ?></div>
    <div class="meta">trading without a licence</div>
  </div>
  <div class="stat info">
    <div class="label">Street grade 1</div>
    <div class="value"><?= num((int) ($licStats['grade1'] ?? 0)) ?></div>
    <div class="meta">large stock</div>
  </div>
  <div class="stat gold">
    <div class="label">Street grade 2</div>
    <div class="value"><?= num((int) ($licStats['grade2'] ?? 0)) ?></div>
    <div class="meta">small enterprise</div>
  </div>
</div>

<div class="grid g-2-1">
  <div>
    <!-- What is required -->
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>How to get a trading licence</h2>
          <p class="desc">As published by the Authority in its trading licence guidance.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="https://www.kcca.go.ug/faqs-trading-license" target="_blank" rel="noopener">
          Official FAQ <?= KCCA_icon('external', 12) ?>
        </a>
      </div>
      <div class="card-body">
        <div class="grid g2">
          <div>
            <h4 class="mb-1">What you must produce</h4>
            <ul class="reasons">
              <?php foreach ($f['licence_requirements'] as $req): ?>
                <li><?= e($req) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h4 class="mb-1">How it is assessed and paid</h4>
            <ol class="reasons" style="list-style:none;padding:0">
              <li>Apply to KCCA for a trading licence to do business.</li>
              <li>A Revenue Officer assesses the fee and issues a KCCA Payment Advice Form.</li>
              <li>Pay at any designated bank.</li>
              <li>Present the receipt at the division office; the licence certificate is issued.</li>
            </ol>
            <div class="divider"></div>
            <h4 class="mb-1">Conditions of the licence</h4>
            <ul class="reasons">
              <li>Appropriately located premises</li>
              <li>Appropriate hygiene and health standard of premises</li>
              <li>Appropriate building standard of premises</li>
              <li>Required personal specifications, such as medical fitness where consumables are vended</li>
              <li>Payment of the appropriate licence fee</li>
            </ul>
            <p class="xsmall muted mt-1" style="margin-bottom:0">
              A trading licence is valid for <strong>twelve months</strong> from the date of issue and
              the fee is payable before commencement of business.
            </p>
          </div>
        </div>

        <div class="source mt-2">
          <span class="lbl">source</span>
          <span>
            Trade Licensing Act, Cap 101, as amended 2000 and 2015 · KCCA trading licence guidance ·
            Kampala Capital City Act 2010.
          </span>
          <a href="https://www.kcca.go.ug/uDocs/Trade_Licence_FAQs.pdf" target="_blank" rel="noopener" class="right">
            Official PDF
          </a>
        </div>
      </div>
    </div>

    <!-- Grade structure -->
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>Street trade licence grades</h2>
          <p class="desc">
            The KCCA (Regulation of Street Trade) Ordinance provides graded street trade licences —
            grade one for traders with larger stock, grade two for small enterprises.
          </p>
        </div>
      </div>
      <div class="card-body tight">
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr><th>Grade</th><th>Who it is for</th><th class="right">Vendors holding it</th><th>Note</th></tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="badge brand">Grade 1</span></td>
                <td>Traders with large business stock</td>
                <td class="right num"><?= num((int) ($licStats['grade1'] ?? 0)) ?></td>
                <td class="small muted">
                  Fee structure set by the Ordinance. Lord Mayor Erias Lukwago proposed reductions
                  from the levels originally drafted during the 2019 deliberations; the Authority
                    committed to a substantial reduction in the fee.
                </td>
              </tr>
              <tr>
                <td><span class="badge gold">Grade 2</span></td>
                <td>Small enterprises</td>
                <td class="right num"><?= num((int) ($licStats['grade2'] ?? 0)) ?></td>
                <td class="small muted">The majority of the register sits at this level.</td>
              </tr>
              <tr>
                <td><span class="badge ok">Market standard</span></td>
                <td>Traders holding gazetted market space</td>
                <td class="right num"><?= num((int) ($licStats['standard'] ?? 0)) ?></td>
                <td class="small muted">The standard licence once a vendor is in a registered market.</td>
              </tr>
              <tr>
                <td><span class="badge bad">Unlicensed</span></td>
                <td>Trading without a current licence</td>
                <td class="right num"><?= num((int) ($licStats['unlicensed'] ?? 0)) ?></td>
                <td class="small muted">
                  A licence is a legal requirement for anyone selling goods or services in KCCA's
                  jurisdiction. A licence does not, however, excuse unlawful occupation of public space —
                  which is a separate offence with a separate remedy.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-foot">Fees are set by the Authority and reviewed from time to time.</div>
    </div>

    <!-- Unlicensed vendors -->
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>Vendors trading without a licence</h2>
          <p class="desc">
            The <?= num((int) ($licStats['unlicensed'] ?? 0)) ?> unlicensed traders, highest turnover first.
            An unlicensed trader is also the most fragile relocation: they have the least capital
            buffer, which is why the engine weights them heavily on equity.
          </p>
        </div>
        <a class="btn btn-ghost btn-sm" href="vendors.php?status=surveyed">All surveyed vendors</a>
      </div>
      <div class="card-body tight">
        <div class="table-wrap" style="max-height:400px;overflow-y:auto">
          <table class="data compact">
            <thead>
              <tr><th>Vendor</th><th>Trade</th><th>Division</th><th>Site</th><th class="right">Turnover</th><th class="right">Capital</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($unlicensed as $v): ?>
                <tr>
                  <td>
                    <strong><?= e($v['full_name']) ?></strong>
                    <div class="xsmall muted"><?= e($v['vendor_code']) ?></div>
                  </td>
                  <td class="xsmall"><?= e(humanise($v['business_category'])) ?></td>
                  <td class="xsmall"><?= e($v['division_name']) ?></td>
                  <td class="xsmall"><?= e($v['zone_name'] ?: '—') ?></td>
                  <td class="right num xsmall"><?= ugxShort((int) $v['monthly_turnover_ugx']) ?></td>
                  <td class="right num xsmall"><?= ugxShort((int) $v['startup_capital_ugx']) ?></td>
                  <td class="right">
                    <a class="btn btn-ghost btn-sm" href="relocation.php?vendor=<?= (int) $v['id'] ?>">Match</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ======================= SIDE COLUMN ======================= -->
  <div>
    <div class="card">
      <div class="card-head"><div class="grow"><h2>Coverage by division</h2></div></div>
      <div class="card-body">
        <svg class="chart" data-chart='<?= ejs([
          'type' => 'bar',
          'opts' => [
            'height' => 200,
            'stacked' => true,
            'labels' => array_map(static fn($r) => $r['name'], $byDivision),
            'series' => [
              ['name' => 'Licensed', 'color' => '#1B7F4D',
               'values' => array_map(static fn($r) => (int) $r['licensed'], $byDivision)],
              ['name' => 'Unlicensed', 'color' => '#C0261A',
               'values' => array_map(static fn($r) => (int) $r['total'] - (int) $r['licensed'], $byDivision)],
            ],
            'yFormat' => 'num',
          ],
        ]) ?>' aria-label="Licence coverage by division"></svg>
        <div class="legend">
          <span class="item"><span class="sw" style="background:#1B7F4D"></span> Licensed</span>
          <span class="item"><span class="sw" style="background:#C0261A"></span> Unlicensed</span>
        </div>
      </div>
      <div class="card-foot">
        Licence and occupation are separate questions.
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Pay online</h2></div></div>
      <div class="card-body">
        <div class="stack-s">
          <a class="btn btn-ghost btn-block" href="https://ecitie.kcca.go.ug" target="_blank" rel="noopener">
            eCitie — online payment
          </a>
          <a class="btn btn-ghost btn-block" href="https://ecitie2.kcca.go.ug" target="_blank" rel="noopener">
            eCitie service centre
          </a>
          <a class="btn btn-ghost btn-block" href="https://city.iras.go.ug" target="_blank" rel="noopener">
            IRAS — taxes and fees
          </a>
          <a class="btn btn-ghost btn-block" href="https://appointment.kcca.go.ug" target="_blank" rel="noopener">
            Book an appointment
          </a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Exemptions</h2></div></div>
      <div class="card-body">
        <p class="small">
          Section 8(2) of the Trade Licensing Act exempts certain trades. The principal exemptions are:
        </p>
        <ul class="reasons">
          <li>Any trade carried on in a market</li>
          <li>The trade of a planter, farmer, gardener, dairy person or agriculturist, for sale of their own produce</li>
          <li>Goods bona fide made by the person selling them by their own handicraft, in their normal place of residence</li>
        </ul>
        <p class="xsmall muted" style="margin-bottom:0">
          A street trader is not within any of these exemptions: the goods are not the trader's own
          produce and are not their own handicraft.
        </p>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Revenue contacts</h2></div></div>
      <div class="card-body">
        <dl class="kv">
          <dt>Tel</dt><dd><?= e($a['telephone']) ?></dd>
          <dt>Toll free</dt><dd><?= e($a['toll_free']) ?></dd>
          <dt>SMS code</dt><dd><?= e($a['sms_code']) ?></dd>
          <dt>Email</dt><dd><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></dd>
        </dl>
        <div class="divider"></div>
        <p class="xsmall muted" style="margin:0">
          A Revenue Officer assesses the fee and issues the KCCA Payment Advice Form. Payment is made
          at any designated bank; the receipt is then presented at the division office where the
          trading licence certificate is issued.
        </p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';