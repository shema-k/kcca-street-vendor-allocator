<?php
/**
 * Public trader portal — no sign-in.
 *
 * A vendor looking for gazetted space: what is available, what the rules
 * are, how to get a trading licence, and where to make a complaint. Plain
 * language, official citations, and the same figures the Authority publishes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$f       = facts();
$vac     = $f['vacancy'];
$city    = $f['city'];
$cat     = (string) ($_GET['category'] ?? '');
$div     = (string) ($_GET['division'] ?? '');

$where  = ['m.vacant_spaces > 0', "m.status IN ('operational','upgrading')"];
$params = [];
if ($cat !== '' && in_array($cat, ['public', 'private'], true)) {
    $where[] = 'm.category = :cat';
    $params['cat'] = $cat;
}
if ($div !== '') {
    $where[] = 'd.code = :div';
    $params['div'] = $div;
}

$markets = Database::all(
    "SELECT m.*, d.name AS division_name
       FROM markets m JOIN divisions d ON d.id = m.division_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY m.vacant_spaces DESC, m.name LIMIT 60",
    $params
);

$publicVacant  = (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='public'");
$privateVacant = (int) Database::value("SELECT COALESCE(SUM(vacant_spaces),0) FROM markets WHERE category='private'");
$a = $f['authority'];

$pageTitle = 'Trader Portal';
require __DIR__ . '/../layouts/header.php';
?>

<!-- ========================= HERO ============================== -->
<div class="portal-hero">
  <h1>Find gazetted space for your business</h1>
  <p>
    The Authority is clearing streets, pavements and verandas of unlawful vending and moving traders
    into gazetted market space. It has identified
    <strong><?= num($vac['public_vacant_spaces']) ?> spaces in <?= num($vac['public_markets']) ?> public markets</strong>
    and at least <strong><?= num($vac['private_vacant_spaces']) ?> in <?= num($vac['private_markets']) ?> private markets</strong>.
  </p>

  <div class="facts">
    <div>
      <span class="n"><?= num($vac['total_vacant']) ?></span>
      <span class="l">Spaces available</span>
    </div>
    <div>
      <span class="n">UGX 0</span>
      <span class="l">Fee on these spaces</span>
    </div>
    <div>
      <span class="n"><?= num($city['divisions']) ?></span>
      <span class="l">Divisions to apply in</span>
    </div>
    <div>
      <span class="n"><?= num($vac['public_markets'] + $vac['private_markets']) ?></span>
      <span class="l">Markets in the register</span>
    </div>
  </div>
</div>

<div class="grid g-3-2">
  <!-- ======================= MAIN COLUMN ======================= -->
  <div>
    <!-- Available space -->
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>Space available right now</h2>
          <p class="desc">Allocation is first-come, first-served. Bring your national ID to the division office.</p>
        </div>
      </div>

      <form class="filters" method="get">
        <div class="field">
          <label for="category">Type of market</label>
          <select name="category" id="category" data-autosubmit>
            <option value="">All markets</option>
            <option value="public"  <?= $cat === 'public'  ? 'selected' : '' ?>>Public markets (KCCA)</option>
            <option value="private" <?= $cat === 'private' ? 'selected' : '' ?>>Private markets</option>
          </select>
        </div>
        <div class="field">
          <label for="division">Division</label>
          <select name="division" id="division" data-autosubmit>
            <option value="">All five divisions</option>
            <?php foreach (Database::all('SELECT code, name FROM divisions ORDER BY name') as $d): ?>
              <option value="<?= e($d['code']) ?>" <?= $div === $d['code'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <a class="btn btn-ghost" href="portal.php">Reset</a>
      </form>

      <div class="card-body tight">
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th>Market</th><th>Division</th><th>Parish</th>
                <th class="right">Spaces free</th><th class="center">Water</th>
                <th class="center">Toilets</th><th class="center">Fee</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($markets as $m): ?>
                <tr>
                  <td>
                    <strong><?= e($m['name']) ?></strong>
                    <div class="xsmall muted">
                      <span class="badge <?= $m['category'] === 'public' ? 'green' : 'gold' ?>" style="font-size:10px">
                        <?= $m['category'] === 'public' ? 'KCCA market' : 'Private market' ?>
                      </span>
                      <?= e($m['code']) ?>
                    </div>
                  </td>
                  <td class="xsmall"><?= e($m['division_name']) ?></td>
                  <td class="xsmall"><?= e($m['parish'] ?: '—') ?></td>
                  <td class="right num strong"><?= num((int) $m['vacant_spaces']) ?></td>
                  <td class="center"><?= $m['water_available'] ? '<span class="badge ok">Yes</span>' : '<span class="badge muted">No</span>' ?></td>
                  <td class="center"><?= $m['toilets'] ? '<span class="badge ok">Yes</span>' : '<span class="badge muted">No</span>' ?></td>
                  <td class="center"><span class="badge ok">Free</span></td>
                  <td class="right">
                    <?php if ($m['category'] === 'public'): ?>
                      <a class="btn btn-ghost btn-sm" href="market_map.php?market=<?= (int) $m['id'] ?>">
                        See the pitches
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if (!$markets): ?>
        <div class="empty">
          <div class="big"><?= KCCA_icon('store', 32) ?></div>
          <h3>No market matches that filter</h3>
          <p>Try “All markets” or a different division. Space is added regularly.</p>
          <a class="btn btn-ghost" href="portal.php">Show everything</a>
        </div>
      <?php endif; ?>
      <div class="card-foot">
        <?= num(count($markets)) ?> market<?= count($markets) === 1 ? '' : 's' ?> shown.
        Citywide there are <?= num($publicVacant) ?> vacant spaces in public markets and
        <?= num($privateVacant) ?> in private markets.
      </div>
    </div>

    <!-- How to get a pitch -->
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>How to get a pitch</h2>
          <p class="desc">Four steps. Nothing in them costs money.</p>
        </div>
      </div>
      <div class="card-body">
        <div class="step">
          <span class="n">1</span>
          <span class="bd">
            <h4>Walk into your division office</h4>
            <p>
              The City Hall is at <?= e($a['address']) ?>. Bring your national ID and tell them you
              are a trader looking for gazetted space. Ask for the Market Administration desk.
            </p>
          </span>
        </div>
        <div class="step">
          <span class="n">2</span>
          <span class="bd">
            <h4>Get your trading licence</h4>
            <p>
              A trading licence is a legal requirement for anyone selling goods or services in KCCA.
              It is valid for twelve months. A Revenue Officer assesses the fee and issues a payment
              advice form; pay at any designated bank; bring the receipt back and your licence is issued.
            </p>
          </span>
        </div>
        <div class="step">
          <span class="n">3</span>
          <span class="bd">
            <h4>Be allocated a pitch</h4>
            <p>
              Space is allocated first-come, first-served. For the vacant spaces the Authority has
              identified, there is no charge. You will be given a pitch number and a section.
            </p>
          </span>
        </div>
        <div class="step">
          <span class="n">4</span>
          <span class="bd">
            <h4>Trade lawfully, and manage your waste</h4>
            <p>
              Trading from your pitch keeps the footpath, the road and the market entrance clear for
              everyone. Managing your own waste is part of the deal — a trading licence does not transfer
              that responsibility to KCCA.
            </p>
          </span>
        </div>

        <div class="quote mt-2">
          <p>“We want more traders to come and join us… People should not remain on the streets when
             stalls are available.”</p>
          <footer><b>Nalwanga Lwanga</b>, vendor, Busega Market · quoted in KCCA news 1078</footer>
        </div>
      </div>
    </div>

    <!-- Why -->
    <div class="card">
      <div class="card-head">
        <div class="grow">
          <h2>Why the Authority is doing this</h2>
          <p class="desc">So the reasoning is clear and it is not personal.</p>
        </div>
      </div>
      <div class="card-body">
        <p>
          Unlawful vending on pavements and verandas blocks footpaths, narrows roads, slows emergency
          vehicles and puts food on the ground where it cannot be kept clean. KCCA's duty under the
          Kampala Capital City Act is to keep the city working for everyone — and that includes the
          traders themselves, who lose customers when the road is impassable.
        </p>
        <div class="grid g2 mt-1">
          <div class="criterion">
            <span class="cl">For the city</span>
            <span class="cv"><?= KCCA_icon('check', 16) ?></span>
            <span class="cn">Wider footpaths, faster journeys, working drains, safer crossings.</span>
          </div>
          <div class="criterion">
            <span class="cl">For the trader</span>
            <span class="cv"><?= KCCA_icon('check', 16) ?></span>
            <span class="cn">A permanent pitch, water, toilets, waste collection, and a licence.</span>
          </div>
          <div class="criterion">
            <span class="cl">For the customer</span>
            <span class="cv"><?= KCCA_icon('check', 16) ?></span>
            <span class="cn">Food sold from a clean surface rather than a tarpaulin on the ground.</span>
          </div>
          <div class="criterion">
            <span class="cl">For the market</span>
            <span class="cv"><?= KCCA_icon('check', 16) ?></span>
            <span class="cn">Space that has been paid for and built, finally used.</span>
          </div>
        </div>
        <p class="small muted mt-2" style="margin-bottom:0">
          “We have over 550 free spaces in our markets… Let us fill these spaces in all the public
          markets in the city.” <strong>— Hajjat Buzeki Sharifah, Executive Director, KCCA</strong>
        </p>
      </div>
    </div>
  </div>

  <!-- ======================= SIDE COLUMN ======================= -->
  <div>
    <div class="card">
      <div class="card-head"><div class="grow"><h2>What you need to bring</h2></div></div>
      <div class="card-body">
        <ul class="reasons">
          <?php foreach ($f['licence_requirements'] as $req): ?>
            <li><?= e($req) ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="divider"></div>
        <p class="xsmall muted" style="margin:0">
          Nationals need identification. Companies additionally need a certificate of incorporation and
          identification for each director. Non-nationals need clearance from the Ministry of Trade,
          Industry and Cooperatives. You must be at least 18, or in partnership with someone above 18.
        </p>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Pay and apply online</h2></div></div>
      <div class="card-body">
        <div class="stack-s">
          <a class="btn btn-ghost btn-block" href="https://ecitie.kcca.go.ug" target="_blank" rel="noopener">
            <?= KCCA_icon('link', 14) ?> eCitie — online payment
          </a>
          <a class="btn btn-ghost btn-block" href="https://city.iras.go.ug" target="_blank" rel="noopener">
            <?= KCCA_icon('link', 14) ?> Pay KCCA taxes and fees
          </a>
          <a class="btn btn-ghost btn-block" href="https://appointment.kcca.go.ug" target="_blank" rel="noopener">
            <?= KCCA_icon('clock', 14) ?> Book an appointment
          </a>
          <a class="btn btn-ghost btn-block" href="https://www.kcca.go.ug/citizen-feedback" target="_blank" rel="noopener">
            <?= KCCA_icon('mail', 14) ?> Send feedback or a complaint
          </a>
          <a class="btn btn-ghost btn-block" href="https://www.kcca.go.ug/facilities" target="_blank" rel="noopener">
            <?= KCCA_icon('building', 14) ?> Locate a facility
          </a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>Division offices</h2></div></div>
      <div class="card-body tight">
        <div class="table-wrap">
          <table class="data compact">
            <thead><tr><th>Division</th><th>Mayor</th><th>Town Clerk</th></tr></thead>
            <tbody>
              <?php foreach (Database::all('SELECT * FROM divisions ORDER BY name') as $d): ?>
                <tr>
                  <td><strong><?= e($d['name']) ?></strong><div class="xsmall muted"><?= num((int) $d['parishes']) ?> parishes</div></td>
                  <td class="xsmall"><?= e($d['mayor']) ?></td>
                  <td class="xsmall"><?= e($d['town_clerk']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-foot">
        City Hall, <?= e($a['address']) ?><br>
        Tel <a href="tel:<?= e(preg_replace('/\s+/', '', $a['telephone'])) ?>"><?= e($a['telephone']) ?></a> ·
        Toll free <?= e($a['toll_free']) ?> · SMS <?= e($a['sms_code']) ?><br>
        WhatsApp <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $a['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e($a['whatsapp']) ?></a>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>The law in plain terms</h2></div></div>
      <div class="card-body">
        <?php foreach ($f['law'] as $l): ?>
          <div class="criterion mb-1">
            <span class="cl"><?= e($l['act']) ?></span>
            <span class="cn"><?= e($l['provision']) ?></span>
          </div>
        <?php endforeach; ?>
        <a class="btn btn-ghost btn-sm btn-block mt-1"
           href="https://www.parliament.go.ug/sites/default/files/The%20Markets%20Act,%202023_0.pdf"
           target="_blank" rel="noopener">Read the Markets Act 2023</a>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="grow"><h2>If something is wrong</h2></div></div>
      <div class="card-body">
        <p class="small">
          Allegations that KCCA officials collect money to allow vendors to operate on the streets are
          not true. Every enforcement officer is identified by a number on their uniform and from cameras.
          If you are asked for money to stay on a street, report it.
        </p>
        <a class="btn btn-primary btn-sm btn-block"
           href="https://www.kcca.go.ug/citizen-feedback" target="_blank" rel="noopener">
          Report it to KCCA
        </a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php';