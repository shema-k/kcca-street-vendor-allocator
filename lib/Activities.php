<?php
/**
 * KCCA activities — the card slideshow.
 *
 * What the Authority actually does, on cards that rotate on their own, on
 * the pages where somebody is arriving: the dashboard and the trader
 * portal. The portal is for people deciding whether this system is worth
 * their time, so it has to say plainly what the Authority does and what
 * they get from it.
 *
 * Illustrations are inline SVG rather than photographs. A photograph of
 * Kisenyi Market at 7am carries rights we do not hold and a network
 * dependency we do not want; a drawn plate in the Authority's own colours
 * says the same thing, loads instantly, and works offline.
 */

declare(strict_types=1);

/** One activity: code, title, what it does, what you get, and the plate. */
function kccaActivities(): array
{
    return [
        [
            'code'    => 'clear',
            'kicker'  => 'Trade order',
            'title'   => 'Streets returned to the public',
            'image'   => '../images/pexels-proudlyswazi-36498408.jpg',
            'alt'     => 'A road reserve kept clear, with a sign against illegal dumping and a walking public',
            'does'    => 'Encroached pavements and road reserves are cleared, in an order set by how much '
                       . 'harm the occupation causes, not by where the pressure is loudest.',
            'benefit' => 'You can walk down the street again — and vendors get somewhere lawful to trade '
                       . 'rather than being moved on.',
            'icon'    => 'clear',
            'accent'  => 'red',
        ],
        [
            'code'    => 'space',
            'kicker'  => 'Gazetted space',
            'title'   => '4,500 pitches identified',
            'image'   => '../images/pexels-phinley-sperrer-2163513679-39554462.jpg',
            'alt'     => 'A Kampala street packed with traders, motorcycles and traffic',
            'does'    => 'Every public market holding empty space is registered with its capacity, its '
                       . 'occupancy and its individual pitches, so the space can be found and allocated.',
            'benefit' => 'A pitch you can choose, with water, power and toilets known before you commit.',
            'icon'    => 'space',
            'accent'  => 'green',
        ],
        [
            'code'    => 'licence',
            'kicker'  => 'Trading licence',
            'title'   => 'Trade formalised',
            'image'   => '../images/pexels-nasiru-othman-447186959-40082918.jpg',
            'alt'     => 'Sacks of grain and dry produce stacked for sale in a market',
            'does'    => 'A trading licence legalises your business under the Trade Licensing Act, and '
                       . 'gives you standing with suppliers, banks and the Authority.',
            'benefit' => 'You can trade without being removed, and you can hold the Authority to that.',
            'icon'    => 'licence',
            'accent'  => 'gold',
        ],
        [
            'code'    => 'upgrade',
            'kicker'  => 'Market development',
            'title'   => 'Markets built and rebuilt',
            'image'   => '../images/pexels-ana-kenk-2159501753-36470055.jpg',
            'alt'     => 'A busy covered market hall, with traders at their stalls',
            'does'    => 'Public markets are rehabilitated and extended — Kiseka, St Balikuddembe and '
                       . 'Usafi among them — with handling areas, waste systems and access.',
            'benefit' => 'A market that works in the rain, holds your stock, and is worth paying rent in.',
            'icon'    => 'upgrade',
            'accent'  => 'green',
        ],
        [
            'code'    => 'equity',
            'kicker'  => 'Trader welfare',
            'title'   => 'Allocation that weights fairness',
            'image'   => '../images/pexels-the-chongaiman-ace-1620694153-39916941.jpg',
            'alt'     => 'A trader at his stall, serving a customer',
            'does'    => 'Who is offered a pitch first weighs capital, dependants, disability, single '
                       . 'parenthood and years in trade, and the weighing is shown.',
            'benefit' => 'The most exposed traders are served first — and you can see why.',
            'icon'    => 'equity',
            'accent'  => 'red',
        ],
        [
            'code'    => 'digital',
            'kicker'  => 'Digital services',
            'title'   => 'Services without the queue',
            'image'   => '../images/pexels-dokunayano-38968324.jpg',
            'alt'     => 'A market passage between trading stalls',
            'does'    => 'Apply, pay and review your licence online, and reach the Authority by toll '
                       . 'free line and web channel instead of a counter.',
            'benefit' => 'You can pay a fee from your stall and keep trading.',
            'icon'    => 'digital',
            'accent'  => 'gold',
        ],
    ];
}

