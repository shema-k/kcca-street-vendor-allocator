/* KCCA VRSP - feed.js
 *
 * The bell in the top bar: what colleagues have just changed.
 *
 * Every officer reads one shared database, so a saved change is already in
 * the register for everybody. This is only the signal that something
 * changed, so an officer is not working from a screen that has quietly gone
 * stale. One small request every twenty seconds, paused while the tab is
 * hidden; it never reloads or moves the page, it only tells.
 */
(function () {
  'use strict';

  var host = document.querySelector('[data-feed]');
  if (!host) { return; }

  var bell  = host.querySelector('[data-feed-bell]');
  var badge = host.querySelector('[data-feed-count]');
  var panel = host.querySelector('[data-feed-panel]');
  var list  = host.querySelector('[data-feed-list]');
  var uid   = host.getAttribute('data-uid') || '0';
  var KEY   = 'kcca-feed-seen-' + uid;      // per account, so two people on one computer do not share it
  var root  = document.documentElement;

  var maxId = 0, seen = null, unread = 0, items = [], timer = null, stack = null;

  function api(after) {
    var path = 'api/feed.php?after=' + after;
    return (window.KCCA && KCCA.api) ? KCCA.api(path) : '../' + path;
  }

  function getSeen() { try { var v = localStorage.getItem(KEY); return v === null ? null : parseInt(v, 10) || 0; } catch (e) { return null; } }
  function setSeen(n) { try { localStorage.setItem(KEY, String(n)); } catch (e) {} seen = n; }

  function ago(iso) {
    var s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
    if (s < 45) { return 'just now'; }
    if (s < 3600) { return Math.round(s / 60) + ' min ago'; }
    if (s < 86400) { return Math.round(s / 3600) + ' h ago'; }
    return Math.round(s / 86400) + ' d ago';
  }

  function render() {
    list.textContent = '';
    if (!items.length) {
      var li = document.createElement('li'); li.className = 'feed-empty'; li.textContent = 'Nothing has changed yet.';
      list.appendChild(li); return;
    }
    items.slice(0, 20).forEach(function (ev) {
      var li = document.createElement('li');
      li.className = 'feed-item' + (ev.mine ? ' mine' : '') + (ev.id > (seen || 0) && !ev.mine ? ' fresh' : '');
      var who = document.createElement('b'); who.textContent = ev.mine ? 'You' : ev.who;
      var line = document.createElement('span');
      line.appendChild(who); line.appendChild(document.createTextNode(' ' + ev.did));
      var time = document.createElement('small'); time.textContent = ago(ev.at);
      var body = ev.href ? document.createElement('a') : document.createElement('div');
      if (ev.href) { body.href = ev.href; }
      body.className = 'feed-row';
      body.appendChild(line);
      if (ev.detail) { var d = document.createElement('em'); d.textContent = ev.detail; body.appendChild(d); }
      body.appendChild(time);
      li.appendChild(body); list.appendChild(li);
    });
  }

  function paintBadge() {
    if (unread > 0) { badge.textContent = unread > 9 ? '9+' : String(unread); badge.hidden = false; }
    else { badge.hidden = true; }
  }

  function toast(ev) {
    if (root.getAttribute('data-notify') === 'off') { return; }
    if (!stack) { stack = document.createElement('div'); stack.className = 'feed-toasts'; stack.setAttribute('aria-live', 'polite'); document.body.appendChild(stack); }
    if (stack.children.length >= 3) { stack.removeChild(stack.firstChild); }
    var t = document.createElement('button');
    t.type = 'button'; t.className = 'feed-toast';
    var b = document.createElement('b'); b.textContent = ev.who;
    t.appendChild(b); t.appendChild(document.createTextNode(' ' + ev.did));
    t.addEventListener('click', function () { open(); });
    stack.appendChild(t);
    setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 6500);
  }

  function open() {
    panel.hidden = false; bell.setAttribute('aria-expanded', 'true');
    setSeen(maxId); unread = 0; paintBadge(); render();
  }
  function close() { panel.hidden = true; bell.setAttribute('aria-expanded', 'false'); }

  bell.addEventListener('click', function (e) { e.stopPropagation(); if (panel.hidden) { open(); } else { close(); } });
  document.addEventListener('click', function (e) { if (!panel.hidden && !host.contains(e.target)) { close(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { close(); bell.focus(); } });

  function poll(first) {
    var req = new XMLHttpRequest();
    req.open('GET', api(first ? 0 : maxId), true);
    req.onload = function () {
      if (req.status !== 200) { return; }
      var data; try { data = JSON.parse(req.responseText); } catch (e) { return; }
      if (!data || !data.ok) { return; }

      var fresh = data.events || [];
      if (first) {
        items = fresh;
        maxId = data.latest || 0;
        seen = getSeen();
        if (seen === null) { setSeen(maxId); }           // first ever visit: nothing is "new"
        unread = items.filter(function (e) { return !e.mine && e.id > seen; }).length;
      } else if (fresh.length) {
        var known = {};
        items.forEach(function (e) { known[e.id] = true; });
        fresh = fresh.filter(function (e) { return !known[e.id]; });       // never list one change twice
        if (!fresh.length) { maxId = Math.max(maxId, data.latest || 0); return; }
        items = fresh.concat(items).slice(0, 40);
        maxId = Math.max(maxId, data.latest || 0);
        var others = fresh.filter(function (e) { return !e.mine; });
        if (panel.hidden) {
          unread += others.length;
          others.slice(0, 3).reverse().forEach(toast);
        } else { setSeen(maxId); }
      }
      paintBadge();
      if (!panel.hidden) { render(); }
    };
    req.send();
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () { if (!document.hidden) { poll(false); } schedule(); }, 20000);
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(false); } });

  poll(true);
  schedule();
})();
