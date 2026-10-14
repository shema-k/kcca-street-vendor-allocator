<?php
/**
 * Closes <main>, renders the footer, closes the document.
 */

$f = facts();
$a = $f['authority'];
?>
    </div><!-- /.content -->

    <footer class="foot">
      <div class="foot-inner">
        <div class="brand-block">
          <img src="../assets/brand/kcca-logo.png" alt="Kampala Capital City Authority">
          <address>
            <strong><?= e($a['legal_name']) ?></strong><br>
            <?= e($a['address']) ?><br>
            Tel: <a href="tel:<?= e(preg_replace('/\s+/', '', $a['telephone'])) ?>"><?= e($a['telephone']) ?></a>
            &nbsp;·&nbsp; Toll free: <?= e($a['toll_free']) ?><br>
            Email: <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a>
          </address>
        </div>

        <div>
          <h6>Official online services</h6>
          <div class="foot-links">
            <a href="https://ecitie.kcca.go.ug" target="_blank" rel="noopener">eCitie — online payment</a>
            <a href="https://kcca.iras.go.ug" target="_blank" rel="noopener">Smart Permit</a>
            <a href="https://bims.go.ug" target="_blank" rel="noopener">BIMS</a>
            <a href="https://www.kcca.go.ug/citizen-feedback" target="_blank" rel="noopener">Citizen feedback</a>
            <a href="https://www.kcca.go.ug/tenders" target="_blank" rel="noopener">Procurement notices</a>
          </div>
        </div>

        <div>
          <h6>Governing law</h6>
          <div class="foot-links">
            <span>KCCA Act 2010 (Cap 195)</span>
            <span>Markets Act 2023</span>
            <span>Trade Licensing Act, Cap 101</span>
            <a href="https://www.kcca.go.ug/open-gov" target="_blank" rel="noopener">Open government data</a>
          </div>
        </div>

        <div style="flex:1; min-width:230px">
          <h6>About these figures</h6>
          <p class="xsmall muted" style="margin:0">
            <?= e($f['provenance']) ?>
          </p>
          <p class="xsmall muted mt-1" style="margin-bottom:0">
            Strategic theme:
            <strong style="color:var(--ink-2)"><?= e($f['strategic_plan']['theme']) ?></strong>
            &nbsp;·&nbsp; <?= e($f['strategic_plan']['value']) ?>
          </p>
        </div>
      </div>

      <div class="foot-bottom">
        <span>Copyright &copy; <?= e($a['copyright']) ?> | All rights reserved</span>
        <span class="sep">·</span>
        <a href="https://www.kcca.go.ug/terms" target="_blank" rel="noopener">Terms of use</a>
        <span class="sep">·</span>
        <a href="https://www.kcca.go.ug/disclaimer" target="_blank" rel="noopener">Disclaimer</a>
        <span class="sep">·</span>
        <a href="https://www.kcca.go.ug/privacy" target="_blank" rel="noopener">Privacy policy</a>
        <span class="sep">·</span>
        <a href="https://www.kcca.go.ug/about-the-authority" target="_blank" rel="noopener">About the Authority</a>
        <span class="right xsmall muted"><?= e(APP_SHORT) ?> v<?= e(APP_VERSION) ?></span>
      </div>
    </footer>

  </main>
</div><!-- /.app -->

<?php /* kcca.js carries KCCA.api and the icons, activities.js the theme
        toggle; both are used by every page, so neither is declared. The
        rest are drawn by the pages that asked for them. */ ?>
<script src="../assets/js/kcca.js"></script>
<script src="../assets/js/activities.js"></script>
<?php /* The live-queue poller, loaded only where a page has asked for it with
         [data-live]. Pages that carry no queue cost nothing, which is the
         reason this is not in the always-loaded pair above. */ ?>
<?php if (!empty($liveWatch)): ?><script src="../assets/js/live.js?v=<?= e(APP_VERSION) ?>"></script><?php endif; ?>
<?php if (needs_asset('charts')): ?><script src="../assets/js/charts.js"></script><?php endif; ?>
<?php if (needs_asset('map')): ?><script src="../assets/js/kampala-map.js"></script><?php endif; ?>
<?php if (needs_asset('stall')): ?><script src="../assets/js/stall-map.js"></script><?php endif; ?>
<?php if (!empty($pageScripts)) { echo $pageScripts; } ?>
<script>
  /* Charts draw on load and again on resize; charts.js also hooks both,
     so this only covers markup injected after first paint. */
  window.addEventListener('load', function () {
    if (window.KCCACharts) window.KCCACharts.renderAll();
  });
</script>
</body>
</html>