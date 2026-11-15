/* KCCA VRSP - alerts.js
 *
 * The Alerts bell for traders and chairmen. Shows what the system has told
 * this account (pitch taken or refused, new trader at a market, messages),
 * checks for new ones every 10 seconds, and pops a short notice when one
 * arrives. A chairman can reply to a trader straight from an alert.
 */
(function () {
  'use strict';

  var host = document.querySelector('[data-alerts]');
  if (!host) { return; }

  var bell = host.querySelector('[data-alerts-bell]');
  var badge = host.querySelector('[data-alerts-count]');
  var panel = host.querySelector('[data-alerts-panel]');
  var list = host.querySelector('[data-alerts-list]');
  var readAll = host.querySelector('[data-alerts-readall]');
  var role = host.getAttribute('data-role') || '';
  var root = document.documentElement;

  var items = [], latest = 0, unread = 0, first = true, opened = false;

  function url(q) {
    var p = 'api/alerts.php' + (q || '');
    return (window.KCCA && KCCA.api) ? KCCA.api(p) : '../' + p;
  }

  function ago(iso) {
    var s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
    if (s < 45) { return 'just now'; }
    if (s < 3600) { return Math.round(s / 60) + ' min ago'; }
    if (s < 86400) { return Math.round(s / 3600) + ' h ago'; }
    return Math.round(s / 86400) + ' d ago';
  }

  function paintBadge() {
    if (unread > 0) { badge.textContent = unread > 9 ? '9+' : String(unread); badge.hidden = false; }
    else { badge.hidden = true; }
    bell.classList.toggle('has-alerts', unread > 0);
  }

  function markRead(id) {
    if (!window.KCCA || !KCCA.post) { return; }
    KCCA.post(url(), id ? { action: 'read', id: id } : { action: 'read' }).then(function (j) {
      if (j && j.ok) { unread = j.unread; paintBadge(); }
    }).catch(function () {});
  }

  function reply(vendorId) {
    KCCA.ask('Reply to trader', '', { label: 'Your message', buttonLabel: 'Send', placeholder: 'Write a short reply' })
      .then(function (text) {
        if (text === null || text === '') { return; }
        KCCA.post(url(), { action: 'message', vendor_id: vendorId, body: text }).then(function (j) {
          KCCA.flash((j && (j.message || j.error)) || 'Could not send.', j && j.ok ? 'ok' : 'bad', 5000);
        }).catch(function () { KCCA.flash('Could not send.', 'bad', 5000); });
      });
  }

  function render() {
    list.textContent = '';
    if (!items.length) {
      var li = document.createElement('li'); li.className = 'feed-empty'; li.textContent = 'No alerts yet.';
      list.appendChild(li); return;
    }
    items.slice(0, 25).forEach(function (a) {
      var li = document.createElement('li');
      li.className = 'alert-item tone-' + a.tone + (a.read ? '' : ' unread');

      var t = document.createElement('b'); t.className = 'alert-title'; t.textContent = a.title;
      var body = document.createElement('div'); body.className = 'alert-body'; body.textContent = a.body;
      var meta = document.createElement('small'); meta.textContent = ago(a.at);
      var row = document.createElement('div'); row.className = 'alert-actions';

      if (a.link) {
        var open = document.createElement('a'); open.className = 'btn btn-ghost btn-xs'; open.textContent = 'Open';
        open.href = a.link; row.appendChild(open);
      }
      if (role === 'chairman' && a.meta && a.meta.vendor_id) {
        var r = document.createElement('button'); r.type = 'button'; r.className = 'btn btn-primary btn-xs'; r.textContent = 'Reply';
        r.addEventListener('click', function (e) { e.stopPropagation(); reply(a.meta.vendor_id); });
        row.appendChild(r);
      }
      li.appendChild(t); li.appendChild(body); li.appendChild(meta); li.appendChild(row);
      li.addEventListener('click', function () {
        if (!a.read) { a.read = true; li.classList.remove('unread'); markRead(a.id); }
      });
      list.appendChild(li);
    });
  }

  function openPanel() { panel.hidden = false; bell.setAttribute('aria-expanded', 'true'); opened = true; render(); }
  function closePanel() {
    panel.hidden = true; bell.setAttribute('aria-expanded', 'false');
    if (opened && unread > 0) { items.forEach(function (a) { a.read = true; }); markRead(0); }
    opened = false;
  }

  bell.addEventListener('click', function (e) { e.stopPropagation(); if (panel.hidden) { openPanel(); } else { closePanel(); } });
  document.addEventListener('click', function (e) { if (!panel.hidden && !host.contains(e.target)) { closePanel(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { closePanel(); bell.focus(); } });
  if (readAll) {
    readAll.addEventListener('click', function () {
      items.forEach(function (a) { a.read = true; });
      unread = 0; paintBadge(); render(); markRead(0);
    });
  }

  function poll() {
    if (document.hidden) { return; }
    fetch(url('?after=' + (first ? 0 : latest)), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.ok) { return; }
        var fresh = j.items || [];
        if (first) {
          items = fresh;
        } else if (fresh.length) {
          items = fresh.concat(items);
          if (root.getAttribute('data-notify') !== 'off' && window.KCCA && KCCA.flash) {
            fresh.slice(0, 2).forEach(function (a) { KCCA.flash(a.title, a.tone === 'bad' ? 'bad' : 'ok', 7000); });
          }
        }
        latest = j.latest || latest;
        unread = j.unread || 0;
        first = false;
        paintBadge();
        if (!panel.hidden) { render(); }
      })
      .catch(function () {});
  }

  poll();
  setInterval(poll, 10000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
})();
