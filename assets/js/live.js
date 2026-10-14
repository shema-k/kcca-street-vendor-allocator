/**
 * KCCA VRSP — a queue that visibly fills.
 *
 * The database is already real time: every request reads committed rows, so
 * nothing here polls for new data that was not already there. What it polls
 * for is the signal that something arrived, so a chairman watching a queue of
 * complaints discovers one as it lands rather than on the next reload.
 *
 * Three things this deliberately does not do:
 *
 *   It does not steal focus or reload under the reader. A page that reloads
 *   itself while somebody is reading it, or worse while they are typing,
 *   destroys their work and their trust. It posts a banner and lets them
 *   choose. Reload happens only if the page has had no interaction for a
 *   while AND the reader has asked for live updates.
 *
 *   It does not promise silence. If the reader is mid-form the interval
 *   stretches so the page never yanks itself at the moment of sending.
 *
 *   It does not pretend to be a socket. One small request on an interval is
 *   honest, works behind any proxy, and costs a fraction of what a websocket
 *   costs on every page that does not need one.
 */
(function () {
  'use strict';

  var INTERVAL = 15000;      // fifteen seconds between checks
  var STRETCH  = 120000;     // two minutes while the reader is interacting
  var KEY      = 'kcca-live';

  var total = 0;             // what the page was rendered with
  var live  = false;         // the reader asked to watch
  var busy  = false;         // something is mid-flight or mid-form
  var timer = null;
  var bar   = null;

  function readTotal() {
    var el = document.querySelector('[data-live-total]');
    if (!el) return 0;
    var n = parseInt(el.getAttribute('data-live-total'), 10);
    return isNaN(n) ? 0 : n;
  }

  function ensureBar() {
    if (bar) return bar;
    bar = document.createElement('div');
    bar.className = 'live-bar';
    bar.setAttribute('role', 'status');
    bar.hidden = true;
    bar.innerHTML = '<span class="live-what"></span>'
      + '<button type="button" class="btn btn-sm btn-primary live-show">Show</button>'
      + '<button type="button" class="live-close" aria-label="Dismiss">&times;</button>';
    document.body.appendChild(bar);
    bar.querySelector('.live-show').addEventListener('click', function () {
      location.reload();
    });
    bar.querySelector('.live-close').addEventListener('click', function () {
      bar.hidden = true;
    });
    return bar;
  }

  function announce(n) {
    var b = ensureBar();
    b.querySelector('.live-what').textContent =
      n === 1 ? '1 new has arrived.' : n + ' new have arrived.';
    b.hidden = false;
  }

  function check() {
    if (busy) return;
    var req = new XMLHttpRequest();
    req.open('GET', (window.KCCA && KCCA.api ? KCCA.api('api/live_counts.php') : 'api/live_counts.php'), true);
    req.onload = function () {
      if (req.status !== 200) return;
      var data;
      try { data = JSON.parse(req.responseText); } catch (e) { return; }
      if (!data || data.ok !== true) return;
      var now = data.total || 0;
      var fresh = now - total;
      if (fresh > 0) {
        total = now;
        /* Count what actually arrived. total rises to the new figure so a
           second arrival is announced as the second, not as both again. */
        announce(fresh);
      }
    };
    req.send();
  }

  function schedule(delay) {
    if (timer) window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      if (live && !busy) check();
      schedule(live && !busy ? INTERVAL : STRETCH);
    }, delay || INTERVAL);
  }

  /* A reader at a form, a modal, or mid-send must not have the page move
     under them, so any of those stretches the interval. */
  function markBusy() {
    busy = true;
    window.setTimeout(function () { busy = false; }, 4000);
  }

  function init() {
    if (!document.querySelector('[data-live]')) return;
    total = readTotal();

    document.addEventListener('input', markBusy);
    document.addEventListener('submit', markBusy);
    document.addEventListener('click', markBusy);

    var toggle = document.querySelector('[data-live-toggle]');
    if (toggle) {
      var set = function (on) {
        live = on;
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        toggle.textContent = on ? 'Live' : 'Paused';
      };
      toggle.addEventListener('click', function () { set(!live); });
      set(false);
    }

    schedule(INTERVAL);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
