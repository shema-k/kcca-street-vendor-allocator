<?php
/**
 * KCCA Online Services — every official KCCA platform, in one place.
 * The main onward link the system gives its users.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requirePermission('view');

$f      = facts();
$a      = $f['authority'];
$services = Database::all('SELECT * FROM kcca_services ORDER BY category, sort_order, name');

$byCategory = [];
foreach ($services as $s) {
    $byCategory[$s['category']][] = $s;
}

$categoryMeta = [
    'Revenue'      => ['icon' => 'store',       'desc' => 'Pay KCCA bills, taxes, fees and trading licence charges online.'],
    'Planning'     => ['icon' => 'doc',         'desc' => 'Development permissions, plan approvals and geospatial data.'],
    'Citizen'      => ['icon' => 'mail',        'desc' => 'Send feedback, report a problem, or locate a facility.'],
    'Trade'        => ['icon' => 'list',        'desc' => 'Directories and registers relevant to trading and business.'],
    'Environment'  => ['icon' => 'layers',      'desc' => 'Air quality, city trees and illegal construction reporting.'],
    'General'      => ['icon' => 'building',    'desc' => 'News, publications, tenders and tourism.'],
    'Internal'     => ['icon' => 'shield',      'desc' => 'Authority intranet, for staff.'],
];

$pageTitle = 'KCCA Online Services';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-head">
  <div class="grow">
    <div class="breadcrumb">
      <a href="dashboard.php">Command</a><span class="sep">/</span>KCCA Online Services
    </div>
    <div class="eyebrow">Public and Corporate Affairs</div>
    <h1>KCCA Online Services</h1>
    <p>
      Every official KCCA platform, linked from one place. These are the Authority's own systems at
      <a href="<?= e($a['website']) ?>" target="_blank" rel="noopener"><?= e($a['website']) ?></a> —
      this system does not replace them, it routes people to them.
    </p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="<?= e($a['website']) ?>" target="_blank" rel="noopener">
      Open kcca.go.ug <?= KCCA_icon('external', 14) ?>
    </a>
    <button class="btn btn-ghost" data-print><?= KCCA_icon('print', 15) ?> Print</button>
  </div>
</div>

<!-- ============================ CONTACT ========================= -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Reach the Authority</h2>
      <p class="desc">City Hall, <?= e($a['address']) ?></p>
    </div>
  </div>
  <div class="card-body">
    <div class="grid g4">
      <a class="stat" href="tel:<?= e(preg_replace('/\s+/', '', $a['telephone'])) ?>">
        <div class="label">Telephone</div>
        <div class="value" style="font-size:19px"><?= e($a['telephone']) ?></div>
        <div class="meta">General enquiries</div>
      </a>
      <a class="stat" href="tel:<?= e(preg_replace('/\s+/', '', $a['toll_free'])) ?>">
        <div class="label">Toll free</div>
        <div class="value" style="font-size:19px"><?= e($a['toll_free']) ?></div>
        <div class="meta">Free from any network</div>
      </a>
      <a class="stat" href="mailto:<?= e($a['email']) ?>">
        <div class="label">Email</div>
        <div class="value" style="font-size:19px"><?= e($a['email']) ?></div>
        <div class="meta">General enquiries</div>
      </a>
      <a class="stat" href="https://wa.me/<?= e(preg_replace('/\D/', '', $a['whatsapp'])) ?>" target="_blank" rel="noopener">
        <div class="label">WhatsApp</div>
        <div class="value" style="font-size:19px"><?= e($a['whatsapp']) ?></div>
        <div class="meta">Direct message</div>
      </a>
    </div>
    <div class="row mt-2">
      <span class="badge info">SMS code <?= e($a['sms_code']) ?></span>
      <a class="badge brand" href="https://www.kcca.go.ug/citizen-feedback" target="_blank" rel="noopener">
        Citizen feedback portal
      </a>
      <a class="badge brand" href="https://www.kcca.go.ug/contact-us" target="_blank" rel="noopener">
        Contact the Authority
      </a>
      <a class="badge brand" href="https://www.kcca.go.ug/open-gov" target="_blank" rel="noopener">
        Open government data
      </a>
    </div>
  </div>
</div>

<!-- ======================= SERVICES BY CATEGORY ================= -->
<?php foreach ($byCategory as $category => $items):
    $meta = $categoryMeta[$category] ?? ['icon' => 'link', 'desc' => ''];
?>
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2><?= KCCA_icon($meta['icon'], 17) ?> <?= e($category) ?></h2>
      <p class="desc"><?= e($meta['desc']) ?></p>
    </div>
    <span class="badge muted"><?= count($items) ?> service<?= count($items) === 1 ? '' : 's' ?></span>
  </div>
  <div class="card-body">
    <div class="grid g3">
      <?php foreach ($items as $s): ?>
        <a class="criterion" href="<?= e($s['url']) ?>" target="_blank" rel="noopener"
           style="text-decoration:none;display:block">
          <span class="cl"><?= e($s['name']) ?></span>
          <span class="cv"><?= KCCA_icon('external', 14) ?></span>
          <span class="cn"><?= e($s['purpose']) ?></span>
          <span class="cw"><?= e(parse_url($s['url'], PHP_URL_HOST)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- ======================== LEADERSHIP =========================== -->
<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Political and technical leadership</h2>
        <p class="desc">As published on the Authority's site.</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="https://www.kcca.go.ug/about-the-authority" target="_blank" rel="noopener">
        About the Authority
      </a>
    </div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Office</th><th>Name</th><th>Title</th></tr></thead>
          <tbody>
            <?php foreach ($f['leadership'] as $l): ?>
              <tr>
                <td class="xsmall"><?= e($l['office']) ?></td>
                <td><strong><?= e($l['name']) ?></strong></td>
                <td class="xsmall muted"><?= e($l['title']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="grow">
        <h2>Directorates</h2>
        <p class="desc">Where each part of this system is owned inside the Authority.</p>
      </div>
    </div>
    <div class="card-body">
      <div class="grid g2">
        <?php
        $owners = [
            'physical-planning' => 'Congestion scoring, zoning, the relocation engine',
            'public-health'     => 'Food hygiene, waste management at markets',
            'legal'             => 'Notices, ultimatums, enforcement, litigation hold',
            'revenue'           => 'Trading licences, market fees, eCitie',
            'internal-audit'    => 'Audit trail, decision records, internal control',
            'gender'            => 'Gender-responsive allocation, youth and PWD considerations',
            'education'         => 'Trader awareness and community sensitisation',
            'engineering'       => 'Market construction, drainage, upgrading works',
        ];
        ?>
        <?php foreach ($f['directorates'] as $d):
            $purpose = $owners[$d['slug']] ?? null;
        ?>
          <div class="criterion">
            <span class="cl" style="font-size:12.5px"><?= e($d['name']) ?></span>
            <?php if ($purpose): ?>
              <span class="cn"><?= e($purpose) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-foot">
      Physical Planning's mandate covers “the zoning, land subdivision and demarcating areas for
      development”, which is where gazetted trading space is defined.
    </div>
  </div>
</div>

<!-- ========================= SOCIAL MEDIA ======================== -->
<div class="card">
  <div class="card-head">
    <div class="grow">
      <h2>Follow the Authority</h2>
      <p class="desc">Official channels for statements on trade order and city developments.</p>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <?php
      $social = [
          ['Facebook', 'https://facebook.com/kccaug'],
          ['X (Twitter)', 'https://twitter.com/KCCAUG'],
          ['Instagram', 'https://instagram.com/kccaug'],
          ['YouTube', 'https://youtube.com/kccaug'],
          ['Flickr', 'https://flickr.com/kccaug'],
      ];
      foreach ($social as [$name, $url]): ?>
        <a class="btn btn-ghost" href="<?= e($url) ?>" target="_blank" rel="noopener">
          <?= e($name) ?> <?= KCCA_icon('external', 13) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';