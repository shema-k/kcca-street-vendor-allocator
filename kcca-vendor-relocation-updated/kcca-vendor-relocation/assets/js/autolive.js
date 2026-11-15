/* KCCA VRSP - autolive.js
 *
 * Keeps every screen current. Every few seconds it asks api/pulse.php for a
 * fingerprint of the data. When it changes:
 *   - the navigation badges update in place, and
 *   - the page refreshes itself once the reader is idle (not typing, no
 *     dialog open), keeping their scroll position. If they are busy, a small
 *     "Updated" bar waits for them instead, so nothing is ever lost.
 * The clock chip in the top bar switches this on or off.
 */
(function () {
  'use strict';

  var EVERY = 8000;          // ms between checks
  var IDLE = 4000;           // ms without input before a refresh is allowed
  var MIN_GAP = 15000;       // ms between automatic refreshes
  var KEY = 'kcca-autolive';
  var SCROLL = 'kcca-scroll:' + location.pathname + location.search;

  var chip = document.querySelector('.live-chip');
  if (!chip) { return; }

  var token = null, pending = false, last = 0, lastInput = Date.now(), timer = null, bar = null;
  var enabled = true;
  try { enabled = localStorage.getItem(KEY) !== 'off'; } catch (e) {}

  /* Put the reader back where they were after a refresh. */
  try {
    var y = sessionStorage.getItem(SCROLL);
    if (y !== null) {
      sessionStorage.removeItem(SCROLL);
      window.addEventListener('load', function () { window.scrollTo(0, parseInt(y, 10) || 0); });
    }
  } catch (e) {}

  ['input', 'keydown', 'change'].forEach(function (t) {
    document.addEventListener(t, function () { lastInput = Date.now(); }, true);
  });

  function idle() {
    if (Date.now() - lastInput < IDLE) { return false; }
    var a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) && a.type !== 'button' && a.type !== 'submit') { return false; }
    if (document.querySelector('.modal-back, [role="dialog"]:not([hidden]), .roledash:not([hidden])')) { return false; }
    return true;
  }

  function paint() {
    chip.classList.toggle('is-paused', !enabled);
    chip.setAttribute('role', 'button');
    chip.setAttribute('tabindex', '0');
    chip.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    chip.title = enabled ? 'Live updates on. Click to pause.' : 'Live updates paused. Click to resume.';
  }

  function toggle() {
    enabled = !enabled;
    try { localStorage.setItem(KEY, enabled ? 'on' : 'off'); } catch (e) {}
    paint();
    if (enabled) { check(); }
    else { hideBar(); }
  }
  chip.addEventListener('click', toggle);
  chip.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
  });

  function showBar() {
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'live-bar';
      bar.setAttribute('role', 'status');
      bar.innerHTML = '<span class="live-what">Updated by a colleague</span>' +
        '<button type="button" class="btn btn-sm btn-primary">Refresh</button>' +
        '<button type="button" class="live-close" aria-label="Dismiss">&times;</button>';
      document.body.appendChild(bar);
      bar.querySelector('.btn').addEventListener('click', refresh);
      bar.querySelector('.live-close').addEventListener('click', hideBar);
    }
    bar.hidden = false;
  }
  function hideBar() { if (bar) { bar.hidden = true; } }

  function refresh() {
    last = Date.now();
    try { sessionStorage.setItem(SCROLL, String(window.pageYOffset || 0)); } catch (e) {}
    location.reload();
  }

  function pills(map) {
    if (!map) { return; }
    Object.keys(map).forEach(function (id) {
      var a = document.querySelector('.rail a[data-nav="' + id + '"]');
      if (!a) { return; }
      var n = parseInt(map[id], 10) || 0;
      var p = a.querySelector('.pill');
      if (n <= 0) { if (p) { p.remove(); } return; }
      if (!p) { p = document.createElement('span'); p.className = 'pill'; a.appendChild(p); }
      var txt = n > 999 ? '999+' : String(n);
      if (p.textContent !== txt) { p.textContent = txt; p.classList.remove('bump'); void p.offsetWidth; p.classList.add('bump'); }
    });
  }

  function check() {
    if (!enabled || document.hidden) { return; }
    var url = (window.KCCA && KCCA.api) ? KCCA.api('api/pulse.php') : '../api/pulse.php';
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.ok) { return; }
        pills(j.pills);
        if (token === null) { token = j.token; return; }
        if (j.token !== token) { token = j.token; pending = true; }
        if (pending) {
          if (idle() && Date.now() - last > MIN_GAP) { refresh(); }
          else { showBar(); }
        }
      })
      .catch(function () { /* offline: try again next time */ });
  }

  function loop() {
    check();
    timer = window.setTimeout(loop, EVERY);
  }

  document.addEventListener('visibilitychange', function () { if (!document.hidden) { check(); } });
  paint();
  loop();
})();
