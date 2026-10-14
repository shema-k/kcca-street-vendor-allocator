<?php
/**
 * Language switcher.
 *
 * A <details> rather than a JavaScript menu, deliberately. It opens with the
 * keyboard, closes with Escape, and marks its own state, with no script at
 * all — which matters on the pages a trader opens on a phone over a slow
 * connection, where the budget is measured in kilobytes.
 *
 * Included by the top bar and by the portal, which has its own shell.
 */

declare(strict_types=1);

$here = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$back = $here === '' || $here === 'index'
    ? 'index.php'
    : (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/pages/')
        ? 'pages/' . $here . '.php'
        : $here . '.php');
?>
<details class="langpick">
  <summary class="icon-link" title="<?= e(t('common.language')) ?>"
           aria-label="<?= e(t('common.language')) ?>">
    <?= KCCA_icon('lang', 15) ?>
    <span class="langcode"><?= e(strtoupper(Lang::code())) ?></span>
  </summary>

  <div class="langpick-menu" role="menu">
    <span class="langpick-head"><?= e(t('common.language')) ?></span>
    <?php foreach (Lang::LANGUAGES as $code => $label): ?>
      <?php $here_ = $code === Lang::code(); ?>
      <a role="menuitem"
         class="langpick-item<?= $here_ ? ' is-current' : '' ?>"
         href="<?= e(langUrl($code, $back)) ?>"
         <?= $here_ ? 'aria-current="true"' : '' ?>
         lang="<?= e($code) ?>">
        <span><?= e($label) ?></span>
        <?php if ($here_): ?><?= KCCA_icon('check', 13) ?><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</details>