/**
 * The illustration for one activity: a photograph where one is supplied,
 * otherwise the drawn plate.
 *
 * A photograph of a Kampala market at 7am carries rights we do not hold, so
 * the default is the drawn plate in the Authority's own colours — it loads
 * instantly and works offline. Where a licensed photograph is available the
 * card prefers it, because it says more about the work than a drawing does.
 *
 * The image is object-fit: cover, so any aspect ratio fills the plate
 * without distortion; a portrait photograph is cropped, not stretched.
 */
function kccaActivityMedia(array $card, string $class = 'act-image', bool $lazy = true): string
{
    if (!empty($card['image'])) {
        return sprintf(
            '<img class="%s" src="%s" alt="%s" loading="%s" decoding="async">',
            e($class),
            e((string) $card['image']),
            e((string) ($card['alt'] ?? $card['title'])),
            $lazy ? 'lazy' : 'eager'
        );
    }

    return kccaActivityPlate((string) $card['icon']);
}

/**
 * The illustration for one activity, drawn in the Authority's colours.
 * Kept as a switch so the file stays readable at a glance.
 */
function kccaActivityPlate(string $code): string
{
    switch ($code) {

        case 'clear':
            /* A street cross-section, with the obstruction being removed. */
            return <<<'SVG'
<svg viewBox="0 0 320 150" role="img" aria-label="A pavement cleared of obstruction, with a walking figure" preserveAspectRatio="xMidYMid slice">
  <rect width="320" height="150" fill="#F3E3E1"/>
  <rect x="0" y="0" width="320" height="8" fill="#DC351D" opacity=".18"/>
  <!-- carriageway -->
  <rect x="0" y="92" width="320" height="42" fill="#464646"/>
  <path d="M0 100h320M0 112h320M0 124h320" stroke="#FFFFFF" stroke-width="2" stroke-dasharray="18 12" opacity=".55"/>
  <!-- footway -->
  <rect x="0" y="62" width="320" height="30" fill="#D8DDE4"/>
  <path d="M0 77h320" stroke="#B9C0CA" stroke-width="1"/>
  <!-- two walking figures, cleared space -->
  <circle cx="96" cy="34" r="10" fill="#0A6339"/>
  <path d="M92 44v20M92 52l-10 12M92 52l10 12M92 64l-8 20M92 64l8 20" stroke="#0A6339" stroke-width="5" stroke-linecap="round"/>
  <circle cx="132" cy="32" r="9" fill="#0A6339"/>
  <path d="M132 42v18M132 50l-9 12M132 50l9 12M132 60l-7 20M132 60l7 20" stroke="#0A6339" stroke-width="5" stroke-linecap="round"/>
</svg>
SVG;

        case 'space':
            /* A market grid, with free pitches marked. */
            return <<<'SVG'
<svg viewBox="0 0 320 150" role="img" aria-label="A grid of market pitches, some marked free" preserveAspectRatio="xMidYMid slice">
  <rect width="320" height="150" fill="#E8F1EC"/>
  <rect x="14" y="16" width="292" height="118" fill="#FFFFFF" opacity=".7" rx="4"/>
  <!-- roof line -->
  <path d="M14 16l144 26h4l144-26" stroke="#0A6339" stroke-width="3" fill="none" opacity=".45"/>
  <!-- stall grid -->
  <g stroke="#124A31" stroke-width="1.6" fill="none">
    <rect x="34" y="46" width="60" height="24"/>
    <rect x="98" y="46" width="60" height="24"/>
    <rect x="162" y="46" width="60" height="24"/>
    <rect x="226" y="46" width="60" height="24"/>
    <rect x="34" y="76" width="60" height="24"/>
    <rect x="98" y="76" width="60" height="24"/>
    <rect x="162" y="76" width="60" height="24"/>
    <rect x="226" y="76" width="60" height="24"/>
  </g>
  <!-- free pitches filled -->
  <g fill="#0A6339">
    <rect x="34" y="46" width="60" height="24"/>
    <rect x="162" y="76" width="60" height="24"/>
    <rect x="226" y="76" width="60" height="24"/>
  </g>
  <g fill="#1B7F4D" font-family="system-ui" font-weight="700" font-size="9">
    <text x="46" y="62">FREE</text>
    <text x="115" y="62">USED</text>
    <text x="180" y="92">FREE</text>
  </g>
</svg>
SVG;

        case 'licence':
            /* A licence card, stamped. */
            return <<<'SVG'
<svg viewBox="0 0 320 150" role="img" aria-label="A trading licence card with a seal" preserveAspectRatio="xMidYMid slice">
  <rect width="320" height="150" fill="#FBF2DA"/>
  <rect x="66" y="26" width="188" height="100" rx="6" fill="#FFFFFF" stroke="#EAA515" stroke-width="2"/>
  <rect x="66" y="26" width="188" height="20" fill="#EAA515"/>
  <text x="160" y="41" text-anchor="middle" font-family="system-ui" font-weight="700" font-size="11" fill="#FFFFFF">TRADING LICENCE</text>
  <rect x="80" y="56" width="34" height="34" rx="3" fill="#F0B84A" opacity=".4"/>
  <path d="M88 62h18M88 70h18M88 78h12" stroke="#B07500" stroke-width="2"/>
  <rect x="124" y="58" width="114" height="6" rx="3" fill="#E3E6EB"/>
  <rect x="124" y="70" width="86" height="5" rx="2.5" fill="#EFF1F4"/>
  <rect x="124" y="80" width="58" height="5" rx="2.5" fill="#EFF1F4"/>
  <circle cx="228" cy="102" r="20" fill="#0A6339"/>
  <path d="M220 102l6 6 12-14" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" fill="none"/>
</svg>
SVG;

        case 'upgrade':
            /* A market hall under construction, with a crane. */
            return <<<'SVG'
<svg viewBox="0 0 320 150" role="img" aria-label="A market hall under construction with a crane" preserveAspectRatio="xMidYMid slice">
  <rect width="320" height="150" fill="#E8F1EC"/>
  <rect x="0" y="128" width="320" height="22" fill="#CFD8DF"/>
  <rect x="40" y="72" width="150" height="56" fill="#FFFFFF"/>
  <path d="M40 72h150l-75-26z" fill="#0A6339" opacity=".85"/>
  <path d="M48 82h134v38H48z" fill="#E7F3EC"/>
  <path d="M64 82v38M96 82v38M128 82v38M160 82v38" stroke="#0A6339" stroke-width="2"/>
  <!-- crane -->
  <rect x="218" y="34" width="5" height="94" fill="#464646"/>
  <rect x="218" y="34" width="84" height="5" fill="#464646"/>
  <path d="M218 34l-24 12h24" fill="#DC351D"/>
  <path d="M272 39v26" stroke="#464646" stroke-width="2"/>
  <rect x="262" y="65" width="20" height="14" fill="#DC351D"/>
  <!-- paving -->
  <path d="M0 128h320" stroke="#B9C0CA" stroke-width="1"/>
</svg>
SVG;

        case 'equity':
            /* Two figures, one being given a pitch — weighting fairness. */
            return <<<'SVG'
<svg viewBox="0 0 320 150" role="img" aria-label="Two traders of different circumstances, with the smaller one given priority" preserveAspectRatio="xMidYMid slice">
  <rect width="320" height="150" fill="#F3E3E1"/>
  <!-- the queue, differentiated -->
  <circle cx="92" cy="46" r="13" fill="#0A6339"/>
  <path d="M92 59v34M92 70l-13 16M92 70l13 16M92 93l-10 24M92 93l10 24" stroke="#0A6339" stroke-width="6" stroke-linecap="round"/>
  <circle cx="140" cy="56" r="9" fill="#949CAB"/>
  <path d="M140 65v26M140 76l-9 12M140 76l9 12M140 91l-7 22M140 91l7 22" stroke="#949CAB" stroke-width="5" stroke-linecap="round"/>
  <!-- the pitch being handed over -->
  <rect x="196" y="70" width="98" height="8" fill="#0A6339"/>
  <rect x="196" y="70" width="6" height="40" fill="#0A6339"/>
  <rect x="288" y="70" width="6" height="40" fill="#0A6339"/>
  <path d="M196 70l-8-10h100l-8 10" fill="#0A6339" opacity=".55"/>
  <!-- the priority marker -->
  <circle cx="240" cy="38" r="14" fill="#DC351D"/>
  <path d="M240 32v6M240 42v2" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round"/>
</svg>
SVG;

        case 'digital':
        default:
            /* A phone reaching a service, no queue. */
            return <<<'SVG'
<svg viewBox="0 0 320 150" role="img" aria-label="A telephone held in a hand, connected to a service" preserveAspectRatio="xMidYMid slice">
  <rect width="320" height="150" fill="#FBF2DA"/>
  <rect x="112" y="20" width="96" height="110" rx="10" fill="#FFFFFF" stroke="#EAA515" stroke-width="2"/>
  <rect x="120" y="30" width="80" height="52" rx="4" fill="#F1F3F6"/>
  <text x="160" y="50" text-anchor="middle" font-family="system-ui" font-weight="700" font-size="9" fill="#0A6339">eCitie</text>
  <rect x="120" y="60" width="80" height="6" rx="3" fill="#E3E6EB"/>
  <rect x="120" y="72" width="80" height="6" rx="3" fill="#E3E6EB"/>
  <rect x="120" y="84" width="34" height="6" rx="3" fill="#DC351D"/>
  <circle cx="160" cy="114" r="4" fill="#E3E6EB"/>
  <!-- connectivity -->
  <path d="M208 62q12 12 0 24" stroke="#EAA515" stroke-width="3" fill="none" stroke-linecap="round"/>
  <path d="M218" 54" q="20" 20" 0" 40" stroke="#EAA515" stroke-width="3" fill="none" stroke-linecap="round"/>
</svg>
SVG;
    }
}

