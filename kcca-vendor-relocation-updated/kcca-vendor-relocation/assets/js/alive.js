/* KCCA VRSP - alive.js
 *
 * Drives the motion in assets/css/alive.css. Nothing here is needed for the
 * system to work: every figure, link and button is complete without it, and
 * every effect is skipped when the visitor prefers reduced motion or has
 * switched Motion off. No effect ever delays a click.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Appearance preferences (one set per account) ------------------
     The server writes the account's choices onto <html> before first paint.
     Changing one applies at once and is then saved to the account, so it
     follows the user to any device. Saving is fire-and-forget: a failure
     never undoes what the user can already see. */
  var ATTR = { glass: 'data-glass', motion: 'data-motion', guide: 'data-guide', notify: 'data-notify', density: 'data-density' };

  function applyPref(key, val) {
    if (key === 'glass') { root.setAttribute('data-glass', val); }
    if (key === 'motion') { if (val === 'off') { root.setAttribute('data-motion', 'off'); } else { root.removeAttribute('data-motion'); } }
    if (key === 'guide') { if (val === 'on') { root.setAttribute('data-guide', 'on'); } else { root.removeAttribute('data-guide'); } }
    if (key === 'notify') { root.setAttribute('data-notify', val); }
    if (key === 'density') { root.setAttribute('data-density', val); }
    var btns = document.querySelectorAll('[data-' + key + '-toggle]');
    for (var i = 0; i < btns.length; i++) { btns[i].setAttribute('aria-pressed', (key === 'motion' ? val === 'on' : key === 'glass' ? val === 'on' : val === 'on') ? 'true' : 'false'); }
    var boxes = document.querySelectorAll('[data-pref="' + key + '"]');
    for (var j = 0; j < boxes.length; j++) { boxes[j].checked = (key === 'density' ? val === 'compact' : val === 'on'); }
  }

  function currentPref(key) {
    var v = root.getAttribute(ATTR[key]);
    if (key === 'glass') { return v === 'off' ? 'off' : 'on'; }
    if (key === 'motion') { return v === 'off' ? 'off' : 'on'; }
    if (key === 'density') { return v === 'compact' ? 'compact' : 'comfortable'; }
    if (key === 'notify') { return v === 'off' ? 'off' : 'on'; }
    return v === 'on' ? 'on' : 'off';
  }

  function savePref(key, val, done) {
    var meta = document.querySelector('meta[name="kcca-csrf"]');
    var fd = new FormData();
    fd.append(key, val);
    if (meta) { fd.append('csrf', meta.getAttribute('content')); }
    var url = (window.KCCA && KCCA.api) ? KCCA.api('api/prefs.php') : '../api/prefs.php';
    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (j) { if (done) { done(!!(j && j.ok)); } })
      .catch(function () { if (done) { done(false); } });
  }

  window.KCCAPrefs = {
    set: function (key, val, done) { applyPref(key, val); savePref(key, val, done); },
    toggle: function (key, done) { this.set(key, currentPref(key) === 'on' ? 'off' : 'on', done); }
  };

  function motionOn() { return !reduce && root.getAttribute('data-motion') !== 'off'; }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-motion-toggle],[data-glass-toggle],[data-guide-toggle]');
    if (!b) { return; }
    var key = b.hasAttribute('data-motion-toggle') ? 'motion' : b.hasAttribute('data-glass-toggle') ? 'glass' : 'guide';
    window.KCCAPrefs.toggle(key);
  });

  /* ---- Live clock (Kampala time) ------------------------------------ */
  function tickClock() {
    var els = document.querySelectorAll('[data-live-clock]');
    if (!els.length) { return; }
    var t;
    try {
      t = new Date().toLocaleTimeString('en-GB', { timeZone: 'Africa/Kampala', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (e) { t = new Date().toLocaleTimeString(); }
    for (var i = 0; i < els.length; i++) { els[i].textContent = t; }
  }
  tickClock();
  setInterval(tickClock, 1000);

  /* ---- Count-up for headline figures -------------------------------- */
  function countUp(el) {
    var node = null, i;
    for (i = 0; i < el.childNodes.length; i++) {
      if (el.childNodes[i].nodeType === 3 && /\d/.test(el.childNodes[i].nodeValue)) { node = el.childNodes[i]; break; }
    }
    if (!node) { return; }
    var raw = node.nodeValue.trim();
    if (!/^[\d,]+(\.\d+)?$/.test(raw)) { return; }
    var dec = (raw.split('.')[1] || '').length;
    var target = parseFloat(raw.replace(/,/g, ''));
    if (!isFinite(target) || target === 0) { return; }
    var dur = Math.min(1800, 800 + String(Math.floor(target)).length * 130);
    var t0 = null;
    function fmt(v) { return v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }); }
    node.nodeValue = fmt(0);
    function step(t) {
      if (t0 === null) { t0 = t; }
      var p = Math.min(1, (t - t0) / dur);
      node.nodeValue = p < 1 ? fmt(target * (1 - Math.pow(1 - p, 3))) : raw;
      if (p < 1) { requestAnimationFrame(step); }
    }
    requestAnimationFrame(step);
  }

  /* ---- Ripple on every button --------------------------------------- */
  document.addEventListener('pointerdown', function (ev) {
    if (!motionOn()) { return; }
    var b = ev.target.closest && ev.target.closest('.btn');
    if (!b || b.disabled) { return; }
    var r = b.getBoundingClientRect();
    var s = document.createElement('span');
    s.className = 'ripple';
    s.style.left = (ev.clientX - r.left) + 'px';
    s.style.top = (ev.clientY - r.top) + 'px';
    b.appendChild(s);
    setTimeout(function () { if (s.parentNode) { s.parentNode.removeChild(s); } }, 650);
  });

  /* ---- Soft page transition ----------------------------------------- */
  document.addEventListener('click', function (ev) {
    if (!motionOn() || ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) { return; }
    var a = ev.target.closest && ev.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-fade')) { return; }
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) { return; }
    if (a.origin !== location.origin || /format=csv|\/api\//.test(href)) { return; }
    ev.preventDefault();
    root.classList.add('leaving');
    setTimeout(function () { location.href = a.href; }, 150);
    /* If the browser does not leave (a download, a cancelled prompt), bring the page back. */
    setTimeout(function () { root.classList.remove('leaving'); }, 3000);
  });
  window.addEventListener('pageshow', function () { root.classList.remove('leaving'); });

  /* ---- Everything that needs the DOM -------------------------------- */
  function ready() {
    if (motionOn()) {

      /* Table rows enter in order (the first dozen only). */
      var tables = document.querySelectorAll('table.data tbody');
      for (var t = 0; t < tables.length; t++) {
        var rows = tables[t].rows;
        for (var r = 0; r < rows.length && r < 14; r++) { rows[r].style.setProperty('--r', r); }
      }

      /* Headline figures count up. */
      var vals = document.querySelectorAll('.stat .value');
      for (var v = 0; v < vals.length; v++) { countUp(vals[v]); }

      /* Tiles lean toward the pointer. */
      var tiles = document.querySelectorAll('.stat');
      Array.prototype.forEach.call(tiles, function (el) {
        el.addEventListener('pointermove', function (ev) {
          if (ev.pointerType === 'touch') { return; }
          var b = el.getBoundingClientRect();
          var x = (ev.clientX - b.left) / b.width - 0.5;
          var y = (ev.clientY - b.top) / b.height - 0.5;
          el.setAttribute('data-tilt', '');
          el.style.setProperty('--ry', (x * 9).toFixed(2) + 'deg');
          el.style.setProperty('--rx', (-y * 9).toFixed(2) + 'deg');
        });
        el.addEventListener('pointerleave', function () {
          el.style.setProperty('--rx', '0deg'); el.style.setProperty('--ry', '0deg');
        });
      });

      /* Cards below the fold rise into view as they are reached. */
      if ('IntersectionObserver' in window) {
        var vh = window.innerHeight || 800;
        var io = new IntersectionObserver(function (entries) {
          entries.forEach(function (en) {
            if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
          });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
        var cards = document.querySelectorAll('.content .card, .content [data-reveal]');
        Array.prototype.forEach.call(cards, function (c) {
          if (c.getBoundingClientRect().top > vh) { c.classList.add('js-reveal'); io.observe(c); }
        });
      }
    }
    initVendorHero();
  }

  /* ---- Vendor photo slider ------------------------------------------ */
  function initVendorHero() {
    var hero = document.querySelector('[data-vhero]');
    if (!hero) { return; }
    var slides = hero.querySelectorAll('.vslide');
    var caps = hero.querySelectorAll('.vcap');
    var dots = hero.querySelectorAll('.vdots button');
    var bar = hero.querySelector('.vbar i');
    var gap = parseInt(hero.getAttribute('data-interval'), 10) || 5500;
    var cur = 0, timer = null, paused = false;
    if (slides.length < 2) { return; }

    function show(n) {
      cur = (n + slides.length) % slides.length;
      for (var i = 0; i < slides.length; i++) {
        slides[i].classList.toggle('on', i === cur);
        if (caps[i]) { caps[i].hidden = i !== cur; }
        if (dots[i]) { dots[i].classList.toggle('on', i === cur); dots[i].setAttribute('aria-selected', i === cur ? 'true' : 'false'); }
      }
      if (bar) {
        bar.classList.remove('run');
        void bar.offsetWidth;                // restart the progress bar
        if (motionOn() && !paused) { bar.style.setProperty('--dur', gap + 'ms'); bar.classList.add('run'); }
      }
    }
    function arm() {
      clearTimeout(timer);
      if (paused || !motionOn()) { return; }
      timer = setTimeout(function () { show(cur + 1); arm(); }, gap);
    }

    Array.prototype.forEach.call(dots, function (d, i) {
      d.addEventListener('click', function () { show(i); arm(); });
    });
    hero.addEventListener('mouseenter', function () { paused = true; clearTimeout(timer); if (bar) { bar.classList.remove('run'); } });
    hero.addEventListener('mouseleave', function () { paused = false; show(cur); arm(); });
    hero.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowRight') { show(cur + 1); arm(); }
      if (ev.key === 'ArrowLeft')  { show(cur - 1); arm(); }
    });
    var x0 = null;
    hero.addEventListener('touchstart', function (ev) { x0 = ev.touches[0].clientX; }, { passive: true });
    hero.addEventListener('touchend', function (ev) {
      if (x0 === null) { return; }
      var dx = ev.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 40) { show(cur + (dx < 0 ? 1 : -1)); arm(); }
    });
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { clearTimeout(timer); } else { arm(); }
    });

    show(0);
    arm();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); } else { ready(); }
})();
