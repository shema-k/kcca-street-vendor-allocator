/* Native navigation with a skeleton confined to the content area. */
(function () {
  'use strict';
  var root = document.documentElement;
  var expiry;
  function layout(url) {
    var page = url.pathname.split('/').pop().replace('.php', '');
    if (/^(dashboard|analytics)$/.test(page)) return 'dashboard';
    if (/^(profile|settings|market_edit|ai_planner)$/.test(page)) return 'form';
    if (/^(city-map|market_map)$/.test(page)) return 'map';
    return 'table';
  }
  function clear() {
    clearTimeout(expiry);
    clearTimeout(window.kccaLoadingFallback);
    root.classList.remove('page-loading');
    var main = document.getElementById('main');
    if (main) main.removeAttribute('aria-busy');
    var status = document.querySelector('.page-loading-status');
    if (status) status.textContent = '';
  }
  function show(url) {
    var skeleton = document.querySelector('[data-page-skeleton]');
    if (!skeleton) return;
    skeleton.setAttribute('data-layout', layout(url));
    root.classList.add('page-loading');
    document.getElementById('main').setAttribute('aria-busy', 'true');
    var status = document.querySelector('.page-loading-status');
    if (status) status.textContent = 'Loading page';
    // Cancelled navigation and downloads must never strand the reader.
    clearTimeout(expiry);
    expiry = setTimeout(clear, 8000);
  }
  function isPage(url) {
    return url.origin === location.origin && /\/pages\/[^/]+\.php$/.test(url.pathname)
      && !/^(csv|pdf|xlsx?|json)$/i.test(url.searchParams.get('format') || '')
      && !url.searchParams.has('download') && !url.searchParams.has('export');
  }
  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    var link = event.target.closest('a[href]');
    if (!link || link.hasAttribute('download') || link.hasAttribute('data-modal') || link.hasAttribute('data-no-loader') || (link.target && link.target !== '_self')) return;
    var url = new URL(link.href, location.href);
    if (!isPage(url) || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
    show(url);
  });
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (event.defaultPrevented || form.method.toLowerCase() !== 'get' || (form.target && form.target !== '_self')) return;
    if (form.hasAttribute('data-no-loader')) return;
    var url = new URL(form.action || location.href, location.href);
    var data = new FormData(form);
    if (event.submitter && event.submitter.name) data.set(event.submitter.name, event.submitter.value);
    url.search = new URLSearchParams(data).toString();
    if (isPage(url)) show(url);
  });
  window.addEventListener('pageshow', clear);
  window.addEventListener('pagehide', clear);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', clear, { once: true });
  else clear();
})();