/**
 * Render the slideshow. Auto-advances, pauses on hover and focus, and is
 * fully keyboard operable.
 */
function renderActivitySlideshow(bool $showControls = true, int $intervalMs = 2000): void
{
    static $n = 0;
    $uid = 'kcca-slides-' . (++$n);
    $cards = kccaActivities();
?>
<section class="acts" data-slideshow="<?= e($uid) ?>"
         data-interval="<?= (int) $intervalMs ?>"
         data-controls="<?= $showControls ? '1' : '0' ?>"
         aria-roledescription="carousel" aria-label="What KCCA does">
    <div class="acts-track">
      <?php foreach ($cards as $i => $c): ?>
        <figure class="act <?= $i === 0 ? 'on' : '' ?>" data-slide="<?= $i ?>"
                role="group" aria-roledescription="slide"
                aria-label="<?= $i + 1 ?> of <?= count($cards) ?>: <?= e($c['title']) ?>"
                <?= $i === 0 ? '' : 'aria-hidden="true"' ?>>
          <div class="act-plate a-<?= e($c['accent']) ?>">
            <?= kccaActivityMedia($c, 'act-image', false) ?>
          </div>
          <figcaption class="act-body">
            <span class="act-kicker"><?= e($c['kicker']) ?></span>
            <h3><?= e($c['title']) ?></h3>
            <p class="act-does"><?= e($c['does']) ?></p>
            <p class="act-benefit"><strong>What you get:</strong> <?= e($c['benefit']) ?></p>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>

    <?php if ($showControls): ?>
      <div class="acts-ui">
        <button type="button" class="acts-nav prev" data-slide-nav="prev"
                aria-label="Previous activity">‹</button>
        <div class="acts-dots" role="tablist" aria-label="Choose an activity">
          <?php foreach ($cards as $i => $c): ?>
            <button type="button" role="tab" class="dot <?= $i === 0 ? 'on' : '' ?>"
                    data-slide-dot="<?= $i ?>"
                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    aria-label="<?= e($c['title']) ?>"></button>
          <?php endforeach; ?>
        </div>
        <button type="button" class="acts-nav next" data-slide-nav="next"
                aria-label="Next activity">›</button>
      </div>
    <?php endif; ?>
  </section>
<?php
}

/**
 * A static grid of the same cards, for readers who want them all at once
 * and for reduced-motion readers whom an auto slideshow would not suit.
 */
function renderActivityGrid(): void
{
    $cards = kccaActivities();
?>
<section class="acts-grid" aria-label="What KCCA does">
  <?php foreach ($cards as $c): ?>
    <article class="act-card a-<?= e($c['accent']) ?>">
      <div class="act-card-plate"><?= kccaActivityMedia($c, 'act-card-image') ?></div>
      <div class="act-card-body">
        <span class="act-kicker"><?= e($c['kicker']) ?></span>
        <h3><?= e($c['title']) ?></h3>
        <p class="act-does"><?= e($c['does']) ?></p>
        <p class="act-benefit"><strong>What you get:</strong> <?= e($c['benefit']) ?></p>
      </div>
    </article>
  <?php endforeach; ?>
</section>
<?php
}
